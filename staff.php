<?php
// Include database connection
require_once 'db_connection.php';

// Initialize variables
$message = '';
$messageType = '';
$staff = null;
$positions = [];
$salary_scales = [];
$wards = [];

// Get all positions
$positions = executeQuery("SELECT * FROM Staff_Positions ORDER BY position_name");

// Get all salary scales
$salary_scales = executeQuery("SELECT * FROM Salary_Scales ORDER BY scale_id");

// Get all wards
$wards = executeQuery("SELECT * FROM Wards ORDER BY ward_number");

// Handle form submission for adding/updating a staff member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffNumber = $_POST['staff_number'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $address = $_POST['address'] ?? '';
    $telephone = $_POST['telephone'] ?? '';
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $nin = $_POST['nin'] ?? '';
    $positionId = $_POST['position_id'] ?? '';
    $currentSalary = $_POST['current_salary'] ?? 0;
    $salaryScaleId = $_POST['salary_scale_id'] ?? '';
    $wardAllocated = $_POST['ward_allocated'] ?? '';
    $hoursPerWeek = $_POST['hours_per_week'] ?? 0;
    $contractType = $_POST['contract_type'] ?? '';
    $paymentType = $_POST['payment_type'] ?? '';
    
    // Validate inputs
    if (empty($staffNumber) || empty($firstName) || empty($lastName) || empty($address) || 
        empty($dateOfBirth) || empty($sex) || empty($nin) || empty($positionId) || 
        empty($currentSalary) || empty($salaryScaleId) || empty($hoursPerWeek) || 
        empty($contractType) || empty($paymentType)) {
        $message = 'All required fields must be completed.';
        $messageType = 'danger';
    } else {
        // Check if staff exists
        $existingStaff = getSingleRecord(
            "SELECT * FROM Staff WHERE staff_number = ?", 
            [$staffNumber]
        );
        
        if ($existingStaff) {
            // Update existing staff
            $result = executeNonQuery(
                "UPDATE Staff SET 
                    first_name = ?, 
                    last_name = ?, 
                    address = ?, 
                    telephone = ?, 
                    date_of_birth = ?, 
                    sex = ?, 
                    nin = ?, 
                    position_id = ?, 
                    current_salary = ?, 
                    salary_scale_id = ?, 
                    ward_allocated = ?, 
                    hours_per_week = ?, 
                    contract_type = ?, 
                    payment_type = ? 
                WHERE staff_number = ?",
                [
                    $firstName, $lastName, $address, $telephone, $dateOfBirth, 
                    $sex, $nin, $positionId, $currentSalary, $salaryScaleId, 
                    $wardAllocated ?: null, $hoursPerWeek, $contractType, $paymentType, $staffNumber
                ]
            );
            
            if ($result) {
                $message = "Staff record updated successfully.";
                $messageType = 'success';
            } else {
                $message = "Failed to update staff record.";
                $messageType = 'danger';
            }
        } else {
            // Insert new staff
            $result = executeNonQuery(
                "INSERT INTO Staff (
                    staff_number, first_name, last_name, address, telephone, 
                    date_of_birth, sex, nin, position_id, current_salary, 
                    salary_scale_id, ward_allocated, hours_per_week, contract_type, payment_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $staffNumber, $firstName, $lastName, $address, $telephone, 
                    $dateOfBirth, $sex, $nin, $positionId, $currentSalary, 
                    $salaryScaleId, $wardAllocated ?: null, $hoursPerWeek, $contractType, $paymentType
                ]
            );
            
            if ($result) {
                $message = "Staff record added successfully.";
                $messageType = 'success';
            } else {
                $message = "Failed to add staff record.";
                $messageType = 'danger';
            }
        }
    }
}

// Handle staff deletion
if (isset($_GET['delete'])) {
    $staffNumber = $_GET['delete'];
    
    // Check if staff has associations that would prevent deletion
    $hasRota = executeQuery(
        "SELECT COUNT(*) as count FROM Staff_Rota WHERE staff_number = ?",
        [$staffNumber]
    );
    
    $hasAppointments = executeQuery(
        "SELECT COUNT(*) as count FROM Appointments WHERE staff_number = ?",
        [$staffNumber]
    );
    
    $hasRequisitions = executeQuery(
        "SELECT COUNT(*) as count FROM Requisitions WHERE staff_number = ? OR signed_by = ?",
        [$staffNumber, $staffNumber]
    );
    
    if ($hasRota[0]['count'] > 0 || $hasAppointments[0]['count'] > 0 || $hasRequisitions[0]['count'] > 0) {
        $message = "Cannot delete staff member because they have associated records in the system.";
        $messageType = 'danger';
    } else {
        // First, delete related records in Staff_Qualifications
        executeNonQuery(
            "DELETE FROM Staff_Qualifications WHERE staff_number = ?",
            [$staffNumber]
        );
        
        // Next, delete related records in Staff_Work_Experience
        executeNonQuery(
            "DELETE FROM Staff_Work_Experience WHERE staff_number = ?",
            [$staffNumber]
        );
        
        // Finally, delete the staff record
        $result = executeNonQuery(
            "DELETE FROM Staff WHERE staff_number = ?",
            [$staffNumber]
        );
        
        if ($result) {
            $message = "Staff record deleted successfully.";
            $messageType = 'success';
        } else {
            $message = "Failed to delete staff record.";
            $messageType = 'danger';
        }
    }
}

