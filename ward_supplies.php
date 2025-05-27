<?php
// Include database connection
require_once 'db_connection.php';

// Initialize variables
$message = '';
$messageType = '';
$wardSupplies = [];
$wardDetails = null;

// Check if ward parameter is provided
$wardNumber = $_GET['ward'] ?? '';

if ($wardNumber) {
    // Get ward details including charge nurse and medical director
    $wardDetails = getSingleRecord(
        "SELECT * FROM Wards WHERE ward_number = ?",
        [$wardNumber]
    );

    if ($wardDetails) {
        // Get pharmaceutical supplies for the ward
        $pharmaceuticalSupplies = executeQuery(
            "SELECT ri.requisition_number, ri.quantity, 
                    ps.drug_number, ps.drug_name, ps.description, 
                    ps.dosage, ps.method_of_administration, ps.cost_per_unit,
                    r.requisition_date, r.delivery_date,
                    s.first_name AS staff_first_name, s.last_name AS staff_last_name
             FROM Requisition_Items ri
             JOIN Pharmaceutical_Supplies ps ON ri.drug_number = ps.drug_number
             JOIN Requisitions r ON ri.requisition_number = r.requisition_number
             JOIN Staff s ON r.staff_number = s.staff_number
             WHERE r.ward_number = ?
             ORDER BY r.requisition_date DESC",
            [$wardNumber]
        );

        // Get surgical supplies for the ward
        $surgicalSupplies = executeQuery(
            "SELECT ri.requisition_number, ri.quantity, 
                    ss.item_number, ss.item_name, ss.description, 
                    ss.cost_per_unit, ss.is_surgical,
                    r.requisition_date, r.delivery_date,
                    s.first_name AS staff_first_name, s.last_name AS staff_last_name
             FROM Requisition_Items ri
             JOIN Surgical_Supplies ss ON ri.item_number = ss.item_number
             JOIN Requisitions r ON ri.requisition_number = r.requisition_number
             JOIN Staff s ON r.staff_number = s.staff_number
             WHERE r.ward_number = ?
             ORDER BY r.requisition_date DESC",
            [$wardNumber]
        );

        // Combine both types of supplies and add type identifier
        foreach ($pharmaceuticalSupplies as &$supply) {
            $supply['type'] = 'Pharmaceutical';
        }
        foreach ($surgicalSupplies as &$supply) {
            $supply['type'] = $supply['is_surgical'] ? 'Surgical' : 'Non-Surgical';
        }

        $wardSupplies = array_merge($pharmaceuticalSupplies, $surgicalSupplies);
    } else {
        $message = "Ward not found.";
        $messageType = 'danger';
    }
}

// Get all wards for the dropdown
$wards = executeQuery("SELECT ward_number, ward_name FROM Wards ORDER BY ward_name");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Supplies Report - Wellmeadows Hospital</title>
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
            width: 1350px;
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

        .ward-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .ward-info-item {
            flex: 1;
            padding: 0 10px;
        }

        .ward-info-label {
            font-weight: bold;
            color: var(--secondary-color);
        }

        .print-btn {
            margin-bottom: 15px;
        }

        @media print {

            header,
            nav,
            .print-btn,
            .form-group {
                display: none;
            }

            .card {
                box-shadow: none;
                border: none;
            }

            body {
                background-color: white;
                color: black;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
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
        <h1>Ward Supplies Report</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Select Ward</div>
            <form method="GET" action="ward_supplies.php">
                <div class="form-group">
                    <label for="ward">Select Ward:</label>
                    <select class="form-control" id="ward" name="ward" required>
                        <option value="">-- Select a Ward --</option>
                        <?php foreach ($wards as $ward): ?>
                            <option value="<?php echo $ward['ward_number']; ?>" <?php echo ($wardNumber == $ward['ward_number']) ? 'selected' : ''; ?>>
                                <?php echo $ward['ward_name']; ?> (<?php echo $ward['ward_number']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">View Supplies</button>
                <?php if ($wardNumber && $wardDetails): ?>
                    <button type="button" class="btn print-btn" onclick="window.print()">Print Report</button>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($wardDetails): ?>
            <div class="card">
                <div class="card-header">Ward Information</div>
                <div class="ward-info">
                    <div class="ward-info-item">
                        <div class="ward-info-label">Ward Number</div>
                        <div><?php echo htmlspecialchars($wardDetails['ward_number']); ?></div>
                    </div>
                    <div class="ward-info-item">
                        <div class="ward-info-label">Ward Name</div>
                        <div><?php echo htmlspecialchars($wardDetails['ward_name']); ?></div>
                    </div>
                    <div class="ward-info-item">

                    </div>
                    <div class="ward-info-item">

                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Supplies Provided to Ward</div>
                <div class="table-container">
                    <?php if (count($wardSupplies) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Requisition #</th>
                                    <th>Item Type</th>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th>Description</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Requested By</th>
                                    <th>Requisition Date</th>
                                    <th>Delivery Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wardSupplies as $supply):
                                    $totalCost = $supply['quantity'] * $supply['cost_per_unit'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($supply['requisition_number']); ?></td>
                                        <td><?php echo htmlspecialchars($supply['type']); ?></td>
                                        <td><?php echo htmlspecialchars($supply['type'] === 'Pharmaceutical' ? $supply['drug_number'] : $supply['item_number']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($supply['type'] === 'Pharmaceutical' ? $supply['drug_name'] : $supply['item_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($supply['description']); ?></td>
                                        <td><?php echo htmlspecialchars($supply['quantity']); ?></td>
                                        <td>£<?php echo number_format($supply['cost_per_unit'], 2); ?></td>
                                        <td>£<?php echo number_format($totalCost, 2); ?></td>
                                        <td><?php echo htmlspecialchars($supply['staff_first_name'] . ' ' . $supply['staff_last_name']); ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($supply['requisition_date'])); ?></td>
                                        <td><?php echo $supply['delivery_date'] ? date('d/m/Y', strtotime($supply['delivery_date'])) : 'Pending'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="7">Total</th>
                                    <th>£<?php
                                    $grandTotal = array_reduce($wardSupplies, function ($carry, $item) {
                                        return $carry + ($item['quantity'] * $item['cost_per_unit']);
                                    }, 0);
                                    echo number_format($grandTotal, 2);
                                    ?></th>
                                    <th colspan="3"></th>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <p>No supplies found for this ward.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>