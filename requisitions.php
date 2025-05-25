<?php
require_once 'db_connection.php';

// Handle delete action
if (isset($_GET['delete'])) {
    $reqNum = $_GET['delete'];

    // Delete items first due to FK constraints
    executeNonQuery("DELETE FROM Requisition_Items WHERE requisition_number = ?", [$reqNum]);
    // Delete requisition
    executeNonQuery("DELETE FROM Requisitions WHERE requisition_number = ?", [$reqNum]);

    header("Location: requisitions.php");
    exit;
}

// Fetch all requisitions with staff and ward info
$requisitions = executeQuery("
    SELECT 
        r.requisition_number,
        r.ward_number,
        w.ward_name,
        CONCAT(s.first_name, ' ', s.last_name) AS requisitioned_by,
        TO_CHAR(r.requisition_date, 'DD-Mon-YY') AS requisition_date,
        STRING_AGG(DISTINCT sup.supplier_name, ', ') AS suppliers
    FROM Requisitions r
    JOIN Wards w ON r.ward_number = w.ward_number
    JOIN Staff s ON r.staff_number = s.staff_number
    LEFT JOIN Requisition_Items ri ON ri.requisition_number = r.requisition_number
    LEFT JOIN Supply_Items si ON 
        (ri.item_number IS NOT NULL AND si.item_number = ri.item_number) OR
        (ri.drug_number IS NOT NULL AND si.drug_number = ri.drug_number)
    LEFT JOIN Suppliers sup ON si.supplier_number = sup.supplier_number
    GROUP BY r.requisition_number, r.ward_number, w.ward_name, s.first_name, s.last_name, r.requisition_date
    ORDER BY r.requisition_number DESC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Requisitions List</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 2rem;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 10px;
        border: 1px solid #ddd;
    }

    th {
        background: #34495e;
        color: white;
    }

    a.button {
        background: #27ae60;
        color: white;
        padding: 6px 12px;
        text-decoration: none;
        border-radius: 4px;
    }

    a.button.delete {
        background: #c0392b;
    }
    </style>
</head>

<body>

    <h1>Requisitions</h1>
    <a href="requisition_form.php" class="button">Add New Requisition</a>
    <table>
        <thead>
            <tr>
                <th>Requisition Number</th>
                <th>Ward Number</th>
                <th>Ward Name</th>
                <th>Requisitioned By</th>
                <th>Suppliers</th>
                <th>Requisition Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requisitions as $req): ?>
            <tr>
                <td><?php echo htmlspecialchars($req['requisition_number']); ?></td>
                <td><?php echo htmlspecialchars($req['ward_number']); ?></td>
                <td><?php echo htmlspecialchars($req['ward_name']); ?></td>
                <td><?php echo htmlspecialchars($req['requisitioned_by']); ?></td>
                <td><?php echo htmlspecialchars($req['suppliers'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($req['requisition_date']); ?></td>
                <td>
                    <a href="requisition_form.php?edit=<?php echo urlencode($req['requisition_number']); ?>"
                        class="button">Edit</a>
                    <a href="requisitions.php?delete=<?php echo urlencode($req['requisition_number']); ?>"
                        onclick="return confirm('Delete this requisition?');" class="button delete">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>