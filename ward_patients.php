<?php
require_once 'db_connection.php';

$message = '';
$messageType = '';

$wardNumber = $_GET['ward'] ?? null;

if (!$wardNumber) {
    die("Ward number is required.");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_new_patient') {
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $address = $_POST['address'] ?? 'Default Address';
        $dateOfBirth = $_POST['date_of_birth'] ?? date('Y-m-d');
        $sex = $_POST['sex'] ?? 'M';
        $dateRegistered = date('Y-m-d');
        $admissionDate = $_POST['admission_date'] ?? date('Y-m-d');
        $expectedLeaveDate = $_POST['expected_leave_date'] ?? '';
        $bedNumber = $_POST['bed_number'] ?? null;

        $patientNumber = 'P' . date('ymd') . rand(1000, 9999);

        if (empty($firstName) || empty($lastName)) {
            $message = "Patient name is required.";
            $messageType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();

                // Insert patient
                $patientResult = executeNonQuery(
                    "INSERT INTO Patients (
                        patient_number, first_name, last_name, address, 
                        date_of_birth, sex, date_registered
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $patientNumber,
                        $firstName,
                        $lastName,
                        $address,
                        $dateOfBirth,
                        $sex,
                        $dateRegistered
                    ]
                );

                if ($patientResult) {
                    // Only insert Patient_Status if wardNumber is set
                    if (!empty($wardNumber)) {
                        $statusResult = executeNonQuery(
                            "INSERT INTO Patient_Status 
                            (patient_number, required_ward, date_placed, expected_leave_date, is_outpatient, bed_number) 
                            VALUES (?, ?, ?, ?, FALSE, ?)",
                            [$patientNumber, $wardNumber, $admissionDate, $expectedLeaveDate, $bedNumber]
                        );

                        if (!$statusResult) {
                            throw new Exception("Failed to add patient status.");
                        }
                    }

                    $pdo->commit();
                    $message = "Patient added successfully.";
                    $messageType = 'success';
                } else {
                    throw new Exception("Failed to add patient.");
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
        }


    } elseif ($action === 'assign_existing_patient') {
        $patientNumber = $_POST['patient_number'] ?? '';
        $admissionDate = $_POST['admission_date'] ?? date('Y-m-d');
        $expectedLeaveDate = $_POST['expected_leave_date'] ?? null;
        $bedNumber = $_POST['bed_number'] ?? null;

        if (empty($patientNumber)) {
            $message = "Please select a patient.";
            $messageType = 'danger';
        } else {
            try {
                $existingStatus = getSingleRecord(
                    "SELECT * FROM Patient_Status WHERE patient_number = ?",
                    [$patientNumber]
                );

                if ($existingStatus) {
                    $result = executeNonQuery(
                        "UPDATE Patient_Status 
                         SET required_ward = ?, date_placed = ?, expected_leave_date = ?, is_outpatient = FALSE, actual_leave_date = NULL, bed_number = ?
                         WHERE patient_number = ?",
                        [$wardNumber, $admissionDate, $expectedLeaveDate, $bedNumber, $patientNumber]
                    );
                } else {
                    $result = executeNonQuery(
                        "INSERT INTO Patient_Status (patient_number, required_ward, date_placed, expected_leave_date, is_outpatient, bed_number)
                         VALUES (?, ?, ?, ?, FALSE, ?)",
                        [$patientNumber, $wardNumber, $admissionDate, $expectedLeaveDate, $bedNumber]
                    );
                }

                if ($result) {
                    $message = "Patient assigned to ward successfully.";
                    $messageType = 'success';
                } else {
                    $message = "Failed to assign patient to ward.";
                    $messageType = 'danger';
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Fetch ward details
$ward = getSingleRecord("SELECT * FROM Wards WHERE ward_number = ?", [$wardNumber]);

// Fetch patients assigned to this ward with additional details for report
$patients = executeQuery(
    "SELECT 
        p.patient_number,
        p.first_name,
        p.last_name,
        p.address,
        p.date_of_birth,
        p.sex,
        ps.date_placed as admission_date,
        ps.expected_leave_date,
        ps.expected_duration,
        ps.bed_number,
        CASE 
            WHEN ps.is_outpatient = TRUE THEN 'Out-patient'
            ELSE 'In-patient'
        END as patient_status,
        CURRENT_DATE - ps.date_placed as days_in_ward
     FROM 
        Patient_Status ps
     JOIN 
        Patients p ON ps.patient_number = p.patient_number
     WHERE 
        ps.required_ward = ? 
        AND ps.is_outpatient = FALSE
        AND (ps.actual_leave_date IS NULL OR ps.actual_leave_date > CURRENT_DATE)
     ORDER BY ps.date_placed DESC",
    [$wardNumber]
);

// Fetch all patients for dropdown
$allPatients = executeQuery(
    "SELECT p.patient_number, p.first_name, p.last_name
     FROM Patients p
     WHERE p.patient_number NOT IN (
         SELECT ps.patient_number
         FROM Patient_Status ps
         WHERE ps.is_outpatient = FALSE
           AND (ps.actual_leave_date IS NULL OR ps.actual_leave_date > CURRENT_DATE)
     )
     ORDER BY p.last_name, p.first_name"
);

// Get charge nurse details for the ward
$chargeNurse = getSingleRecord(
    "SELECT s.first_name, s.last_name, s.staff_number 
     FROM Staff s 
     JOIN Staff_Positions sp ON s.position_id = sp.position_id 
     WHERE s.ward_allocated = ? AND sp.position_name = 'Charge Nurse'
     LIMIT 1",
    [$wardNumber]
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Patients - Wellmeadows Hospital</title>
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
            width: 1500px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            margin: 2px;
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

        .btn-warning {
            background-color: var(--warning-color);
        }

        .btn-warning:hover {
            background-color: #e67e22;
        }

        .btn-print {
            background-color: #6c757d;
        }

        .btn-print:hover {
            background-color: #5a6268;
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

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }

            .print-section,
            .print-section * {
                visibility: visible;
            }

            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            header,
            nav,
            footer,
            .btn,
            .card:not(.print-section) {
                display: none !important;
            }

            .print-header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
            }

            .print-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
            }

            table {
                font-size: 12px;
            }

            th,
            td {
                padding: 8px;
            }
        }

        .report-header {
            display: none;
        }

        .print-section .report-header {
            display: block;
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
        <h1>Patients in <?= htmlspecialchars($ward['ward_name'] ?? 'Unknown Ward') ?></h1>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Ward Information</div>
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label>Ward Number</label>
                        <p><?= htmlspecialchars($ward['ward_number'] ?? 'N/A') ?></p>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label>Location</label>
                        <p><?= htmlspecialchars($ward['location'] ?? 'N/A') ?></p>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label>Total Beds</label>
                        <p><?= htmlspecialchars($ward['total_beds'] ?? 'N/A') ?></p>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label>Telephone Extension</label>
                        <p><?= htmlspecialchars($ward['telephone_extension'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card print-section">
            <div class="card-header">
                Current Patients Report
                <button onclick="printReport()" class="btn btn-print">🖨️ Print Report</button>
            </div>

            <!-- Print Header (only visible when printing) -->
            <div class="report-header">
                <div class="print-header">
                    <h2>Wellmeadows Hospital</h2>
                    <h3>Patient Allocation Report</h3>
                    <p>Ward: <?= htmlspecialchars($ward['ward_name'] ?? 'Unknown Ward') ?>
                        (<?= htmlspecialchars($ward['ward_number'] ?? 'N/A') ?>)</p>
                </div>
                <div class="print-info">
                    <div>
                        <strong>Report Date:</strong> <?= date('d-M-y') ?><br>
                        <strong>Ward Location:</strong> <?= htmlspecialchars($ward['location'] ?? 'N/A') ?><br>
                        <strong>Total Beds:</strong> <?= htmlspecialchars($ward['total_beds'] ?? 'N/A') ?>
                    </div>
                    <div>
                        <strong>Charge Nurse:</strong>
                        <?= htmlspecialchars(($chargeNurse['first_name'] ?? '') . ' ' . ($chargeNurse['last_name'] ?? 'Not Assigned')) ?><br>
                        <strong>Staff Number:</strong>
                        <?= htmlspecialchars($chargeNurse['staff_number'] ?? 'N/A') ?><br>
                        <strong>Tel Ext:</strong> <?= htmlspecialchars($ward['telephone_extension'] ?? 'N/A') ?>
                    </div>
                </div>
            </div>

            <?php if (count($patients) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient Number</th>
                                <th>Name</th>
                                <th>On Waiting List</th>
                                <th>Expected Stay (Days)</th>
                                <th>Date Placed</th>
                                <th>Date Leave</th>
                                <th>Actual Leave</th>
                                <th>Bed Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <?php
                                // Calculate if patient is on waiting list
                                $onWaitingList = isset($patient['waiting_list_date']) && $patient['waiting_list_date'] ? 'Yes' : 'No';

                                // Format dates
                                $datePlaced = isset($patient['admission_date']) ? date('d-M-y', strtotime($patient['admission_date'])) : 'N/A';
                                $expectedLeave = isset($patient['expected_leave_date']) ? date('d-M-y', strtotime($patient['expected_leave_date'])) : 'Not Set';
                                $actualLeave = isset($patient['actual_leave_date']) && $patient['actual_leave_date'] ? date('d-M-y', strtotime($patient['actual_leave_date'])) : 'N/A';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($patient['patient_number']) ?></td>
                                    <td><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></td>
                                    <td><?= $onWaitingList ?></td>
                                    <td><?= htmlspecialchars($patient['expected_duration'] ?? 'N/A') ?></td>
                                    <td><?= $datePlaced ?></td>
                                    <td><?= $expectedLeave ?></td>
                                    <td><?= $actualLeave ?></td>
                                    <td><?= htmlspecialchars($patient['bed_number'] ?? 'Not Assigned') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="report-footer" style="margin-top: 20px; display: none;">
                        <p><strong>Total Patients:</strong> <?= count($patients) ?></p>
                        <p><strong>Report Generated:</strong> <?= date('d-M-Y H:i:s') ?></p>
                        <p><strong>Generated by:</strong> Medical Director</p>
                    </div>
                </div>
            <?php else: ?>
                <p>No patients currently in this ward.</p>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">Assign Existing Patient to Ward</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="assign_existing_patient" />
                        <div class="form-group">
                            <label for="patient_number">Select Patient*</label>
                            <select name="patient_number" id="patient_number" class="form-control" required>
                                <option value="">-- Select Patient --</option>
                                <?php foreach ($allPatients as $p): ?>
                                    <option value="<?= htmlspecialchars($p['patient_number']) ?>">
                                        <?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="admission_date_existing">Admission Date</label>
                            <input type="date" name="admission_date" id="admission_date_existing" class="form-control"
                                value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label for="bed_number">Bed Number</label>
                            <input type="text" name="bed_number" id="bed_number" class="form-control"
                                placeholder="e.g., B12">
                        </div>
                        <div class="form-group">
                            <label for="expected_leave_date_existing">Expected Leave Date</label>
                            <input type="date" name="expected_leave_date" id="expected_leave_date_existing"
                                class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary">Assign Patient to Ward</button>
                    </form>
                </div>
            </div>

    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function printReport() {
            // Show report footer for print
            const reportFooter = document.querySelector('.report-footer');
            if (reportFooter) {
                reportFooter.style.display = 'block';
            }

            // Show report header for print
            const reportHeader = document.querySelector('.report-header');
            if (reportHeader) {
                reportHeader.style.display = 'block';
            }

            // Print the page
            window.print();

            // Restore elements after print
            setTimeout(() => {
                if (reportFooter) {
                    reportFooter.style.display = 'none';
                }
            }, 1000);
        }

        // Handle print dialog cancel
        window.addEventListener('afterprint', function () {
            const reportFooter = document.querySelector('.report-footer');
            if (reportFooter) {
                reportFooter.style.display = 'none';
            }
        });
    </script>
</body>

</html>