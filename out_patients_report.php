<?php
require_once 'db_connection.php';

// Check if user has permission to view out-patient report (Charge Nurse or Medical Director)
// In a real application, you would check user roles/permissions here
$can_view_report = true; // This would be determined by user role

if (!$can_view_report) {
    header('Location: appointments.php');
    exit;
}

// Get out-patients report data
$outpatients_report = executeQuery("
    SELECT DISTINCT
        p.patient_number,
        p.first_name,
        p.last_name,
        p.address,
        p.telephone as telephone_number,
        p.date_of_birth,
        p.sex,
        a.date_time as appointment_datetime
    FROM Patients p
    JOIN Patient_Status ps ON p.patient_number = ps.patient_number
    JOIN Appointments a ON p.patient_number = a.patient_number
    WHERE ps.is_outpatient = true
    ORDER BY a.date_time DESC, p.last_name, p.first_name
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Out-Patients Clinic Report - Wellmeadows Hospital</title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --info-color: #17a2b8;
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

        .report-header {
            background-color: var(--info-color);
            color: white;
            padding: 15px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 0;
        }

        .report-content {
            background-color: white;
            border: 1px solid var(--info-color);
            border-top: none;
            border-radius: 0 0 5px 5px;
            padding: 20px;
        }

        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid var(--info-color);
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box h4 {
            color: var(--info-color);
            margin-bottom: 10px;
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
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .btn:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
        }

        .btn-secondary:hover {
            background-color: #1a252f;
        }

        .btn-info {
            background-color: var(--info-color);
        }

        .btn-info:hover {
            background-color: #138496;
        }

        .btn-success {
            background-color: var(--success-color);
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-label {
            font-weight: bold;
            min-width: 140px;
            color: var(--secondary-color);
        }

        .detail-value {
            flex: 1;
            color: #555;
        }

        .patient-card {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary-color);
        }

        .patient-name {
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }

        .patient-details {
            font-size: 0.9rem;
            color: #666;
        }

        .action-buttons {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        footer {
            background-color: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            text-align: center;
            margin-top: 2rem;
        }

        @media print {

            header,
            footer,
            .action-buttons,
            .btn {
                display: none;
            }

            body {
                background-color: white;
            }

            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
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
        <h1>Out-Patients Clinic Report</h1>

        <div class="report-header">
            <h2>Out-Patients Clinic Report</h2>
            <p>Report for Charge Nurse and Medical Director - Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        </div>

        <div class="report-content">
            <div class="info-box">
                <h4>Report Information</h4>
                <p>This report lists all patients referred to the out-patients clinic, including their personal details
                    and appointment information. This report is restricted to Charge Nurses and Medical Directors only.
                </p>
                <p><strong>Total Out-Patients:</strong> <?php echo count($outpatients_report); ?></p>
            </div>

            <?php if (empty($outpatients_report)): ?>
                <div class="patient-card">
                    <div class="patient-name">No Out-Patients Found</div>
                    <div class="patient-details">There are currently no patients referred to the out-patients clinic.</div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient Number</th>
                                <th>Name</th>
                                <th>Date of Birth</th>
                                <th>Gender</th>
                                <th>Address</th>
                                <th>Telephone</th>
                                <th>Last Appointment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outpatients_report as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['patient_number']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?></td>
                                    <td><?php echo htmlspecialchars($patient['sex']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['address']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['telephone_number']); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($patient['appointment_datetime'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 20px;">
                    <h4>Summary by Gender</h4>
                    <?php
                    $gender_summary = [];
                    foreach ($outpatients_report as $patient) {
                        $gender = $patient['sex'];
                        $gender_summary[$gender] = ($gender_summary[$gender] ?? 0) + 1;
                    }
                    ?>
                    <div class="detail-grid">
                        <?php foreach ($gender_summary as $gender => $count): ?>
                            <div class="detail-item">
                                <span class="detail-label"><?php echo htmlspecialchars($gender); ?>:</span>
                                <span class="detail-value"><?php echo $count; ?> patient(s)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="appointments.php" class="btn btn-secondary">← Back to Appointments</a>
                <button onclick="window.print()" class="btn btn-success">Print Report</button>
                <a href="export_outpatients.php" class="btn btn-info">Export to CSV</a>
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