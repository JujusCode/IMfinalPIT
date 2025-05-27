<?php
require_once 'db_connection.php';

$message = '';
$messageType = '';
$requisition = null;
$isEditing = false;
$currentWard = isset($_GET['ward']) ? $_GET['ward'] : null;

$currentStaff = 'CN001'; // Example staff number

$surgicalSupplies = executeQuery("SELECT * FROM Surgical_Supplies ORDER BY item_name");
$pharmaceuticalSupplies = executeQuery("SELECT * FROM Pharmaceutical_Supplies ORDER BY drug_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requisitionNumber = $_POST['requisition_number'] ?? '';
    $wardNumber = $_POST['ward_number'] ?? '';
    $requisitionDate = $_POST['requisition_date'] ?? date('Y-m-d');
    $items = $_POST['items'] ?? [];

    if (empty($wardNumber)) {
        $message = 'Ward number is required.';
        $messageType = 'danger';
    } else {
        // Check if ward and staff exist
        $wardExists = executeQuery("SELECT 1 FROM Wards WHERE ward_number = ?", [$wardNumber]);
        $staffExists = executeQuery("SELECT 1 FROM Staff WHERE staff_number = ?", [$currentStaff]);

        if (empty($wardExists) || empty($staffExists)) {
            $message = 'Ward or Staff does not exist.';
            $messageType = 'danger';
        } else {
            if (!beginTransaction()) {
                $message = "Failed to start transaction.";
                $messageType = 'danger';
            } else {
                try {
                    if (isset($_POST['edit'])) {
                        $result = executeNonQuery(
                            "UPDATE Requisitions SET ward_number = ?, requisition_date = ?, staff_number = ? WHERE requisition_number = ?",
                            [$wardNumber, $requisitionDate, $currentStaff, $requisitionNumber]
                        );

                        if (!$result) {
                            throw new Exception("Failed to update requisition.");
                        }

                        $deleteResult = executeNonQuery(
                            "DELETE FROM Requisition_Items WHERE requisition_number = ?",
                            [$requisitionNumber]
                        );

                        if (!$deleteResult) {
                            throw new Exception("Failed to delete existing items.");
                        }
                    } else {
                        $requisitionNumber = 'REQ-' . date('YmdHis') . '-' . bin2hex(random_bytes(2));

                        $result = executeNonQuery(
                            "INSERT INTO Requisitions (requisition_number, ward_number, staff_number, requisition_date) VALUES (?, ?, ?, ?)",
                            [$requisitionNumber, $wardNumber, $currentStaff, $requisitionDate]
                        );

                        if (!$result) {
                            throw new Exception("Failed to create new requisition.");
                        }
                    }

                    foreach ($items as $item) {
                        if (!empty($item['quantity']) && $item['quantity'] > 0) {
                            if (!empty($item['type']) && $item['type'] === 'surgical' && !empty($item['item_number'])) {
                                $itemResult = executeNonQuery(
                                    "INSERT INTO Requisition_Items (requisition_number, item_number, quantity) VALUES (?, ?, ?)",
                                    [$requisitionNumber, $item['item_number'], $item['quantity']]
                                );
                            } elseif (!empty($item['type']) && $item['type'] === 'pharmaceutical' && !empty($item['drug_number'])) {
                                $itemResult = executeNonQuery(
                                    "INSERT INTO Requisition_Items (requisition_number, drug_number, quantity) VALUES (?, ?, ?)",
                                    [$requisitionNumber, $item['drug_number'], $item['quantity']]
                                );
                            } else {
                                continue;
                            }

                            if (!isset($itemResult) || !$itemResult) {
                                throw new Exception("Failed to add requisition items.");
                            }
                        }
                    }

                    if (!commitTransaction()) {
                        throw new Exception("Failed to commit transaction.");
                    }

                    $message = "Requisition " . (isset($_POST['edit']) ? "updated" : "created") . " successfully.";
                    $messageType = 'success';

                    header("Location: ward_requisitions.php?ward=" . urlencode($wardNumber));
                    exit();
                } catch (Exception $e) {
                    rollbackTransaction();
                    $message = "Failed to process requisition: " . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $requisitionNumber = $_GET['delete'];

    if (!beginTransaction()) {
        $message = "Failed to start transaction.";
        $messageType = 'danger';
    } else {
        try {
            $deleteItems = executeNonQuery(
                "DELETE FROM Requisition_Items WHERE requisition_number = ?",
                [$requisitionNumber]
            );

            if (!$deleteItems) {
                throw new Exception("Failed to delete requisition items.");
            }

            $deleteRequisition = executeNonQuery(
                "DELETE FROM Requisitions WHERE requisition_number = ?",
                [$requisitionNumber]
            );

            if (!$deleteRequisition) {
                throw new Exception("Failed to delete requisition.");
            }

            if (!commitTransaction()) {
                throw new Exception("Failed to commit transaction.");
            }

            $message = "Requisition deleted successfully.";
            $messageType = 'success';

            header("Location: ward_requisitions.php?ward=" . urlencode($currentWard));
            exit();
        } catch (Exception $e) {
            rollbackTransaction();
            $message = "Failed to delete requisition: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

if (isset($_GET['edit'])) {
    $requisitionNumber = $_GET['edit'];
    $requisition = getSingleRecord(
        "SELECT * FROM Requisitions WHERE requisition_number = ?",
        [$requisitionNumber]
    );
    
    if ($requisition) {
        $isEditing = true;
        $currentWard = $requisition['ward_number'];
        $requisitionItems = executeQuery(
            "SELECT * FROM Requisition_Items WHERE requisition_number = ?",
            [$requisitionNumber]
        );
    }
}

$wardDetails = $currentWard ? getSingleRecord(
    "SELECT * FROM Wards WHERE ward_number = ?",
    [$currentWard]
) : null;

$requisitions = $currentWard ? executeQuery(
    "SELECT r.*, CONCAT(s.first_name, ' ', s.last_name) AS staff_name, r.signed_by, r.delivery_date FROM Requisitions r JOIN Staff s ON r.staff_number = s.staff_number WHERE r.ward_number = ? ORDER BY r.requisition_date DESC",
    [$currentWard]
) : [];

$allWards = executeQuery("SELECT * FROM Wards ORDER BY ward_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Ward Requisitions - Wellmeadows Hospital</title>
<style>
:root {
    --primary-color: #3498db;
    --secondary-color: #2c3e50;
    --success-color: #2ecc71;
    --danger-color: #e74c3c;
    --warning-color: #f39c12;
    --light-color: #ecf0f1;
    --dark-color: #34495e;
}
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Arial', sans-serif;
}
body {
    background-color: #f5f7fa;
    color: #333;
    line-height: 1.6;
}
.container {
    width: 1350px;
    max-width: 90%;
    margin: 0 auto;
    padding: 20px;
}
header {
    background-color: var(--primary-color);
    color: white;
    padding: 1rem 0;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}
header .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.logo {
    font-size: 1.5rem;
    font-weight: bold;
}
nav ul {
    display: flex;
    list-style: none;
}
nav ul li {
    margin-left: 20px;
}
nav ul li a {
    color: white;
    text-decoration: none;
    transition: color 0.3s;
}
nav ul li a:hover {
    color: var(--light-color);
}
main {
    padding: 2rem 0;
}
.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    padding: 20px;
    margin-bottom: 20px;
}
.card-header {
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
    font-weight: bold;
    color: var(--secondary-color);
}
.table-container {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
table, th, td {
    border: 1px solid #ddd;
}
th, td {
    padding: 12px 15px;
    text-align: left;
}
th {
    background-color: var(--primary-color);
    color: white;
}
tr:nth-child(even) {
    background-color: #f2f2f2;
}
tr:hover {
    background-color: #e9e9e9;
}
.btn {
    display: inline-block;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    background-color: var(--primary-color);
    color: white;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.3s;
}
.btn:hover {
    background-color: #2980b9;
}
.btn-success {
    background-color: var(--success-color);
}
.btn-success:hover {
    background-color: #27ae60;
}
.btn-danger {
    background-color: var(--danger-color);
}
.btn-danger:hover {
    background-color: #c0392b;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}
.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}
.form-control:focus {
    border-color: var(--primary-color);
    outline: none;
}
.alert {
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}
.alert-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
.form-row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -10px;
    margin-left: -10px;
}
.form-col {
    flex: 1;
    padding: 0 10px;
    min-width: 200px;
}
.item-row {
    display: flex;
    margin-bottom: 10px;
    align-items: center;
}
.item-type {
    flex: 1;
    margin-right: 10px;
}
.item-select {
    flex: 2;
    margin-right: 10px;
}
.item-quantity {
    flex: 1;
    margin-right: 10px;
}
.item-remove {
    flex: 0 0 30px;
}
#items-container {
    margin-bottom: 15px;
}
.requisition-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}
.status-pending {
    background-color: #fff3cd;
    color: #856404;
}
.status-approved {
    background-color: #d4edda;
    color: #155724;
}
.status-fulfilled {
    background-color: #d1ecf1;
    color: #0c5460;
}
.status-denied {
    background-color: #f8d7da;
    color: #721c24;
}
</style>
<script>
function addItemRow(type = 'surgical', selectedItem = '', quantity = 1) {
    const container = document.getElementById('items-container');
    const row = document.createElement('div');
    row.className = 'item-row';

    // Type selector
    const typeSelect = document.createElement('select');
    typeSelect.name = 'items[][type]';
    typeSelect.className = 'form-control item-type';
    typeSelect.innerHTML = `<option value="surgical">Surgical Item</option><option value="pharmaceutical">Pharmaceutical</option>`;
    typeSelect.value = type;

    // Surgical item selector
    const surgicalSelect = document.createElement('select');
    surgicalSelect.name = 'items[][item_number]';
    surgicalSelect.className = 'form-control item-select';

    // Pharmaceutical item selector
    const pharmaSelect = document.createElement('select');
    pharmaSelect.name = 'items[][drug_number]';
    pharmaSelect.className = 'form-control item-select';

    // Populate surgical items
    const surgicalItems = <?php echo json_encode($surgicalSupplies); ?>;
    surgicalItems.forEach(item => {
        const option = document.createElement('option');
        option.value = item.item_number;
        option.textContent = item.item_name + ' (' + item.item_number + ')';
        surgicalSelect.appendChild(option);
    });

    // Populate pharmaceutical items
    const pharmaItems = <?php echo json_encode($pharmaceuticalSupplies); ?>;
    pharmaItems.forEach(item => {
        const option = document.createElement('option');
        option.value = item.drug_number;
        option.textContent = item.drug_name + ' (' + item.drug_number + ')';
        pharmaSelect.appendChild(option);
    });

    // Quantity input
    const quantityInput = document.createElement('input');
    quantityInput.type = 'number';
    quantityInput.name = 'items[][quantity]';
    quantityInput.className = 'form-control item-quantity';
    quantityInput.min = '1';
    quantityInput.value = quantity;
    quantityInput.required = true;

    // Remove button
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-danger item-remove';
    removeBtn.textContent = '×';
    removeBtn.onclick = () => container.removeChild(row);

    // Show/hide selects based on type
    function updateVisibility() {
        if(typeSelect.value === 'surgical') {
            surgicalSelect.style.display = 'block';
            pharmaSelect.style.display = 'none';
        } else {
            surgicalSelect.style.display = 'none';
            pharmaSelect.style.display = 'block';
        }
    }

    typeSelect.addEventListener('change', updateVisibility);
    updateVisibility();

    // Set selected items
    if(type === 'surgical') {
        surgicalSelect.value = selectedItem;
    } else {
        pharmaSelect.value = selectedItem;
    }

    // Append controls to row
    row.appendChild(typeSelect);
    row.appendChild(surgicalSelect);
    row.appendChild(pharmaSelect);
    row.appendChild(quantityInput);
    row.appendChild(removeBtn);

    container.appendChild(row);
}

document.addEventListener('DOMContentLoaded', () => {
    <?php if ($isEditing && !empty($requisitionItems)): ?>
        <?php foreach ($requisitionItems as $item):
            $type = !empty($item['item_number']) ? 'surgical' : 'pharmaceutical';
            $selectedVal = $type === 'surgical' ? $item['item_number'] : $item['drug_number'];
            $qty = (int) $item['quantity'];
        ?>
        addItemRow('<?php echo $type; ?>', '<?php echo htmlspecialchars($selectedVal); ?>', <?php echo $qty; ?>);
        <?php endforeach; ?>
    <?php else: ?>
        addItemRow();
    <?php endif; ?>
});
</script>
</head>
<body>
<header>
    <div class="container">
        <div class="logo">Wellmeadows Hospital</div>
        <nav>
            <ul>
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="wards.php">Wards</a></li>
                <li><a href="staff.php">Staff</a></li>
                <li><a href="patients.php">Patients</a></li>
                <li><a href="appointments.php">Appointments</a></li>
                <li><a href="supplies.php">Supplies</a></li>
                <li><a href="staff_report.php">Reports</a></li>
                <li><a href="patient_medication.php">Medication</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="container">
<h1>Ward Requisitions Management</h1>
<?php if (!empty($message)): ?>
<div class="alert alert-<?php echo htmlspecialchars($messageType); ?>">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($currentWard && $wardDetails): ?>
<div class="card">
    <div class="card-header">
        Requisitions for <?php echo htmlspecialchars($wardDetails['ward_name']); ?> (Ward <?php echo htmlspecialchars($currentWard); ?>)
        <a href="wards.php" class="btn" style="float: right;">Back to Wards</a>
        <a href="ward_requisitions.php" class="btn" style="float: right; margin-right: 10px;">Back to requisition list</a>
    </div>

    <div class="card">
        <div class="card-header"><?php echo $isEditing ? 'Edit Requisition' : 'Create New Requisition'; ?></div>
        <form method="POST" action="ward_requisitions.php?ward=<?php echo urlencode($currentWard); ?>">
            <?php if ($isEditing): ?>
                <input type="hidden" name="edit" value="1" />
                <input type="hidden" name="requisition_number" value="<?php echo htmlspecialchars($requisition['requisition_number']); ?>" />
            <?php endif; ?>

            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="ward_number">Ward</label>
                        <select class="form-control" id="ward_number" name="ward_number" required>
                            <?php foreach ($allWards as $ward): ?>
                                <option value="<?php echo htmlspecialchars($ward['ward_number']); ?>"
                                    <?php echo $currentWard == $ward['ward_number'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ward['ward_name']); ?> (<?php echo htmlspecialchars($ward['ward_number']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="requisition_date">Requisition Date</label>
                        <input type="date" class="form-control" id="requisition_date" name="requisition_date" value="<?php echo $isEditing ? htmlspecialchars($requisition['requisition_date']) : date('Y-m-d'); ?>" required />
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Items Requested</label>
                <div id="items-container"></div>
                <button type="button" class="btn" onclick="addItemRow()">Add Item</button>
            </div>

            <button type="submit" class="btn btn-success">
                <?php echo $isEditing ? 'Update Requisition' : 'Create Requisition'; ?>
            </button>
            <?php if ($isEditing): ?>
            <a href="ward_requisitions.php?ward=<?php echo urlencode($currentWard); ?>" class="btn">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header">Previous Requisitions</div>
        <div class="table-container">
            <table>
                <thead>
                <tr>
                    <th>Requisition #</th>
                    <th>Date</th>
                    <th>Requested By</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($requisitions) > 0): ?>
                    <?php foreach ($requisitions as $req):
                        $items = executeQuery(
                            "SELECT 
                                COALESCE(ri.item_number, ri.drug_number) as item_id,
                                COALESCE(ss.item_name, ps.drug_name) as item_name,
                                ri.quantity
                             FROM Requisition_Items ri
                             LEFT JOIN Surgical_Supplies ss ON ri.item_number = ss.item_number
                             LEFT JOIN Pharmaceutical_Supplies ps ON ri.drug_number = ps.drug_number
                             WHERE ri.requisition_number = ?",
                            [$req['requisition_number']]
                        );
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($req['requisition_number']); ?></td>
                            <td><?php echo htmlspecialchars($req['requisition_date']); ?></td>
                            <td><?php echo htmlspecialchars($req['staff_name']); ?></td>
                            <td>
                                <?php if (empty($req['signed_by']) && empty($req['delivery_date'])): ?>
                                    <span class="requisition-status status-pending">Pending</span>
                                <?php elseif (!empty($req['signed_by']) && empty($req['delivery_date'])): ?>
                                    <span class="requisition-status status-approved">Approved</span>
                                <?php elseif (!empty($req['delivery_date'])): ?>
                                    <span class="requisition-status status-fulfilled">Fulfilled</span>
                                <?php else: ?>
                                    <span class="requisition-status status-denied">Denied</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <ul>
                                    <?php foreach ($items as $item): ?>
                                        <li><?php echo htmlspecialchars($item['item_name']); ?> (x<?php echo htmlspecialchars($item['quantity']); ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <?php if (empty($req['signed_by']) && empty($req['delivery_date'])): ?>
                                    <a href="ward_requisitions.php?ward=<?php echo urlencode($currentWard); ?>&edit=<?php echo htmlspecialchars($req['requisition_number']); ?>" class="btn">Edit</a>
                                    <a href="ward_requisitions.php?ward=<?php echo urlencode($currentWard); ?>&delete=<?php echo htmlspecialchars($req['requisition_number']); ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this requisition?')">Delete</a>
                                <?php endif; ?>
                                <a href="requisition_print.php?id=<?php echo htmlspecialchars($req['requisition_number']); ?>" class="btn" target="_blank">Print</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No requisitions found for this ward.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php else: ?>
    <div class="card">
        <div class="card-header">Select a Ward</div>
        <div class="table-container">
            <table>
                <thead>
                <tr>
                    <th>Ward Number</th>
                    <th>Ward Name</th>
                    <th>Location</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($allWards as $ward): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ward['ward_number']); ?></td>
                        <td><?php echo htmlspecialchars($ward['ward_name']); ?></td>
                        <td><?php echo htmlspecialchars($ward['location']); ?></td>
                        <td>
                            <a href="ward_requisitions.php?ward=<?php echo urlencode($ward['ward_number']); ?>" class="btn">View Requisitions</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
</main>

<footer>
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
    </div>
</footer>
</body>
</html>


