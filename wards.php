<?php
// Include database connection
require_once 'db_connection.php';

// Initialize variables
$message = '';
$messageType = '';
$ward = null;

// Handle form submission for adding/updating a ward
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wardNumber = $_POST['ward_number'] ?? '';
    $wardName = $_POST['ward_name'] ?? '';
    $location = $_POST['location'] ?? '';
    $totalBeds = $_POST['total_beds'] ?? 0;
    $telephoneExtension = $_POST['telephone_extension'] ?? '';
    
    // Validate inputs
    if (empty($wardNumber) || empty($wardName) || empty($location) || empty($totalBeds) || empty($telephoneExtension)) {
        $message = 'All fields are required.';
        $messageType = 'danger';
    } else {
        // Check if ward exists
        $existingWard = getSingleRecord(
            "SELECT * FROM Wards WHERE ward_number = ?", 
            [$wardNumber]
        );
        
        if ($existingWard) {
            // Update existing ward
            $result = executeNonQuery(
                "UPDATE Wards SET ward_name = ?, location = ?, total_beds = ?, telephone_extension = ? WHERE ward_number = ?",
                [$wardName, $location, $totalBeds, $telephoneExtension, $wardNumber]
            );
            
            if ($result) {
                $message = "Ward updated successfully.";
                $messageType = 'success';
            } else {
                $message = "Failed to update ward.";
                $messageType = 'danger';
            }
        } else {
            // Insert new ward
            $result = executeNonQuery(
                "INSERT INTO Wards (ward_number, ward_name, location, total_beds, telephone_extension) VALUES (?, ?, ?, ?, ?)",
                [$wardNumber, $wardName, $location, $totalBeds, $telephoneExtension]
            );
            
            if ($result) {
                $message = "Ward added successfully.";
                $messageType = 'success';
            } else {
                $message = "Failed to add ward.";
                $messageType = 'danger';
            }
        }
    }
}

// Handle ward deletion
if (isset($_GET['delete'])) {
    $wardNumber = $_GET['delete'];
    
    // Check if ward has associated patients or staff
    $hasAssociation = false;
    
    $staffInWard = executeQuery(
        "SELECT COUNT(*) as count FROM Staff WHERE ward_allocated = ?",
        [$wardNumber]
    );
    
    $patientsInWard = executeQuery(
        "SELECT COUNT(*) as count FROM Patient_Status WHERE required_ward = ?",
        [$wardNumber]
    );
    
    if ($staffInWard[0]['count'] > 0 || $patientsInWard[0]['count'] > 0) {
        $message = "Cannot delete ward because it has associated staff or patients.";
        $messageType = 'danger';
    } else {
        $result = executeNonQuery(
            "DELETE FROM Wards WHERE ward_number = ?",
            [$wardNumber]
        );
        
        if ($result) {
            $message = "Ward deleted successfully.";
            $messageType = 'success';
        } else {
            $message = "Failed to delete ward.";
            $messageType = 'danger';
        }
    }
}

// Handle ward editing
if (isset($_GET['edit'])) {
    $wardNumber = $_GET['edit'];
    $ward = getSingleRecord(
        "SELECT * FROM Wards WHERE ward_number = ?",
        [$wardNumber]
    );
}

// Get all wards
$wards = executeQuery("SELECT * FROM Wards ORDER BY ward_number");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Management - Wellmeadows Hospital</title>
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

    footer {
        background-color: var(--secondary-color);
        color: white;
        padding: 1rem 0;
        text-align: center;
        margin-top: 2rem;
    }
    </style>
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
        <h1>Ward Management</h1>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <?php echo $ward ? 'Edit Ward' : 'Add New Ward'; ?>
            </div>
            <form method="POST" action="wards.php">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="ward_number">Ward Number</label>
                            <input type="text" class="form-control" id="ward_number" name="ward_number"
                                value="<?php echo $ward ? $ward['ward_number'] : ''; ?>"
                                <?php echo $ward ? 'readonly' : ''; ?> required>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="ward_name">Ward Name</label>
                            <input type="text" class="form-control" id="ward_name" name="ward_name"
                                value="<?php echo $ward ? $ward['ward_name'] : ''; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" class="form-control" id="location" name="location"
                                value="<?php echo $ward ? $ward['location'] : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="total_beds">Total Beds</label>
                            <input type="number" class="form-control" id="total_beds" name="total_beds"
                                value="<?php echo $ward ? $ward['total_beds'] : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="telephone_extension">Telephone Extension</label>
                            <input type="text" class="form-control" id="telephone_extension" name="telephone_extension"
                                value="<?php echo $ward ? $ward['telephone_extension'] : ''; ?>" required>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <?php echo $ward ? 'Update Ward' : 'Add Ward'; ?>
                </button>

                <?php if ($ward): ?>
                <a href="wards.php" class="btn">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="card-header">All Wards</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Ward Number</th>
                            <th>Ward Name</th>
                            <th>Location</th>
                            <th>Total Beds</th>
                            <th>Tel. Extension</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($wards) > 0): ?>
                        <?php foreach ($wards as $ward): ?>
                        <tr>
                            <td><?php echo $ward['ward_number']; ?></td>
                            <td><?php echo $ward['ward_name']; ?></td>
                            <td><?php echo $ward['location']; ?></td>
                            <td><?php echo $ward['total_beds']; ?></td>
                            <td><?php echo $ward['telephone_extension']; ?></td>
                            <td>
                                <a href="wards.php?edit=<?php echo $ward['ward_number']; ?>" class="btn">Edit</a>
                                <a href="wards.php?delete=<?php echo $ward['ward_number']; ?>" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to delete this ward?')">Delete</a>
                                <a href="ward_staff.php?ward=<?php echo $ward['ward_number']; ?>" class="btn">View
                                    Staff</a>
                                <a href="ward_patients.php?ward=<?php echo $ward['ward_number']; ?>" class="btn">View
                                    Patients</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No wards found.</td>
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