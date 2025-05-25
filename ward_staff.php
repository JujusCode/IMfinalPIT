<?php
require_once 'db_connection.php';

// Get all wards with their staff details
$wardStaffReport = executeQuery("
    SELECT 
        w.ward_number,
        w.ward_name,
        w.location,
        w.telephone_extension as ward_tel,
        s.staff_number,
        s.first_name,
        s.last_name,
        s.address,
        s.telephone as staff_tel,
        sp.position_name,
        sr.shift_type,
        charge_nurse.first_name as charge_nurse_first_name,
        charge_nurse.last_name as charge_nurse_last_name
    FROM 
        Wards w
    LEFT JOIN 
        Staff s ON w.ward_number = s.ward_allocated
    LEFT JOIN 
        Staff_Positions sp ON s.position_id = sp.position_id
    LEFT JOIN 
        Staff_Rota sr ON s.staff_number = sr.staff_number AND w.ward_number = sr.ward_number
    LEFT JOIN 
        Staff charge_nurse ON w.ward_number = charge_nurse.ward_allocated 
        AND charge_nurse.position_id = (SELECT position_id FROM Staff_Positions WHERE position_name = 'Charge Nurse')
    WHERE 
        (sp.position_name IN ('Staff Nurse', 'Nurse', 'Charge Nurse') OR s.staff_number IS NULL)
    ORDER BY 
        w.ward_number, sp.position_name DESC, s.last_name, s.first_name
");

// Group the data by ward
$wardData = [];
foreach ($wardStaffReport as $row) {
    $wardNumber = $row['ward_number'];

    if (!isset($wardData[$wardNumber])) {
        $wardData[$wardNumber] = [
            'ward_info' => [
                'ward_number' => $row['ward_number'],
                'ward_name' => $row['ward_name'],
                'location' => $row['location'],
                'ward_tel' => $row['ward_tel'],
                'charge_nurse_name' => $row['charge_nurse_first_name'] . ' ' . $row['charge_nurse_last_name']
            ],
            'staff' => []
        ];
    }

    if ($row['staff_number']) {
        $wardData[$wardNumber]['staff'][] = [
            'staff_number' => $row['staff_number'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'address' => $row['address'],
            'staff_tel' => $row['staff_tel'],
            'position' => $row['position_name'],
            'shift' => $row['shift_type'] ?? 'Not Assigned'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Staff Report - Wellmeadows Hospital</title>
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

        .report-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .report-header h1 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .report-header .date {
            color: #666;
            font-size: 1.1rem;
        }

        .print-controls {
            text-align: center;
            margin-bottom: 20px;
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
            margin: 0 5px;
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

        .ward-section {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .ward-header {
            background-color: var(--secondary-color);
            color: white;
            padding: 15px 20px;
        }

        .ward-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .ward-info-item {
            font-size: 0.9rem;
        }

        .ward-info-item strong {
            display: block;
            margin-bottom: 2px;
        }

        .staff-table {
            width: 100%;
            border-collapse: collapse;
        }

        .staff-table th,
        .staff-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .staff-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }

        .staff-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .staff-table tr:hover {
            background-color: #e9ecef;
        }

        .no-staff {
            padding: 20px;
            text-align: center;
            color: #666;
            font-style: italic;
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
            body {
                background-color: white;
                font-size: 12px;
            }

            header,
            footer,
            .print-controls {
                display: none !important;
            }

            main {
                padding: 0;
            }

            .container {
                width: 100%;
                max-width: none;
                padding: 0;
            }

            .ward-section {
                page-break-inside: avoid;
                margin-bottom: 20px;
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .ward-header {
                background-color: #f0f0f0 !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
            }

            .staff-table th {
                background-color: #f0f0f0 !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
            }

            .report-header {
                box-shadow: none;
                border-bottom: 2px solid #ddd;
            }
        }

        @page {
            margin: 1in;
        }
    </style>
</head>

<body>
    <header class="no-print">
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
        <div class="report-header">
            <h1>Ward Staff Allocation Report</h1>
            <div class="date">Generated on: <?php echo date('F j, Y \a\t g:i A'); ?></div>
            <div class="date">For Personnel Officer and Charge Nurse</div>
        </div>

        <div class="print-controls no-print">
            <button onclick="window.print()" class="btn btn-success">🖨️ Print Report</button>
            <a href="wards.php" class="btn">← Back to Wards</a>
        </div>

        <?php if (empty($wardData)): ?>
            <div class="ward-section">
                <div class="no-staff">No ward data available.</div>
            </div>
        <?php else: ?>
            <?php foreach ($wardData as $wardNumber => $data): ?>
                <div class="ward-section">
                    <div class="ward-header">
                        <h2>Ward <?php echo htmlspecialchars($data['ward_info']['ward_number']); ?> -
                            <?php echo htmlspecialchars($data['ward_info']['ward_name']); ?></h2>
                        <div class="ward-info">
                            <div class="ward-info-item">
                                <strong>Charge Nurse:</strong>
                                <?php echo htmlspecialchars($data['ward_info']['charge_nurse_name'] ?: 'Not Assigned'); ?>
                            </div>
                            <div class="ward-info-item">
                                <strong>Location:</strong>
                                <?php echo htmlspecialchars($data['ward_info']['location']); ?>
                            </div>
                            <div class="ward-info-item">
                                <strong>Ward Telephone:</strong>
                                <?php echo htmlspecialchars($data['ward_info']['ward_tel']); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($data['staff'])): ?>
                        <div class="no-staff">No staff currently allocated to this ward.</div>
                    <?php else: ?>
                        <table class="staff-table">
                            <thead>
                                <tr>
                                    <th>Staff No.</th>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th>Tel No.</th>
                                    <th>Position</th>
                                    <th>Shift</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['staff'] as $staff): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff['staff_number']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['address'] ?: 'Not Available'); ?></td>
                                        <td><?php echo htmlspecialchars($staff['staff_tel'] ?: 'Not Available'); ?></td>
                                        <td><?php echo htmlspecialchars($staff['position']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['shift']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="report-header" style="margin-top: 30px;">
            <div class="date">
                <strong>Report Summary:</strong>
                Total Wards: <?php echo count($wardData); ?> |
                Total Staff Listed:
                <?php echo array_sum(array_map(function ($ward) {
                    return count($ward['staff']); }, $wardData)); ?>
            </div>
        </div>
    </main>

    <footer class="no-print">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Print functionality
        function printReport() {
            window.print();
        }

        // Add keyboard shortcut for printing
        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                printReport();
            }
        });
    </script>
</body>

</html>