// Handle staff editing
if (isset($_GET['edit'])) {
    $staffNumber = $_GET['edit'];
    $staff = getSingleRecord(
        "SELECT * FROM Staff WHERE staff_number = ?",
        [$staffNumber]
    );
}

// Search functionality
$search = $_GET['search'] ?? '';
$searchParam = "%$search%";

// Get staff with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$staffQuery = "
    SELECT s.*, p.position_name, w.ward_name 
    FROM Staff s
    LEFT JOIN Staff_Positions p ON s.position_id = p.position_id
    LEFT JOIN Wards w ON s.ward_allocated = w.ward_number
    WHERE s.staff_number LIKE ? 
        OR s.first_name LIKE ? 
        OR s.last_name LIKE ?
        OR p.position_name LIKE ?
    ORDER BY s.last_name, s.first_name
    LIMIT ? OFFSET ?
";

$staff_members = executeQuery($staffQuery, [$searchParam, $searchParam, $searchParam, $searchParam, $perPage, $offset]);

// Count total staff for pagination
$totalStaffQuery = "
    SELECT COUNT(*) as total
    FROM Staff s
    LEFT JOIN Staff_Positions p ON s.position_id = p.position_id
    WHERE s.staff_number LIKE ? 
        OR s.first_name LIKE ? 
        OR s.last_name LIKE ?
        OR p.position_name LIKE ?
";

