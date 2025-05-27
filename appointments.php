<?php
require_once 'db_connection.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_appointment'])) {
        $result = executeNonQuery("
            INSERT INTO Appointments (
                appointment_number, patient_number, staff_number, 
                date_time, examination_room
            ) VALUES (
                :appointment_number, :patient_number, :staff_number, 
                :date_time, :examination_room
            )", [
            'appointment_number' => $_POST['appointment_number'],
            'patient_number' => $_POST['patient_number'],
            'staff_number' => $_POST['staff_number'],
            'date_time' => $_POST['date'] . ' ' . $_POST['time'],
            'examination_room' => $_POST['examination_room']
        ]);

        if ($result > 0) {
            $success = "Appointment scheduled successfully!";
        } else {
            $error = "Error scheduling appointment";
        }
    }
}

// Get all appointments
$appointments = executeQuery("
    SELECT a.*, p.first_name as patient_first, p.last_name as patient_last,
           s.first_name as staff_first, s.last_name as staff_last
    FROM Appointments a
    JOIN Patients p ON a.patient_number = p.patient_number
    JOIN Staff s ON a.staff_number = s.staff_number
    ORDER BY a.date_time DESC
");

// Get patients and staff for dropdowns
$patients = executeQuery("SELECT patient_number, first_name, last_name FROM Patients ORDER BY last_name, first_name");
$doctors = executeQuery("
    SELECT s.staff_number, s.first_name, s.last_name 
    FROM Staff s
    JOIN Staff_Positions sp ON s.position_id = sp.position_id
    WHERE sp.position_name IN ('Medical Director', 'Consultant')
    ORDER BY s.last_name, s.first_name
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Wellmeadows Hospital</title>
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
        <h1>Appointment Management</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Schedule New Appointment</div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="appointment_number">Appointment ID</label>
                        <input type="text" class="form-control" id="appointment_number" name="appointment_number"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="patient_number">Patient</label>
                        <select class="form-control" id="patient_number" name="patient_number" required>
                            <option value="">-- Select Patient --</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patient_number']; ?>">
                                    <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="staff_number">Doctor</label>
                        <select class="form-control" id="staff_number" name="staff_number" required>
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['staff_number']; ?>">
                                    <?php echo htmlspecialchars($doctor['last_name'] . ', ' . $doctor['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" class="form-control" id="date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="time">Time</label>
                        <input type="time" class="form-control" id="time" name="time" required>
                    </div>
                    <div class="form-group">
                        <label for="examination_room">Examination Room</label>
                        <input type="text" class="form-control" id="examination_room" name="examination_room" required>
                    </div>
                    <button type="submit" name="add_appointment" class="btn btn-success">Schedule Appointment</button>
                    <a href="out_patients_report.php" class="btn">View out-patients list</a>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Upcoming Appointments</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Date & Time</th>
                            <th>Room</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No appointments found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($appointment['appointment_number']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['patient_last'] . ', ' . $appointment['patient_first']); ?>
                                    </td>
                                    <td>Dr.
                                        <?php echo htmlspecialchars($appointment['staff_last'] . ', ' . $appointment['staff_first']); ?>
                                    </td>
                                    <td><?php echo date('d-M-Y H:i', strtotime($appointment['date_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['examination_room']); ?></td>
                                    <td>
                                        <a href="appointment_details.php?id=<?php echo $appointment['appointment_number']; ?>"
                                            class="btn">View</a>
                                        <a href="cancel_appointment.php?id=<?php echo $appointment['appointment_number']; ?>"
                                            class="btn btn-danger">Cancel</a>
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