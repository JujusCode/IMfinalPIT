<?php
// Include database connection
require_once 'db_connection.php';

// Initialize ward filter before using it
$wardFilter = null;
if (isset($_GET['ward']) && !empty($_GET['ward'])) {
    $wardFilter = $_GET['ward'];
}

// Build base SQL with optional WHERE clause if wardFilter exists
$sql = "
    SELECT
        s.staff_number AS \"Staff No\",
        CONCAT(s.first_name, ' ', s.last_name) AS \"Name\",
        s.address AS \"Address\",
        s.telephone AS \"Tel No\",
        p.position_name AS \"Position\",
        sr.shift_type AS \"Shift\"
    FROM 
        Staff s
    JOIN 
        Staff_Positions p ON s.position_id = p.position_id
    LEFT JOIN 
        Staff_Rota sr ON s.staff_number = sr.staff_number
";

// Parameters array for prepared statement
$params = [];

if ($wardFilter) {
    $sql .= " WHERE s.ward_allocated = ? ";
    $params[] = $wardFilter;
}

$sql .= " ORDER BY s.staff_number ";

// Execute query with parameters if any
$staffReport = executeQuery($sql, $params);

// If ward filter is set, get ward details for heading
$wardDetails = null;
if ($wardFilter) {
    $wardDetails = getSingleRecord("SELECT ward_name FROM Wards WHERE ward_number = ?", [$wardFilter]);
}

// Get all wards for dropdown filter
$wards = executeQuery("SELECT ward_number, ward_name FROM Wards ORDER BY ward_number");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Report - Wellmeadows Hospital</title>
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
    .filter-form select{
        margin-bottom: 10px;
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
        <h1>Staff Report</h1>


        <div class="card">
            <div class="card-header">
                <?php if ($wardFilter): ?>
                <div>Staff in <?php echo $wardDetails['ward_name']; ?> Ward (<?php echo $wardFilter; ?>)</div>
                <?php else: ?>
                <div>All Staff</div>
                <?php endif; ?>

                <form class="filter-form" method="GET" action="">
                    <select name="ward" class="form-control">
                        <option value="">All Wards</option>
                        <?php foreach ($wards as $ward): ?>
                        <option value="<?php echo $ward['ward_number']; ?>"
                            <?php echo ($wardFilter == $ward['ward_number']) ? 'selected' : ''; ?>>
                            <?php echo $ward['ward_name']; ?> (<?php echo $ward['ward_number']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn">Filter</button>
                    <button onclick="window.print()" class="btn">Print Report</button>
                </form>
            </div>

            <div class="report-title">
                <h2>Staff Report - Wellmeadows Hospital</h2>
                <p>Date: <?php echo date('d/m/Y'); ?></p>
                <?php if ($wardFilter): ?>
                <p>Ward: <?php echo $wardDetails['ward_name']; ?> (<?php echo $wardFilter; ?>)</p>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Staff No</th>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Tel No</th>
                            <th>Position</th>
                            <th>Shift</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staffReport) > 0): ?>
                        <?php foreach ($staffReport as $staff): ?>
                        <tr>
                            <td><?php echo $staff['Staff No']; ?></td>
                            <td><?php echo $staff['Name']; ?></td>
                            <td><?php echo $staff['Address']; ?></td>
                            <td><?php echo $staff['Tel No'] ?? 'N/A'; ?></td>
                            <td><?php echo $staff['Position']; ?></td>
                            <td><?php echo $staff['Shift'] ?? 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No staff records found.</td>
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

    <script>
    window.onbeforeprint = function() {
        document.querySelector('.report-title').style.display = 'block';
    };

    window.onafterprint = function() {
        document.querySelector('.report-title').style.display = 'none';
    };
    </script>
</body>

</html>