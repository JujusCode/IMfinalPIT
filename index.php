<?php
// Database connection function
function connectDB()
{
    // Database connection settings
    $host = "localhost";
    $dbname = "wellmeadows";
    $user = "postgres";
    $password = "bardinas123";

    // Establish database connection
    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Function to get dashboard summary data
function getDashboardSummary($pdo)
{
    $summary = [];

    // Get ward summary
    $wardQuery = $pdo->query("SELECT COUNT(*) as total_wards, SUM(total_beds) as total_beds FROM Wards");
    $wardSummary = $wardQuery->fetch(PDO::FETCH_ASSOC);
    $summary['wards'] = [
        'total_wards' => $wardSummary['total_wards'] ?? 0,
        'total_beds' => $wardSummary['total_beds'] ?? 0
    ];

    // Get available beds (calculated by subtracting occupied beds from total beds)
    $occupiedBedsQuery = $pdo->query("
        SELECT COUNT(*) as occupied_beds 
        FROM Patient_Status 
        WHERE is_outpatient = FALSE AND actual_leave_date IS NULL
    ");
    $occupiedBeds = $occupiedBedsQuery->fetch(PDO::FETCH_ASSOC)['occupied_beds'] ?? 0;
    $summary['wards']['available_beds'] = $summary['wards']['total_beds'] - $occupiedBeds;

    // Get staff summary
    $staffQuery = $pdo->query("
        SELECT COUNT(*) as total_staff FROM Staff
    ");
    $nurseQuery = $pdo->query("
        SELECT COUNT(*) as nurses FROM Staff s
        JOIN Staff_Positions sp ON s.position_id = sp.position_id
        WHERE sp.position_name IN ('Charge Nurse', 'Staff Nurse', 'Nurse')
    ");
    $doctorQuery = $pdo->query("
        SELECT COUNT(*) as doctors FROM Staff s
        JOIN Staff_Positions sp ON s.position_id = sp.position_id
        WHERE sp.position_name IN ('Medical Director', 'Consultant')
    ");

    $summary['staff'] = [
        'total_staff' => $staffQuery->fetch(PDO::FETCH_ASSOC)['total_staff'] ?? 0,
        'nurses' => $nurseQuery->fetch(PDO::FETCH_ASSOC)['nurses'] ?? 0,
        'doctors' => $doctorQuery->fetch(PDO::FETCH_ASSOC)['doctors'] ?? 0
    ];

    // Get patient summary
    $inpatientQuery = $pdo->query("
        SELECT COUNT(*) as inpatients 
        FROM Patient_Status 
        WHERE is_outpatient = FALSE AND actual_leave_date IS NULL
    ");
    $outpatientQuery = $pdo->query("
        SELECT COUNT(*) as outpatients 
        FROM Patient_Status 
        WHERE is_outpatient = TRUE
    ");
    $waitingListQuery = $pdo->query("
        SELECT COUNT(*) as waiting 
        FROM Patient_Status 
        WHERE waiting_list_date IS NOT NULL AND date_placed IS NULL
    ");

    $summary['patients'] = [
        'inpatients' => $inpatientQuery->fetch(PDO::FETCH_ASSOC)['inpatients'] ?? 0,
        'outpatients' => $outpatientQuery->fetch(PDO::FETCH_ASSOC)['outpatients'] ?? 0,
        'waiting_list' => $waitingListQuery->fetch(PDO::FETCH_ASSOC)['waiting'] ?? 0
    ];

    // Get appointment summary
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));

    $todayAppointmentsQuery = $pdo->prepare("
        SELECT COUNT(*) as today 
        FROM Appointments 
        WHERE DATE(date_time) = :today
    ");
    $todayAppointmentsQuery->execute(['today' => $today]);

    $weekAppointmentsQuery = $pdo->prepare("
        SELECT COUNT(*) as week 
        FROM Appointments 
        WHERE DATE(date_time) BETWEEN :start AND :end
    ");
    $weekAppointmentsQuery->execute(['start' => $weekStart, 'end' => $weekEnd]);

    $pendingAppointmentsQuery = $pdo->prepare("
        SELECT COUNT(*) as pending 
        FROM Appointments 
        WHERE DATE(date_time) >= :today
    ");
    $pendingAppointmentsQuery->execute(['today' => $today]);

    $summary['appointments'] = [
        'today' => $todayAppointmentsQuery->fetch(PDO::FETCH_ASSOC)['today'] ?? 0,
        'week' => $weekAppointmentsQuery->fetch(PDO::FETCH_ASSOC)['week'] ?? 0,
        'pending' => $pendingAppointmentsQuery->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0
    ];

    // Get supplies summary
    $pharmaQuery = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            SUM(quantity_in_stock) as total_quantity,
            SUM(CASE WHEN quantity_in_stock <= reorder_level THEN 1 ELSE 0 END) as low_stock_items
        FROM Pharmaceutical_Supplies
    ");
    $pharmaSummary = $pharmaQuery->fetch(PDO::FETCH_ASSOC);

    $surgicalQuery = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            SUM(quantity_in_stock) as total_quantity,
            SUM(CASE WHEN quantity_in_stock <= reorder_level THEN 1 ELSE 0 END) as low_stock_items
        FROM Surgical_Supplies
    ");
    $surgicalSummary = $surgicalQuery->fetch(PDO::FETCH_ASSOC);

    $summary['supplies'] = [
        'pharmaceutical' => [
            'total_items' => $pharmaSummary['total_items'] ?? 0,
            'total_quantity' => $pharmaSummary['total_quantity'] ?? 0,
            'low_stock' => $pharmaSummary['low_stock_items'] ?? 0
        ],
        'surgical' => [
            'total_items' => $surgicalSummary['total_items'] ?? 0,
            'total_quantity' => $surgicalSummary['total_quantity'] ?? 0,
            'low_stock' => $surgicalSummary['low_stock_items'] ?? 0
        ],
        'total_low_stock' => ($pharmaSummary['low_stock_items'] ?? 0) + ($surgicalSummary['low_stock_items'] ?? 0)
    ];

    return $summary;
}

// Get recent patient admissions data
function getRecentAdmissions($pdo)
{
    $query = $pdo->query("
        SELECT 
            p.patient_number,
            p.first_name,
            p.last_name,
            w.ward_number,
            w.ward_name,
            ps.date_placed as admission_date,
            ps.expected_leave_date
        FROM Patient_Status ps
        JOIN Patients p ON ps.patient_number = p.patient_number
        JOIN Wards w ON ps.required_ward = w.ward_number
        WHERE ps.is_outpatient = FALSE 
        AND ps.date_placed IS NOT NULL
        AND ps.actual_leave_date IS NULL
        ORDER BY ps.date_placed DESC
        LIMIT 4
    ");

    return $query->fetchAll(PDO::FETCH_ASSOC);
}

// Get dashboard data
$pdo = connectDB();
$dashboardSummary = getDashboardSummary($pdo);
$recentAdmissions = getRecentAdmissions($pdo);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Wellmeadows Hospital Management System</title>
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
            font-family: "Arial", sans-serif;
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

        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }

        .card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

        footer {
            background-color: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            text-align: center;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            header .container {
                flex-direction: column;
            }

            nav ul {
                margin-top: 1rem;
            }

            nav ul li {
                margin: 0 10px;
            }

            .dashboard {
                grid-template-columns: 1fr;
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
                    <li><a href="requisitions.php">Orders</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <h1>Welcome to Wellmeadows Hospital Management System</h1>
        <p>Select a module from the navigation menu above to get started.</p>

        <div class="dashboard">
            <div class="card">
                <div class="card-header">Ward Summary</div>
                <div class="card-body">
                    <p>Total Wards: <strong><?php echo $dashboardSummary['wards']['total_wards']; ?></strong></p>
                    <p>Total Beds: <strong><?php echo $dashboardSummary['wards']['total_beds']; ?></strong></p>
                    <p>Available Beds: <strong><?php echo $dashboardSummary['wards']['available_beds']; ?></strong></p>
                    <a href="wards.php" class="btn">View Details</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Staff Summary</div>
                <div class="card-body">
                    <p>Total Staff: <strong><?php echo $dashboardSummary['staff']['total_staff']; ?></strong></p>
                    <p>Nurses: <strong><?php echo $dashboardSummary['staff']['nurses']; ?></strong></p>
                    <p>Doctors: <strong><?php echo $dashboardSummary['staff']['doctors']; ?></strong></p>
                    <a href="staff.php" class="btn">View Details</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Patient Summary</div>
                <div class="card-body">
                    <p>In-patients: <strong><?php echo $dashboardSummary['patients']['inpatients']; ?></strong></p>
                    <p>Out-patients: <strong><?php echo $dashboardSummary['patients']['outpatients']; ?></strong></p>
                    <p>Waiting List: <strong><?php echo $dashboardSummary['patients']['waiting_list']; ?></strong></p>
                    <a href="patients.php" class="btn">View Details</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Appointments</div>
                <div class="card-body">
                    <p>Today: <strong><?php echo $dashboardSummary['appointments']['today']; ?></strong></p>
                    <p>This Week: <strong><?php echo $dashboardSummary['appointments']['week']; ?></strong></p>
                    <p>Pending: <strong><?php echo $dashboardSummary['appointments']['pending']; ?></strong></p>
                    <a href="appointments.php" class="btn">View Details</a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Supplies Summary</div>
                <div class="card-body">
                    <p>Pharmaceutical Items:
                        <strong><?php echo $dashboardSummary['supplies']['pharmaceutical']['total_items']; ?></strong>
                    </p>
                    <p>Surgical Items:
                        <strong><?php echo $dashboardSummary['supplies']['surgical']['total_items']; ?></strong>
                    </p>
                    <p>Total Low Stock Items: <strong
                            class="text-danger"><?php echo $dashboardSummary['supplies']['total_low_stock']; ?></strong>
                    </p>
                    <a href="supplies.php" class="btn">View Details</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Recent Patient Admissions</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Name</th>
                            <th>Ward</th>
                            <th>Admission Date</th>
                            <th>Expected Leave</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAdmissions)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No recent admissions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentAdmissions as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['patient_number']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </td>
                                    <td>Ward
                                        <?php echo htmlspecialchars($patient['ward_number'] . ' - ' . $patient['ward_name']); ?>
                                    </td>
                                    <td><?php echo date('d-M-y', strtotime($patient['admission_date'])); ?></td>
                                    <td><?php echo date('d-M-y', strtotime($patient['expected_leave_date'])); ?></td>
                                    <td>
                                        <a href="patient_details.php?id=<?php echo htmlspecialchars($patient['patient_number']); ?>"
                                            class="btn">View</a>
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
            <p>
                &copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.
            </p>
        </div>
    </footer>
</body>

</html>