$totalStaff = executeQuery($totalStaffQuery, [$searchParam, $searchParam, $searchParam, $searchParam]);
$totalPages = ceil($totalStaff[0]['total'] / $perPage);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Wellmeadows Hospital</title>
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
        width: 1400px;
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

    .search-box {
        margin-bottom: 20px;
        display: flex;
    }

    .search-box input {
        flex: 1;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px 0 0 4px;
    }

    .search-box button {
        padding: 10px 15px;
        border: none;
        background-color: var(--primary-color);
        color: white;
        border-radius: 0 4px 4px 0;
        cursor: pointer;
    }

    .pagination {
        display: flex;
        justify-content: center;
        list-style: none;
        margin-top: 20px;
    }

    .pagination li {
        margin: 0 5px;
    }

    .pagination a {
        display: block;
        padding: 8px 12px;
        border: 1px solid #ddd;
        text-decoration: none;
        color: var(--primary-color);
        border-radius: 4px;
    }

    .pagination a:hover,
    .pagination .active a {
        background-color: var(--primary-color);
        color: white;
    }

    .pagination .disabled a {
        color: #aaa;
        pointer-events: none;
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
        <h1>Staff Management</h1>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <?php echo $staff ? 'Edit Staff Member' : 'Add New Staff Member'; ?>
            </div>
            <form method="POST" action="staff.php">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="staff_number">Staff Number*</label>
                            <input type="text" class="form-control" id="staff_number" name="staff_number"
                                value="<?php echo $staff ? $staff['staff_number'] : ''; ?>"
                                <?php echo $staff ? 'readonly' : ''; ?> required>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="first_name">First Name*</label>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                value="<?php echo $staff ? $staff['first_name'] : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="last_name">Last Name*</label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                value="<?php echo $staff ? $staff['last_name'] : ''; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="address">Address*</label>
                            <textarea class="form-control" id="address" name="address" rows="3"
                                required><?php echo $staff ? $staff['address'] : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="telephone">Telephone</label>
                            <input type="text" class="form-control" id="telephone" name="telephone"
                                value="<?php echo $staff ? $staff['telephone'] : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth*</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                value="<?php echo $staff ? $staff['date_of_birth'] : ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="sex">Sex*</label>
                            <select class="form-control" id="sex" name="sex" required>
                                <option value="">-- Select --</option>
                                <option value="M" <?php echo ($staff && $staff['sex'] == 'M') ? 'selected' : ''; ?>>Male
                                </option>
                                <option value="F" <?php echo ($staff && $staff['sex'] == 'F') ? 'selected' : ''; ?>>
                                    Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="nin">NIN (National Insurance Number)*</label>
                            <input type="text" class="form-control" id="nin" name="nin"
                                value="<?php echo $staff ? $staff['nin'] : ''; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="position_id">Position*</label>
                            <select class="form-control" id="position_id" name="position_id" required>
                                <option value="">-- Select Position --</option>
                                <?php foreach ($positions as $position): ?>
                                <option value="<?php echo $position['position_id']; ?>"
                                    <?php echo ($staff && $staff['position_id'] == $position['position_id']) ? 'selected' : ''; ?>>
                                    <?php echo $position['position_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="salary_scale_id">Salary Scale*</label>
                            <select class="form-control" id="salary_scale_id" name="salary_scale_id" required>
                                <option value="">-- Select Salary Scale --</option>
                                <?php foreach ($salary_scales as $scale): ?>
                                <option value="<?php echo $scale['scale_id']; ?>"
                                    <?php echo ($staff && $staff['salary_scale_id'] == $scale['scale_id']) ? 'selected' : ''; ?>>
                                    <?php echo $scale['scale_id'] . ' (£' . number_format($scale['min_salary'], 2) . ' - £' . number_format($scale['max_salary'], 2) . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="current_salary">Current Salary (£)*</label>
                            <input type="number" step="0.01" class="form-control" id="current_salary"
                                name="current_salary" value="<?php echo $staff ? $staff['current_salary'] : ''; ?>"
                                required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="ward_allocated">Allocated Ward</label>
                            <select class="form-control" id="ward_allocated" name="ward_allocated">
                                <option value="">-- None --</option>
                                <?php foreach ($wards as $ward): ?>
                                <option value="<?php echo $ward['ward_number']; ?>"
                                    <?php echo ($staff && $staff['ward_allocated'] == $ward['ward_number']) ? 'selected' : ''; ?>>
                                    <?php echo $ward['ward_number'] . ' - ' . $ward['ward_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="hours_per_week">Hours Per Week*</label>
                            <input type="number" step="0.5" class="form-control" id="hours_per_week"
                                name="hours_per_week" value="<?php echo $staff ? $staff['hours_per_week'] : ''; ?>"
                                required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="contract_type">Contract Type*</label>
                            <select class="form-control" id="contract_type" name="contract_type" required>
                                <option value="">-- Select --</option>
                                <option value="P"
                                    <?php echo ($staff && $staff['contract_type'] == 'P') ? 'selected' : ''; ?>>
                                    Permanent</option>
                                <option value="T"
                                    <?php echo ($staff && $staff['contract_type'] == 'T') ? 'selected' : ''; ?>>
                                    Temporary</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="payment_type">Payment Type*</label>
                            <select class="form-control" id="payment_type" name="payment_type" required>
                                <option value="">-- Select --</option>
                                <option value="W"
                                    <?php echo ($staff && $staff['payment_type'] == 'W') ? 'selected' : ''; ?>>Weekly
                                </option>
                                <option value="M"
                                    <?php echo ($staff && $staff['payment_type'] == 'M') ? 'selected' : ''; ?>>Monthly
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success">
                    <?php echo $staff ? 'Update Staff Member' : 'Add Staff Member'; ?>
                </button>

                <?php if ($staff): ?>
                <a href="staff.php" class="btn">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card">
            <div class="card-header">Staff Directory</div>

            <!-- Search Box -->
            <form method="GET" action="staff.php" class="search-box">
                <input type="text" name="search" placeholder="Search by name, staff number or position..."
                    value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Ward</th>
                            <th>Contract</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staff_members) > 0): ?>
                        <?php foreach ($staff_members as $member): ?>
                        <tr>
                            <td><?php echo $member['staff_number']; ?></td>
                            <td><?php echo $member['first_name'] . ' ' . $member['last_name']; ?></td>
                            <td><?php echo $member['position_name']; ?></td>
                            <td><?php echo $member['ward_name'] ? $member['ward_name'] : 'Not Assigned'; ?></td>
                            <td>
                                <?php 
                                            echo ($member['contract_type'] == 'P') ? 'Permanent' : 'Temporary';
                                            echo ', ';
                                            echo ($member['payment_type'] == 'W') ? 'Weekly' : 'Monthly';
                                        ?>
                            </td>
                            <td>
                                <a href="staff.php?edit=<?php echo $member['staff_number']; ?>" class="btn">Edit</a>
                                <a href="staff.php?delete=<?php echo $member['staff_number']; ?>" class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to delete this staff member?')">Delete</a>
                                <a href="staff_qualifications.php?id=<?php echo $member['staff_number']; ?>"
                                    class="btn">Qualifications</a>
                                <a href="staff_experience.php?id=<?php echo $member['staff_number']; ?>"
                                    class="btn">Experience</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No staff members found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <ul class="pagination">
                <li class="<?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a
                        href="<?php echo ($page <= 1) ? '#' : '?page=' . ($page - 1) . ($search ? '&search=' . urlencode($search) : ''); ?>">Previous</a>
                </li>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="<?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <li class="<?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a
                        href="<?php echo ($page >= $totalPages) ? '#' : '?page=' . ($page + 1) . ($search ? '&search=' . urlencode($search) : ''); ?>">Next</a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>