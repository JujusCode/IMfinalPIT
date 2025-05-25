<?php
// Database connection
require_once 'db_connection.php';

// Get the staff number from GET parameter 'id'
$staff_number = $_GET['id'] ?? null;

// Handle form submissions for Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use staff_number from hidden input to prevent changes
    $staff_number_post = $_POST['staff_number'];

    if (isset($_POST['add'])) {
        $position = $_POST['position'];
        $start_date = $_POST['start_date'];
        $finish_date = $_POST['finish_date'] ?: null;
        $organization = $_POST['organization'];

        $sql = 'INSERT INTO Staff_Work_Experience (staff_number, position, start_date, finish_date, organization) VALUES (?, ?, ?, ?, ?)';
        executeNonQuery($sql, [$staff_number_post, $position, $start_date, $finish_date, $organization]);

        header("Location: staff_experience.php?id=" . urlencode($staff_number_post));
        exit;
    } 
    elseif (isset($_POST['edit'])) {
        $experience_id = $_POST['experience_id'];
        $position = $_POST['position'];
        $start_date = $_POST['start_date'];
        $finish_date = $_POST['finish_date'] ?: null;
        $organization = $_POST['organization'];

        $sql = 'UPDATE Staff_Work_Experience SET position=?, start_date=?, finish_date=?, organization=? WHERE experience_id=? AND staff_number=?';
        executeNonQuery($sql, [$position, $start_date, $finish_date, $organization, $experience_id, $staff_number_post]);

        header("Location: staff_experience.php?id=" . urlencode($staff_number_post));
        exit;
    } 
    elseif (isset($_POST['delete'])) {
        $experience_id = $_POST['experience_id'];
        $sql = 'DELETE FROM Staff_Work_Experience WHERE experience_id = ? AND staff_number = ?';
        executeNonQuery($sql, [$experience_id, $staff_number_post]);

        header("Location: staff_experience.php?id=" . urlencode($staff_number_post));
        exit;
    }
}

// Fetch experiences only for the selected staff member
if ($staff_number) {
    $sql = "SELECT e.*, s.first_name, s.last_name FROM Staff_Work_Experience e 
            LEFT JOIN Staff s ON e.staff_number = s.staff_number
            WHERE e.staff_number = ?
            ORDER BY e.start_date DESC";

    $experiences = executeQuery($sql, [$staff_number]);
} else {
    $experiences = [];
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Staff Work Experience</title>
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
        <?php if ($staff_number && !empty($experiences)): ?>
        <h1>Work Experience for
            <?= htmlspecialchars($experiences[0]['first_name'] . ' ' . $experiences[0]['last_name']) ?></h1>
        <?php elseif ($staff_number): ?>
        <h1>No work experience found for staff number <?= htmlspecialchars($staff_number) ?></h1>
        <?php else: ?>
        <h1>Please select a staff member to view their work experience.</h1>
        <?php endif; ?>

        <?php if ($staff_number): ?>
        <div class="card">
            <div class="card-header">Work Experience List</div>
            <a href="staff.php" class="btn">Back to staffs</a>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Position</th>
                            <th>Start Date</th>
                            <th>Finish Date</th>
                            <th>Organization</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($experiences as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['experience_id']) ?></td>
                            <td><?= htmlspecialchars($e['position']) ?></td>
                            <td><?= htmlspecialchars($e['start_date']) ?></td>
                            <td><?= htmlspecialchars($e['finish_date']) ?></td>
                            <td><?= htmlspecialchars($e['organization']) ?></td>
                            <td>
                                <form method="post" style="display:inline-block; margin:0;">
                                    <input type="hidden" name="experience_id" value="<?= $e['experience_id'] ?>">
                                    <input type="hidden" name="staff_number"
                                        value="<?= htmlspecialchars($staff_number) ?>">
                                    <button type="button" class="btn btn-success btn-sm"
                                        onclick="showEditForm(<?= $e['experience_id'] ?>, '<?= htmlspecialchars(addslashes($e['position'])) ?>', '<?= $e['start_date'] ?>', '<?= $e['finish_date'] ?>', '<?= htmlspecialchars(addslashes($e['organization'])) ?>')">
                                        Edit
                                    </button>
                                    <button type="submit" name="delete" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure to delete this experience?')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Add / Edit Experience</div>
            <form method="post" id="experienceForm">
                <input type="hidden" name="experience_id" id="experience_id" value="">
                <input type="hidden" name="staff_number" id="staff_number"
                    value="<?= htmlspecialchars($staff_number) ?>">

                <div class="form-group">
                    <label for="position">Position:</label>
                    <input type="text" name="position" id="position" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="finish_date">Finish Date:</label>
                    <input type="date" name="finish_date" id="finish_date" class="form-control">
                </div>

                <div class="form-group">
                    <label for="organization">Organization:</label>
                    <input type="text" name="organization" id="organization" class="form-control" required>
                </div>

                <button type="submit" name="add" id="submitBtn" class="btn btn-primary">Add Experience</button>
                <button type="submit" name="edit" id="editBtn" class="btn btn-success" style="display:none;">Save
                    Changes</button>
                <button type="button" onclick="resetForm()" class="btn btn-warning">Cancel</button>
            </form>
        </div>
        <?php endif; ?>
    </main>

    <script>
    function showEditForm(id, position, startDate, finishDate, organization) {
        document.getElementById('experience_id').value = id;
        document.getElementById('position').value = position;
        document.getElementById('start_date').value = startDate;
        document.getElementById('finish_date').value = finishDate;
        document.getElementById('organization').value = organization;

        document.getElementById('submitBtn').style.display = 'none';
        document.getElementById('editBtn').style.display = 'inline-block';
    }

    function resetForm() {
        document.getElementById('experience_id').value = '';
        document.getElementById('position').value = '';
        document.getElementById('start_date').value = '';
        document.getElementById('finish_date').value = '';
        document.getElementById('organization').value = '';

        document.getElementById('submitBtn').style.display = 'inline-block';
        document.getElementById('editBtn').style.display = 'none';
    }
    </script>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>