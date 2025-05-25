<?php
// Database connection
require_once 'db_connection.php';

$staff_number = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_number_post = $_POST['staff_number'];

    if (isset($_POST['add'])) {
        $qualification_type = $_POST['qualification_type'];
        $qualification_date = $_POST['qualification_date'];
        $institution = $_POST['institution'];

        $stmt = $pdo->prepare('INSERT INTO Staff_Qualifications (staff_number, qualification_type, qualification_date, institution) VALUES (?, ?, ?, ?)');
        $stmt->execute([$staff_number_post, $qualification_type, $qualification_date, $institution]);

        header("Location: staff_qualifications.php?id=" . urlencode($staff_number_post));
        exit;
    }
    elseif (isset($_POST['edit'])) {
        $qualification_id = $_POST['qualification_id'];
        $qualification_type = $_POST['qualification_type'];
        $qualification_date = $_POST['qualification_date'];
        $institution = $_POST['institution'];

        $stmt = $pdo->prepare('UPDATE Staff_Qualifications SET qualification_type=?, qualification_date=?, institution=? WHERE qualification_id=? AND staff_number=?');
        $stmt->execute([$qualification_type, $qualification_date, $institution, $qualification_id, $staff_number_post]);

        header("Location: staff_qualifications.php?id=" . urlencode($staff_number_post));
        exit;
    }
    elseif (isset($_POST['delete'])) {
        $qualification_id = $_POST['qualification_id'];

        $stmt = $pdo->prepare('DELETE FROM Staff_Qualifications WHERE qualification_id = ? AND staff_number = ?');
        $stmt->execute([$qualification_id, $staff_number_post]);

        header("Location: staff_qualifications.php?id=" . urlencode($staff_number_post));
        exit;
    }
}

if ($staff_number) {
    $sql = "SELECT q.*, s.first_name, s.last_name FROM Staff_Qualifications q 
            LEFT JOIN Staff s ON q.staff_number = s.staff_number
            WHERE q.staff_number = ?
            ORDER BY q.qualification_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$staff_number]);
    $qualifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $qualifications = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Staff Qualifications</title>

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
        font-size: 1.25rem;
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
        font-size: 0.9rem;
        margin-right: 5px;
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

    footer {
        background-color: var(--secondary-color);
        color: white;
        padding: 1rem 0;
        text-align: center;
        margin-top: 2rem;
    }

    .form-buttons {
        margin-top: 10px;
    }

    .form-buttons button {
        margin-right: 10px;
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
        <?php if ($staff_number && !empty($qualifications)): ?>
        <h1>Qualifications for
            <?= htmlspecialchars($qualifications[0]['first_name'] . ' ' . $qualifications[0]['last_name']) ?></h1>
        <?php elseif ($staff_number): ?>
        <h1>No qualifications found for staff number <?= htmlspecialchars($staff_number) ?></h1>
        <?php else: ?>
        <h1>Please select a staff member to view their qualifications.</h1>
        <?php endif; ?>

        <?php if ($staff_number): ?>
        <div class="card">
            <div class="card-header">Staff Qualifications</div>
            <a href="staff.php" class="btn">Back to staffs</a>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Qualification Type</th>
                            <th>Qualification Date</th>
                            <th>Institution</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($qualifications as $q): ?>
                        <tr>
                            <td><?= htmlspecialchars($q['qualification_id']) ?></td>
                            <td><?= htmlspecialchars($q['qualification_type']) ?></td>
                            <td><?= htmlspecialchars($q['qualification_date']) ?></td>
                            <td><?= htmlspecialchars($q['institution']) ?></td>
                            <td>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="qualification_id" value="<?= $q['qualification_id'] ?>">
                                    <input type="hidden" name="staff_number"
                                        value="<?= htmlspecialchars($staff_number) ?>">
                                    <button type="button" class="btn btn-success"
                                        onclick="showEditForm(<?= $q['qualification_id'] ?>, '<?= htmlspecialchars(addslashes($q['qualification_type'])) ?>', '<?= $q['qualification_date'] ?>', '<?= htmlspecialchars(addslashes($q['institution'])) ?>')">Edit</button>
                                    <button type="submit" class="btn btn-danger" name="delete"
                                        onclick="return confirm('Are you sure to delete this qualification?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header" id="form-header">Add New Qualification</div>
            <form method="post" id="qualificationForm">
                <input type="hidden" name="qualification_id" id="qualification_id" value="">
                <input type="hidden" name="staff_number" id="staff_number"
                    value="<?= htmlspecialchars($staff_number) ?>">

                <div class="form-group">
                    <label for="qualification_type">Qualification Type</label>
                    <input type="text" class="form-control" name="qualification_type" id="qualification_type" required>
                </div>

                <div class="form-group">
                    <label for="qualification_date">Qualification Date</label>
                    <input type="date" class="form-control" name="qualification_date" id="qualification_date" required>
                </div>

                <div class="form-group">
                    <label for="institution">Institution</label>
                    <input type="text" class="form-control" name="institution" id="institution" required>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn btn-success" name="add" id="addBtn">Add Qualification</button>
                    <button type="submit" class="btn btn-success" name="edit" id="editBtn" style="display:none;">Update
                        Qualification</button>
                    <button type="button" class="btn btn-danger" id="cancelBtn" style="display:none;"
                        onclick="resetForm()">Cancel</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </main>

    <footer>
        &copy; <?= date('Y') ?> Wellmeadows Hospital. All rights reserved.
    </footer>

    <script>
    function showEditForm(id, type, date, institution) {
        document.getElementById('qualification_id').value = id;
        document.getElementById('qualification_type').value = type;
        document.getElementById('qualification_date').value = date;
        document.getElementById('institution').value = institution;

        document.getElementById('addBtn').style.display = 'none';
        document.getElementById('editBtn').style.display = 'inline-block';
        document.getElementById('cancelBtn').style.display = 'inline-block';

        document.getElementById('form-header').textContent = 'Edit Qualification';
    }

    function resetForm() {
        document.getElementById('qualificationForm').reset();
        document.getElementById('qualification_id').value = '';
        document.getElementById('addBtn').style.display = 'inline-block';
        document.getElementById('editBtn').style.display = 'none';
        document.getElementById('cancelBtn').style.display = 'none';

        document.getElementById('form-header').textContent = 'Add New Qualification';
    }
    </script>
</body>

</html>