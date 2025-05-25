<?php
require_once 'db_connection.php';

function generateRequisitionNumber() {
    return 'REQ' . date('YmdHis') . rand(100, 999);
}

$editMode = false;
$requisition_number = '';
$ward_number = '';
$ward_name = '';
$staff_number = '';
$supplier_number = '';
$requisition_date = date('Y-m-d');
$items = []; // array of items to edit or new

// Fetch staff list for selection
$staffList = executeQuery("SELECT staff_number, first_name, last_name FROM Staff ORDER BY first_name");

// Fetch ward list for selection
$wardList = executeQuery("SELECT ward_number, ward_name FROM Wards ORDER BY ward_name");

// Fetch supplier list for selection
$supplierList = executeQuery("SELECT supplier_number, supplier_name FROM Suppliers ORDER BY supplier_name;");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requisition_number = $_POST['requisition_number'] ?? null; // might be empty for new
    $ward_number = $_POST['ward_number'] ?? '';
    $staff_number = $_POST['staff_number'] ?? '';
    $supplier_number = $_POST['supplier_number'] ?? '';
    $requisition_date = $_POST['requisition_date'] ?? '';
    
    $item_numbers = $_POST['item_number'] ?? [];
    $names = $_POST['name'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $methods = $_POST['method_of_admin'] ?? [];
    $costs = $_POST['cost_per_unit'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if (!$ward_number || !$staff_number || !$supplier_number || !$requisition_date) {
        $error = "Ward, Staff, Supplier, and Date are required.";
    } else {
        try {
            $conn->beginTransaction();

            if ($requisition_number) {
                // Update existing requisition header
                $sql = "UPDATE Requisitions SET ward_number = ?, staff_number = ?, requisition_date = ?, supplier_number = ? WHERE requisition_number = ?";
                executeNonQuery($sql, [$ward_number, $staff_number, $requisition_date, $supplier_number, $requisition_number]);

                // Delete old items then reinsert
                executeNonQuery("DELETE FROM Requisition_Items WHERE requisition_number = ?", [$requisition_number]);
            } else {
                // Generate unique requisition_number
                $requisition_number = generateRequisitionNumber();

                // Insert new requisition with generated requisition_number
                $sql = "INSERT INTO Requisitions (requisition_number, ward_number, staff_number, requisition_date, supplier_number) VALUES (?, ?, ?, ?, ?)";
                executeNonQuery($sql, [$requisition_number, $ward_number, $staff_number, $requisition_date, $supplier_number]);
            }

            // Insert items
            $itemCount = count($item_numbers);
            for ($i = 0; $i < $itemCount; $i++) {
                if (empty($item_numbers[$i])) continue; // skip empty rows
                
                $sql = "INSERT INTO Requisition_Items 
                    (requisition_number, item_number, name, description, dosage, method_of_admin, cost_per_unit, quantity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                executeNonQuery($sql, [
                    $requisition_number,
                    $item_numbers[$i],
                    $names[$i] ?? '',
                    $descriptions[$i] ?? '',
                    $dosages[$i] ?? '',
                    $methods[$i] ?? '',
                    $costs[$i] ?? 0,
                    $quantities[$i] ?? 0
                ]);
            }

            $conn->commit();
            header("Location: requisitions.php");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error saving requisition: " . $e->getMessage();
        }
    }
}

// Load existing requisition if editing
if (isset($_GET['edit'])) {
    $requisition_number = $_GET['edit'];
    $editMode = true;

    // Load requisition header
    $req = getSingleRecord("SELECT * FROM Requisitions WHERE requisition_number = ?", [$requisition_number]);
    if ($req) {
        $ward_number = $req['ward_number'];
        $staff_number = $req['staff_number'];
        $supplier_number = $req['supplier_number'] ?? '';
        $requisition_date = $req['requisition_date'];
    }

    // Load items
    $items = executeQuery("SELECT * FROM Requisition_Items WHERE requisition_number = ?", [$requisition_number]);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title><?php echo $editMode ? "Edit" : "Add"; ?> Requisition</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 2rem;
    }

    label {
        display: block;
        margin-top: 1rem;
    }

    input,
    select {
        padding: 6px;
        width: 300px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }

    th,
    td {
        border: 1px solid #ddd;
        padding: 8px;
    }

    th {
        background: #34495e;
        color: white;
    }

    input[type=number] {
        width: 100px;
    }

    .button {
        margin-top: 1rem;
        padding: 8px 16px;
        background: #27ae60;
        color: white;
        border: none;
        cursor: pointer;
        border-radius: 4px;
    }

    .error {
        color: red;
        margin-top: 1rem;
    }
    </style>
    <script>
    function addItemRow() {
        const table = document.getElementById('items-table').getElementsByTagName('tbody')[0];
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
        <td><input type="text" name="item_number[]" /></td>
        <td><input type="text" name="name[]" /></td>
        <td><input type="text" name="description[]" /></td>
        <td><input type="text" name="dosage[]" /></td>
        <td><input type="text" name="method_of_admin[]" /></td>
        <td><input type="number" step="0.01" name="cost_per_unit[]" /></td>
        <td><input type="number" name="quantity[]" min="1" /></td>
    `;
        table.appendChild(newRow);
    }
    </script>
</head>

<body>

    <h1><?php echo $editMode ? "Edit" : "Add"; ?> Requisition</h1>

    <?php if (!empty($error)): ?>
    <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php if ($editMode): ?>
        <input type="hidden" name="requisition_number" value="<?php echo htmlspecialchars($requisition_number); ?>" />
        <?php endif; ?>

        <label>Ward Number:
            <select name="ward_number" required>
                <option value="">--Select Ward--</option>
                <?php foreach ($wardList as $ward): ?>
                <option value="<?php echo htmlspecialchars($ward['ward_number']); ?>"
                    <?php if ($ward['ward_number'] == $ward_number) echo "selected"; ?>>
                    <?php echo htmlspecialchars($ward['ward_number'] . ' - ' . $ward['ward_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Requisitioned By (Staff):
            <select name="staff_number" required>
                <option value="">--Select Staff--</option>
                <?php foreach ($staffList as $staff): ?>
                <option value="<?php echo htmlspecialchars($staff['staff_number']); ?>"
                    <?php if ($staff['staff_number'] == $staff_number) echo "selected"; ?>>
                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Supplier:
            <select name="supplier_number" required>
                <option value="">--Select Supplier--</option>
                <?php foreach ($supplierList as $supplier): ?>
                <option value="<?php echo htmlspecialchars($supplier['supplier_number']); ?>" <?php
                if (($editMode && $supplier_number == $supplier['supplier_number']) ||
                    (isset($_POST['supplier_number']) && $_POST['supplier_number'] == $supplier['supplier_number'])) {
                    echo "selected";
                }
            ?>>
                    <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>


        <label>Requisition Date:
            <input type="date" name="requisition_date" value="<?php echo htmlspecialchars($requisition_date); ?>"
                required />
        </label>

        <h3>Items/Drugs</h3>
        <table id="items-table">
            <thead>
                <tr>
                    <th>Item No.</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Dosage</th>
                    <th>Method</th>
                    <th>Cost/Unit</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($editMode && !empty($items)): ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><input type="text" name="item_number[]"
                            value="<?php echo htmlspecialchars($item['item_number'] ?? ''); ?>" /></td>
                    <td><input type="text" name="name[]" value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" />
                    </td>
                    <td><input type="text" name="description[]"
                            value="<?php echo htmlspecialchars($item['description'] ?? ''); ?>" /></td>
                    <td><input type="text" name="dosage[]"
                            value="<?php echo htmlspecialchars($item['dosage'] ?? ''); ?>" /></td>
                    <td><input type="text" name="method_of_admin[]"
                            value="<?php echo htmlspecialchars($item['method_of_admin'] ?? ''); ?>" /></td>
                    <td><input type="number" step="0.01" name="cost_per_unit[]"
                            value="<?php echo htmlspecialchars($item['cost_per_unit'] ?? ''); ?>" /></td>
                    <td><input type="number" name="quantity[]"
                            value="<?php echo htmlspecialchars($item['quantity'] ?? ''); ?>" /></td>

                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td><input type="text" name="item_number[]" /></td>
                    <td><input type="text" name="name[]" /></td>
                    <td><input type="text" name="description[]" /></td>
                    <td><input type="text" name="dosage[]" /></td>
                    <td><input type="text" name="method_of_admin[]" /></td>
                    <td><input type="number" step="0.01" name="cost_per_unit[]" /></td>
                    <td><input type="number" name="quantity[]" min="1" /></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <button type="button" class="button" onclick="addItemRow()">Add Another Item</button>
        <br />
        <button type="submit" class="button">Save Requisition</button>
    </form>
</body>

</html>