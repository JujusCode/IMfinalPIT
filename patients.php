<?php
require_once 'db_connection.php';

// Initialize variables
$message = '';
$messageType = '';
$search = $_GET['search'] ?? '';
$searchParam = "%$search%";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_patient'])) {
        // Add new patient
        $result = executeNonQuery("
            INSERT INTO Patients (
                patient_number, first_name, last_name, address, telephone, 
                date_of_birth, sex, marital_status, date_registered, doctor_id
            ) VALUES (
                :patient_number, :first_name, :last_name, :address, :telephone, 
                :date_of_birth, :sex, :marital_status, :date_registered, :doctor_id
            )", [
                'patient_number' => $_POST['patient_number'],
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'address' => $_POST['address'],
                'telephone' => $_POST['telephone'],
                'date_of_birth' => $_POST['date_of_birth'],
                'sex' => $_POST['sex'],
                'marital_status' => $_POST['marital_status'],
                'date_registered' => date('Y-m-d'),
                'doctor_id' => $_POST['doctor_id'] ?: null
            ]);
    
        if ($result > 0) {
            // Insert Next-of-Kin details
            $kinResult = executeNonQuery("
            INSERT INTO Next_Of_Kin (
                patient_number, fullname, relationship, address, telephone
            ) VALUES (
                :patient_number, :fullname, :relationship, :address, :telephone
            )", [
                'patient_number' => $_POST['patient_number'],
                'fullname' => $_POST['kin_full_name'],
                'relationship' => $_POST['kin_relationship'],
                'address' => $_POST['kin_address'],
                'telephone' => $_POST['kin_telephone']
            ]);
        
    
            if ($kinResult > 0) {
                $message = "Patient and next-of-kin added successfully!";
                $messageType = 'success';
            } else {
                $message = "Patient added, but failed to add next-of-kin.";
                $messageType = 'warning';
            }
        } else {
            $message = "Error adding patient.";
            $messageType = 'danger';
        }
    }
}
// Handle deletion
if (isset($_GET['delete_patient'])) {
    $patientNumber = $_GET['delete_patient'];

    // Delete Next-of-Kin first to maintain foreign key constraints
    executeNonQuery("DELETE FROM Next_Of_Kin WHERE patient_number = :patient_number", [
        'patient_number' => $patientNumber
    ]);

    // Delete from Patient_Status if needed
    executeNonQuery("DELETE FROM Patient_Status WHERE patient_number = :patient_number", [
        'patient_number' => $patientNumber
    ]);

    // Then delete from Patients table
    $deleted = executeNonQuery("DELETE FROM Patients WHERE patient_number = :patient_number", [
        'patient_number' => $patientNumber
    ]);

    if ($deleted > 0) {
        $message = "Patient record deleted successfully.";
        $messageType = "success";
    } else {
        $message = "Failed to delete patient.";
        $messageType = "danger";
    }
}
    

// Get all patients with search functionality
$patientsQuery = "
    SELECT p.*, ps.is_outpatient, ld.fullname as doctor_name
    FROM Patients p
    LEFT JOIN Patient_Status ps ON p.patient_number = ps.patient_number
    LEFT JOIN Local_Doctors ld ON p.doctor_id = ld.doctor_id
    WHERE p.patient_number LIKE ? 
        OR p.first_name LIKE ? 
        OR p.last_name LIKE ?
        OR ld.fullname LIKE ?
    ORDER BY p.last_name, p.first_name
";

$patients = executeQuery($patientsQuery, [$searchParam, $searchParam, $searchParam, $searchParam]);

// Get local doctors for dropdown
$doctors = executeQuery("SELECT doctor_id, fullname FROM Local_Doctors ORDER BY fullname");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients - Wellmeadows Hospital</title>
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
        <h1>Patient Management</h1>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Add New Patient</div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="patient_number">Patient ID</label>
                        <input type="text" class="form-control" id="patient_number" name="patient_number" required>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Telephone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone">
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label for="sex">Gender</label>
                        <select class="form-control" id="sex" name="sex" required>
                            <option value="M">Male</option>
                            <option value="F">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="marital_status">Marital Status</label>
                        <input type="text" class="form-control" id="marital_status" name="marital_status">
                    </div>
                    <div class="form-group">
                        <label for="doctor_id">Local Doctor</label>
                        <select class="form-control" id="doctor_id" name="doctor_id">
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['doctor_id']; ?>">
                                <?php echo htmlspecialchars($doctor['fullname']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="card-header">Next-of-Kin Details</div>
                    <div class="form-group">
                        <label for="kin_full_name">Full Name</label>
                        <input type="text" class="form-control" id="kin_full_name" name="kin_full_name" required>
                    </div>
                    <div class="form-group">
                        <label for="kin_relationship">Relationship</label>
                        <input type="text" class="form-control" id="kin_relationship" name="kin_relationship" required>
                    </div>
                    <div class="form-group">
                        <label for="kin_address">Address</label>
                        <textarea class="form-control" id="kin_address" name="kin_address" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="kin_telephone">Telephone</label>
                        <input type="tel" class="form-control" id="kin_telephone" name="kin_telephone" required>
                    </div>

                    <button type="submit" name="add_patient" class="btn btn-success">Add Patient</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Patient List</div>
            <!-- Search Box -->
            <form method="GET" action="patients.php" class="search-box">
                <input type="text" name="search" placeholder="Search by name, patient ID or doctor..."
                    value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>DOB</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th>Doctor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No patients found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($patient['patient_number']); ?></td>
                            <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </td>
                            <td><?php echo date('d-M-Y', strtotime($patient['date_of_birth'])); ?></td>
                            <td><?php echo $patient['sex'] == 'M' ? 'Male' : 'Female'; ?></td>
                            <td>
                                <?php if ($patient['is_outpatient'] === null): ?>
                                Not assigned
                                <?php else: ?>
                                <?php echo $patient['is_outpatient'] ? 'Outpatient' : 'Inpatient'; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($patient['doctor_name'] ?? 'None'); ?></td>
                            <td>
                                <a href="patient_details.php?id=<?php echo $patient['patient_number']; ?>"
                                    class="btn">View</a>
                                <a href="edit_patient.php?id=<?php echo $patient['patient_number']; ?>"
                                    class="btn btn-warning">Edit</a>
                                <a href="?delete_patient=<?php echo $patient['patient_number']; ?>"
                                    class="btn btn-danger"
                                    onclick="return confirm('Are you sure you want to delete this patient?');">
                                    Delete
                                </a>
                            </td>

                            </td>
                        </tr>
                        <?php endforeach; ?>
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