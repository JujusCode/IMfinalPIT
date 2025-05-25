<?php
include 'db_connection.php';

// Initialize variables
$message = '';
$messageType = '';
$supply = null;
$isEditing = false;

// Handle form submission for adding/updating a supply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['supply_type'];
    
    if ($type == 'pharmaceutical') {
        // Pharmaceutical supply data
        $drug_number = $_POST['drug_number'] ?? '';
        $drug_name = $_POST['drug_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $dosage = $_POST['dosage'] ?? '';
        $method_of_administration = $_POST['method_of_administration'] ?? '';
        $quantity = $_POST['quantity'] ?? 0;
        $reorder_level = $_POST['reorder_level'] ?? 0;
        $cost = $_POST['cost'] ?? 0.0;
        
        // Validate inputs
        if (empty($drug_number) || empty($drug_name) || empty($dosage) || 
            empty($method_of_administration) || empty($quantity) || empty($reorder_level) || empty($cost)) {
            $message = 'All fields are required for pharmaceutical supplies.';
            $messageType = 'danger';
        } else {
            // Check if supply exists
            $existingSupply = getSingleRecord(
                "SELECT * FROM Pharmaceutical_Supplies WHERE drug_number = ?", 
                [$drug_number]
            );
            
            if ($existingSupply) {
                // Update existing pharmaceutical supply
                $result = executeNonQuery(
                    "UPDATE Pharmaceutical_Supplies SET 
                        drug_name = ?, 
                        description = ?, 
                        dosage = ?, 
                        method_of_administration = ?, 
                        quantity_in_stock = ?, 
                        reorder_level = ?, 
                        cost_per_unit = ? 
                    WHERE drug_number = ?",
                    [$drug_name, $description, $dosage, $method_of_administration, 
                     $quantity, $reorder_level, $cost, $drug_number]
                );
                
                if ($result) {
                    $message = "Pharmaceutical supply updated successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to update pharmaceutical supply.";
                    $messageType = 'danger';
                }
            } else {
                // Insert new pharmaceutical supply
                $result = executeNonQuery(
                    "INSERT INTO Pharmaceutical_Supplies 
                        (drug_number, drug_name, description, dosage, method_of_administration, 
                         quantity_in_stock, reorder_level, cost_per_unit) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$drug_number, $drug_name, $description, $dosage, $method_of_administration, 
                     $quantity, $reorder_level, $cost]
                );
                
                if ($result) {
                    $message = "Pharmaceutical supply added successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to add pharmaceutical supply.";
                    $messageType = 'danger';
                }
            }
        }
    } else {
        // Surgical supply data
        $item_number = $_POST['item_number'] ?? '';
        $item_name = $_POST['item_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $quantity = $_POST['quantity'] ?? 0;
        $reorder_level = $_POST['reorder_level'] ?? 0;
        $cost = $_POST['cost'] ?? 0.0;
        $is_surgical = isset($_POST['is_surgical']) ? 1 : 0;
        
        // Validate inputs
        if (empty($item_number) || empty($item_name) || empty($quantity) || 
            empty($reorder_level) || empty($cost)) {
            $message = 'All fields are required for surgical supplies.';
            $messageType = 'danger';
        } else {
            // Check if supply exists
            $existingSupply = getSingleRecord(
                "SELECT * FROM Surgical_Supplies WHERE item_number = ?", 
                [$item_number]
            );
            
            if ($existingSupply) {
                // Update existing surgical supply
                $result = executeNonQuery(
                    "UPDATE Surgical_Supplies SET 
                        item_name = ?, 
                        description = ?, 
                        quantity_in_stock = ?, 
                        reorder_level = ?, 
                        cost_per_unit = ?,
                        is_surgical = ?
                    WHERE item_number = ?",
                    [$item_name, $description, $quantity, $reorder_level, $cost, $is_surgical, $item_number]
                );
                
                if ($result) {
                    $message = "Surgical supply updated successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to update surgical supply.";
                    $messageType = 'danger';
                }
            } else {
                // Insert new surgical supply
                $result = executeNonQuery(
                    "INSERT INTO Surgical_Supplies 
                        (item_number, item_name, description, quantity_in_stock, 
                         reorder_level, cost_per_unit, is_surgical) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$item_number, $item_name, $description, $quantity, $reorder_level, $cost, $is_surgical]
                );
                
                if ($result) {
                    $message = "Surgical supply added successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to add surgical supply.";
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Handle supply deletion
if (isset($_GET['delete'])) {
    $type = $_GET['type'];
    $id = $_GET['delete'];
    
    if ($type == 'pharmaceutical') {
        $result = executeNonQuery(
            "DELETE FROM Pharmaceutical_Supplies WHERE drug_number = ?",
            [$id]
        );
    } else {
        $result = executeNonQuery(
            "DELETE FROM Surgical_Supplies WHERE item_number = ?",
            [$id]
        );
    }
    
    if ($result) {
        $message = "Supply deleted successfully.";
        $messageType = 'success';
    } else {
        $message = "Failed to delete supply.";
        $messageType = 'danger';
    }
}

// Handle supply editing
if (isset($_GET['edit'])) {
    $type = $_GET['type'];
    $id = $_GET['edit'];
    
    if ($type == 'pharmaceutical') {
        $supply = getSingleRecord(
            "SELECT * FROM Pharmaceutical_Supplies WHERE drug_number = ?",
            [$id]
        );
        $isEditing = true;
    } else {
        $supply = getSingleRecord(
            "SELECT * FROM Surgical_Supplies WHERE item_number = ?",
            [$id]
        );
        $isEditing = true;
    }
}

// Get all supplies
$pharmaceuticalSupplies = executeQuery("SELECT * FROM Pharmaceutical_Supplies ORDER BY drug_name");
$surgicalSupplies = executeQuery("SELECT * FROM Surgical_Supplies ORDER BY item_name");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supply Management - Wellmeadows Hospital</title>
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
        width: 1140px;
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

    table,
    th,
    td {
        border: 1px solid #ddd;
    }

    th,
    td {
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

    .supply-type-fields {
        display: none;
    }

    .supply-type-fields.active {
        display: block;
    }

    footer {
        background-color: var(--secondary-color);
        color: white;
        padding: 1rem 0;
        text-align: center;
        margin-top: 2rem;
    }
    </style>
    <script>
    function toggleSupplyFields() {
        const type = document.getElementById('supply_type').value;

        // Hide all fields first
        document.querySelectorAll('.supply-type-fields').forEach(el => {
            el.classList.remove('active');
        });

        // Show the selected type's fields
        document.getElementById(type + '_fields').classList.add('active');
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleSupplyFields();
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
        <h1>Supply Management</h1>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <?php echo $isEditing ? 'Edit Supply' : 'Add New Supply'; ?>
            </div>
            <form method="POST" action="supplies.php">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="supply_type">Supply Type</label>
                            <select class="form-control" id="supply_type" name="supply_type"
                                onchange="toggleSupplyFields()" <?php echo $isEditing ? 'disabled' : ''; ?>>
                                <option value="pharmaceutical"
                                    <?php echo ($isEditing && isset($supply['drug_number'])) ? 'selected' : ''; ?>>
                                    Pharmaceutical</option>
                                <option value="surgical"
                                    <?php echo ($isEditing && isset($supply['item_number'])) ? 'selected' : ''; ?>>
                                    Surgical</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Pharmaceutical Supply Fields -->
                <div id="pharmaceutical_fields"
                    class="supply-type-fields <?php echo ($isEditing && isset($supply['drug_number'])) ? 'active' : ''; ?>">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="drug_number">Drug Number</label>
                                <input type="text" class="form-control" id="drug_number" name="drug_number"
                                    value="<?php echo $isEditing ? $supply['drug_number'] : ''; ?>"
                                    <?php echo $isEditing ? 'readonly' : ''; ?> required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="drug_name">Drug Name</label>
                                <input type="text" class="form-control" id="drug_name" name="drug_name"
                                    value="<?php echo $isEditing ? $supply['drug_name'] : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description"
                                    name="description"><?php echo $isEditing ? $supply['description'] : ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="dosage">Dosage</label>
                                <input type="text" class="form-control" id="dosage" name="dosage"
                                    value="<?php echo $isEditing ? $supply['dosage'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="method_of_administration">Method of Administration</label>
                                <input type="text" class="form-control" id="method_of_administration"
                                    name="method_of_administration"
                                    value="<?php echo $isEditing ? $supply['method_of_administration'] : ''; ?>"
                                    required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="quantity">Quantity in Stock</label>
                                <input type="number" class="form-control" id="quantity" name="quantity"
                                    value="<?php echo $isEditing ? $supply['quantity_in_stock'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="reorder_level">Reorder Level</label>
                                <input type="number" class="form-control" id="reorder_level" name="reorder_level"
                                    value="<?php echo $isEditing ? $supply['reorder_level'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="cost">Cost Per Unit</label>
                                <input type="number" step="0.01" class="form-control" id="cost" name="cost"
                                    value="<?php echo $isEditing ? $supply['cost_per_unit'] : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Surgical Supply Fields -->
                <div id="surgical_fields"
                    class="supply-type-fields <?php echo ($isEditing && isset($supply['item_number'])) ? 'active' : ''; ?>">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="item_number">Item Number</label>
                                <input type="text" class="form-control" id="item_number" name="item_number"
                                    value="<?php echo $isEditing ? $supply['item_number'] : ''; ?>"
                                    <?php echo $isEditing ? 'readonly' : ''; ?> required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="item_name">Item Name</label>
                                <input type="text" class="form-control" id="item_name" name="item_name"
                                    value="<?php echo $isEditing ? $supply['item_name'] : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description"
                                    name="description"><?php echo $isEditing ? $supply['description'] : ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="quantity">Quantity in Stock</label>
                                <input type="number" class="form-control" id="quantity" name="quantity"
                                    value="<?php echo $isEditing ? $supply['quantity_in_stock'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="reorder_level">Reorder Level</label>
                                <input type="number" class="form-control" id="reorder_level" name="reorder_level"
                                    value="<?php echo $isEditing ? $supply['reorder_level'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="cost">Cost Per Unit</label>
                                <input type="number" step="0.01" class="form-control" id="cost" name="cost"
                                    value="<?php echo $isEditing ? $supply['cost_per_unit'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="is_surgical">Surgical Item</label>
                                <select class="form-control" id="is_surgical" name="is_surgical">
                                    <option value="1"
                                        <?php echo ($isEditing && $supply['is_surgical'] == 1) ? 'selected' : ''; ?>>Yes
                                    </option>
                                    <option value="0"
                                        <?php echo ($isEditing && $supply['is_surgical'] == 0) ? 'selected' : ''; ?>>No
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <?php echo $isEditing ? 'Update Supply' : 'Add Supply'; ?>
                </button>

                <?php if ($isEditing): ?>
                <a href="supplies.php" class="btn">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="card-header">Pharmaceutical Supplies</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Drug Number</th>
                            <th>Drug Name</th>
                            <th>Dosage</th>
                            <th>Method</th>
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Cost</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pharmaceuticalSupplies) > 0): ?>
                        <?php foreach ($pharmaceuticalSupplies as $supply): ?>
                        <tr>
                            <td><?php echo $supply['drug_number']; ?></td>
                            <td><?php echo $supply['drug_name']; ?></td>
                            <td><?php echo $supply['dosage']; ?></td>
                            <td><?php echo $supply['method_of_administration']; ?></td>
                            <td><?php echo $supply['quantity_in_stock']; ?></td>
                            <td><?php echo $supply['reorder_level']; ?></td>
                            <td><?php echo number_format($supply['cost_per_unit'], 2); ?></td>
                            <td>
                                <a href="supplies.php?edit=<?php echo $supply['drug_number']; ?>&type=pharmaceutical"
                                    class="btn">Edit</a>
                                <a href="supplies.php?delete=<?php echo $supply['drug_number']; ?>&type=pharmaceutical"
                                    class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to delete this pharmaceutical supply?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No pharmaceutical supplies found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Surgical Supplies</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Item Number</th>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Cost</th>
                            <th>Surgical</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($surgicalSupplies) > 0): ?>
                        <?php foreach ($surgicalSupplies as $supply): ?>
                        <tr>
                            <td><?php echo $supply['item_number']; ?></td>
                            <td><?php echo $supply['item_name']; ?></td>
                            <td><?php echo $supply['description']; ?></td>
                            <td><?php echo $supply['quantity_in_stock']; ?></td>
                            <td><?php echo $supply['reorder_level']; ?></td>
                            <td><?php echo number_format($supply['cost_per_unit'], 2); ?></td>
                            <td><?php echo $supply['is_surgical'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a href="supplies.php?edit=<?php echo $supply['item_number']; ?>&type=surgical"
                                    class="btn">Edit</a>
                                <a href="supplies.php?delete=<?php echo $supply['item_number']; ?>&type=surgical"
                                    class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to delete this surgical supply?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No surgical supplies found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>