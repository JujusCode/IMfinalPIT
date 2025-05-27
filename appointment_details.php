<?php
require_once 'db_connection.php';

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? '';

// Debug: Let's see what we're getting
error_log("Appointment ID received: " . $appointment_id);

if (empty($appointment_id)) {
    error_log("No appointment ID provided, redirecting to appointments.php");
    header('Location: appointments.php');
    exit;
}

// First, let's check if the appointment exists at all
$simple_check = executeQuery("SELECT appointment_number FROM Appointments WHERE appointment_number = :appointment_id", ['appointment_id' => $appointment_id]);
error_log("Simple appointment check result: " . print_r($simple_check, true));

// Initialize variables
$appointment = null;
$error_message = null;
$debug_info = null;

// Get appointment details
$appointment_result = executeQuery("
    SELECT a.*, 
           p.first_name as patient_first, p.last_name as patient_last,
           p.address, p.telephone as telephone_number, p.date_of_birth, p.sex,
           s.first_name as staff_first, s.last_name as staff_last
    FROM Appointments a
    JOIN Patients p ON a.patient_number = p.patient_number
    JOIN Staff s ON a.staff_number = s.staff_number
    WHERE a.appointment_number = :appointment_id
", ['appointment_id' => $appointment_id]);

error_log("Full appointment query result: " . print_r($appointment_result, true));

if (empty($appointment_result)) {
    error_log("No appointment found for ID: " . $appointment_id);

    // Let's also check what appointments exist
    $all_appointments = executeQuery("SELECT appointment_number FROM Appointments LIMIT 10");
    error_log("Available appointments: " . print_r($all_appointments, true));

    $error_message = "Appointment not found with ID: " . htmlspecialchars($appointment_id);
    $debug_info = [
        'simple_check' => $simple_check,
        'all_appointments' => $all_appointments
    ];
} else {
    $appointment = $appointment_result[0];
}

// Check if user has permission to view out-patient report (Charge Nurse or Medical Director)
$can_view_report = true; // This would be determined by user role
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - Wellmeadows Hospital</title>
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

        .card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-weight: bold;
            color: var(--secondary-color);
            font-size: 1.2rem;
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

        .btn-danger {
            background-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .appointment-status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: bold;
            text-align: center;
            max-width: 120px;
        }

        .status-scheduled {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-completed {
            background-color: #e8f5e8;
            color: #2e7d32;
        }

        .status-cancelled {
            background-color: #ffebee;
            color: #c62828;
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

        .action-buttons {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .report-access-section {
            margin-top: 30px;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            border-left: 4px solid var(--info-color);
        }

        .report-access-section h3 {
            color: var(--info-color);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .report-access-section p {
            margin-bottom: 15px;
            color: #666;
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
            .report-access-section,
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
        <h1>Appointment Details</h1>

        <?php if (isset($error_message)): ?>
            <div class="card">
                <div class="card-header">Error</div>
                <p style="color: red; padding: 20px;"><?php echo $error_message; ?></p>
                <p style="padding: 0 20px 20px;">
                    <strong>Debug Info:</strong><br>
                    Requested ID: <?php echo htmlspecialchars($appointment_id); ?><br>
                    Simple check result:
                    <?php echo empty($simple_check) ? 'No appointment found' : 'Appointment exists'; ?><br>
                    Available appointments:
                    <?php
                    if (!empty($debug_info['all_appointments'])) {
                        foreach ($debug_info['all_appointments'] as $apt) {
                            echo htmlspecialchars($apt['appointment_number']) . ', ';
                        }
                    } else {
                        echo 'None found';
                    }
                    ?>
                </p>
                <div style="padding: 0 20px 20px;">
                    <a href="appointments.php" class="btn btn-secondary">← Back to Appointments</a>
                </div>
            </div>
        <?php else: ?>

            <div class="card">
                <div class="card-header">Appointment Information</div>

                <div class="detail-grid">
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Appointment ID:</span>
                            <span
                                class="detail-value"><?php echo htmlspecialchars($appointment['appointment_number']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date & Time:</span>
                            <span
                                class="detail-value"><?php echo date('l, F j, Y \a\t g:i A', strtotime($appointment['date_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Examination Room:</span>
                            <span
                                class="detail-value"><?php echo htmlspecialchars($appointment['examination_room']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">
                                <span class="appointment-status status-scheduled">Scheduled</span>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Patient Name:</span>
                            <span
                                class="detail-value"><?php echo htmlspecialchars($appointment['patient_first'] . ' ' . $appointment['patient_last']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Patient Number:</span>
                            <span
                                class="detail-value"><?php echo htmlspecialchars($appointment['patient_number']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date of Birth:</span>
                            <span
                                class="detail-value"><?php echo date('F j, Y', strtotime($appointment['date_of_birth'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Gender:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($appointment['sex']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="info-box">
                    <h4>Contact Information</h4>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($appointment['address']); ?></p>
                    <p><strong>Telephone:</strong> <?php echo htmlspecialchars($appointment['telephone_number']); ?></p>
                </div>

                <div class="info-box">
                    <h4>Attending Physician</h4>
                    <p><strong>Doctor:</strong> Dr.
                        <?php echo htmlspecialchars($appointment['staff_first'] . ' ' . $appointment['staff_last']); ?>
                    </p>
                    <p><strong>Staff Number:</strong> <?php echo htmlspecialchars($appointment['staff_number']); ?></p>
                </div>

                <div class="action-buttons">
                    <a href="appointments.php" class="btn btn-secondary">← Back to Appointments</a>
                    <a href="edit_appointment.php?id=<?php echo $appointment['appointment_number']; ?>"
                        class="btn btn-info">Edit Appointment</a>
                    <a href="cancel_appointment.php?id=<?php echo $appointment['appointment_number']; ?>"
                        class="btn btn-danger">Cancel Appointment</a>
                    <button onclick="window.print()" class="btn btn-success">Print Details</button>
                </div>
            </div>

            <?php if ($can_view_report): ?>
                <div class="report-access-section">
                    <h3>Out-Patients Clinic Report</h3>
                    <p>Access the comprehensive out-patients clinic report. This report lists all patients referred to the
                        out-patients clinic with their personal details and appointment information.</p>
                    <p><em>Note: This report is restricted to Charge Nurses and Medical Directors only.</em></p>
                    <a href="out_patients_report.php" class="btn btn-info">View Out-Patients Report</a>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Add some basic interactivity
        document.addEventListener('DOMContentLoaded', function () {
            // Confirm before cancelling appointment
            const cancelBtn = document.querySelector('.btn-danger');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function (e) {
                    if (!confirm('Are you sure you want to cancel this appointment?')) {
                        e.preventDefault();
                    }
                });
            }

            // Auto-hide success/error messages after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                setTimeout(function () {
                    alert.style.opacity = '0';
                    setTimeout(function () {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>

</html>