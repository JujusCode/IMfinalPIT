<?php
// patient_medication.php

// Database connection parameters
$host = "localhost";
$port = "5432";
$dbname = "wellmeadows";
$user = "postgres";
$password = "bardinas123";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function executeQuery($sql, $params = [])
{
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "Query Error: " . $e->getMessage();
        return [];
    }
}

function executeNonQuery($sql, $params = [])
{
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        echo "Query Error: " . $e->getMessage();
        return 0;
    }
}

// Message vars
$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_number = $_POST['patient_number'] ?? '';
    $drug_numbers = $_POST['drug_number'] ?? [];
    $units_per_day_arr = $_POST['units_per_day'] ?? [];
    $start_dates = $_POST['start_date'] ?? [];
    $finish_dates = $_POST['finish_date'] ?? [];

    // Basic validation
    if (!$patient_number) {
        $message = "Please select a patient.";
        $messageClass = 'error';
    } elseif (empty($drug_numbers)) {
        $message = "Please add at least one drug.";
        $messageClass = 'error';
    } else {
        $successCount = 0;
        for ($i = 0; $i < count($drug_numbers); $i++) {
            $drug_number = $drug_numbers[$i];
            $units_per_day = $units_per_day_arr[$i] ?? 0;
            $start_date = $start_dates[$i] ?? '';
            $finish_date = $finish_dates[$i] ?: null;

            if (!$drug_number || !$units_per_day || !$start_date) {
                continue; // skip incomplete entry
            }

            $sql = "INSERT INTO Patient_Medication 
                    (patient_number, drug_number, units_per_day, start_date, finish_date)
                    VALUES (:patient_number, :drug_number, :units_per_day, :start_date, :finish_date)";
            $params = [
                ':patient_number' => $patient_number,
                ':drug_number' => $drug_number,
                ':units_per_day' => $units_per_day,
                ':start_date' => $start_date,
                ':finish_date' => $finish_date
            ];
            $rows = executeNonQuery($sql, $params);
            if ($rows > 0) {
                $successCount++;
            }
        }

        if ($successCount > 0) {
            $message = "Successfully assigned $successCount medication(s).";
            $messageClass = 'success';
        } else {
            $message = "Failed to assign medications. Please check your inputs.";
            $messageClass = 'error';
        }
    }
}

// Fetch dropdown data
$patients = executeQuery("SELECT patient_number, first_name, last_name FROM Patients ORDER BY last_name");
$drugs = executeQuery("SELECT drug_number, drug_name FROM Pharmaceutical_Supplies ORDER BY drug_name");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Assign Multiple Medications to Patient</title>
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
        <h1 class="page-title">Assign Multiple Medications to Patient</h1>

        <?php if ($message): ?>
            <div class="alert <?= $messageClass === 'success' ? 'alert-success' : 'alert-danger' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" id="medicationForm">

                <div class="form-group">
                    <label for="patient_number">Select Patient</label>
                    <select name="patient_number" id="patient_number" class="form-control" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $patient):
                            $fullName = htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            <option value="<?= htmlspecialchars($patient['patient_number']) ?>"><?= $fullName ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Medications</label>
                    <div class="table-container">
                        <table id="medicationsTable">
                            <thead>
                                <tr>
                                    <th>Drug</th>
                                    <th>Units per Day</th>
                                    <th>Start Date</th>
                                    <th>Finish Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select name="drug_number[]" class="form-control" required>
                                            <option value="">-- Select Drug --</option>
                                            <?php foreach ($drugs as $drug): ?>
                                                <option value="<?= htmlspecialchars($drug['drug_number']) ?>">
                                                    <?= htmlspecialchars($drug['drug_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="units_per_day[]" min="1" max="10"
                                            class="form-control" required></td>
                                    <td><input type="date" name="start_date[]" class="form-control" required></td>
                                    <td><input type="date" name="finish_date[]" class="form-control"></td>
                                    <td><button type="button" class="btn btn-danger remove-btn"
                                            onclick="removeRow(this)">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <button type="button" class="btn" onclick="addRow()">Add Another Drug</button>
                    <button type="submit" class="btn btn-success">Assign Medications</button>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>

    <script>
        function addRow() {
            const tbody = document.querySelector("#medicationsTable tbody");
            const newRow = document.createElement("tr");
            newRow.innerHTML = `
                <td>
                    <select name="drug_number[]" class="form-control" required>
                        <option value="">-- Select Drug --</option>
                        <?php foreach ($drugs as $drug): ?>
                            <option value="<?= htmlspecialchars($drug['drug_number']) ?>"><?= htmlspecialchars($drug['drug_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="units_per_day[]" min="1" max="10" class="form-control" required></td>
                <td><input type="date" name="start_date[]" class="form-control" required></td>
                <td><input type="date" name="finish_date[]" class="form-control"></td>
                <td><button type="button" class="btn btn-danger remove-btn" onclick="removeRow(this)">Remove</button></td>
            `;
            tbody.appendChild(newRow);
        }

        function removeRow(button) {
            const row = button.closest("tr");
            const tbody = document.querySelector("#medicationsTable tbody");
            if (tbody.rows.length > 1) {
                row.remove();
            } else {
                alert("At least one medication entry is required.");
            }
        }
    </script>
</body>

</html>