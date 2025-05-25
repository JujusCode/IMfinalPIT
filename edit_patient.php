<?php
require_once 'db_connection.php';

$patient_number = $_GET['id'] ?? null;
if (!$patient_number) {
    die("Patient number not specified.");
}

try {
    // Fetch doctors for dropdown (assumed Local_Doctors table)
    $stmt = $conn->prepare("SELECT doctor_id, fullname FROM Local_Doctors ORDER BY fullname");
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch patient data
    $patient = getSingleRecord("SELECT * FROM patients WHERE patient_number = ?", [$patient_number]);
    if (!$patient) {
        die("Patient not found.");
    }

    $error = '';
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_number_form = trim($_POST['patient_number'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        $sex = $_POST['sex'] ?? '';
        $marital_status = trim($_POST['marital_status'] ?? '');
        $doctor_id = $_POST['doctor_id'] ?: null;

        if (!$patient_number_form || !$first_name || !$last_name || !$address || !$date_of_birth || !$sex) {
            $error = "Please fill in all required fields.";
        } else {
            $updateQuery = "UPDATE patients SET
                patient_number = ?,
                first_name = ?,
                last_name = ?,
                address = ?,
                telephone = ?,
                date_of_birth = ?,
                sex = ?,
                marital_status = ?,
                doctor_id = ?
                WHERE patient_number = ?";

            $updateParams = [
                $patient_number_form,
                $first_name,
                $last_name,
                $address,
                $telephone,
                $date_of_birth,
                $sex,
                $marital_status,
                $doctor_id,
                $patient_number
            ];

            $result = executeNonQuery($updateQuery, $updateParams);

            if ($result) {
                header("Location: edit_patient.php?id=" . urlencode($patient_number_form));
                exit();
            } else {
                $error = "Failed to update patient information.";
            }
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Patient - Wellmeadows Hospital</title>
    <style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --danger-color: #e74c3c;
        --light-color: #ecf0f1;
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
        min-height: 100vh;
        display: flex;
        flex-direction: column;
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
        width: 1140px;
        max-width: 90%;
        margin: 0 auto;
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
        font-weight: 600;
        transition: color 0.3s;
    }

    nav ul li a:hover {
        color: var(--light-color);
    }

    main {
        flex: 1;
        padding: 2rem 0;
        width: 1140px;
        max-width: 90%;
        margin: 0 auto;
    }

    h2 {
        margin-bottom: 1rem;
        color: var(--secondary-color);
        font-weight: 700;
        font-size: 1.8rem;
    }

    .card {
        background-color: white;
        border-radius: 6px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 20px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        color: var(--secondary-color);
    }

    input[type="text"],
    input[type="tel"],
    input[type="date"],
    select,
    textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 1rem;
        font-family: inherit;
    }

    textarea {
        resize: vertical;
    }

    .alert {
        padding: 12px 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        font-weight: bold;
    }

    .alert-danger {
        background-color: var(--danger-color);
        color: white;
    }

    button {
        background-color: var(--primary-color);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 4px;
        font-size: 1rem;
        cursor: pointer;
        font-weight: 600;
        transition: background-color 0.3s;
    }

    button:hover {
        background-color: #2980b9;
    }

    a.btn-back {
        display: inline-block;
        margin-bottom: 15px;
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
    }

    a.btn-back:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {

        header .container,
        main {
            width: 100%;
            max-width: 100%;
            padding: 0 15px;
        }

        nav ul {
            flex-wrap: wrap;
        }

        nav ul li {
            margin-left: 0;
            margin-right: 15px;
            margin-bottom: 10px;
        }
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

    <main>
        <a href="patients.php" class="btn-back">&larr; Back to Patients List</a>

        <h2>Edit Patient</h2>

        <div class="card">
            <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="form-group">
                    <label for="patient_number">Patient ID *</label>
                    <input type="text" id="patient_number" name="patient_number" maxlength="10" required
                        value="<?= htmlspecialchars($patient['patient_number']) ?>">
                </div>

                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" required
                        value="<?= htmlspecialchars($patient['first_name']) ?>">
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" required
                        value="<?= htmlspecialchars($patient['last_name']) ?>">
                </div>

                <div class="form-group">
                    <label for="address">Address *</label>
                    <textarea id="address" name="address"
                        required><?= htmlspecialchars($patient['address']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="telephone">Telephone</label>
                    <input type="tel" id="telephone" name="telephone" maxlength="12" pattern="[0-9\s\-+()]*"
                        value="<?= htmlspecialchars($patient['telephone']) ?>">
                </div>

                <div class="form-group">
                    <label for="date_of_birth">Date of Birth *</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" required
                        value="<?= htmlspecialchars($patient['date_of_birth']) ?>">
                </div>

                <div class="form-group">
                    <label>Sex *</label>
                    <label><input type="radio" name="sex" value="M" <?= $patient['sex'] === 'M' ? 'checked' : '' ?>>
                        Male</label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="sex" value="F" <?= $patient['sex'] === 'F' ? 'checked' : '' ?>>
                        Female</label>
                </div>

                <div class="form-group">
                    <label for="marital_status">Marital Status</label>
                    <input type="text" id="marital_status" name="marital_status"
                        value="<?= htmlspecialchars($patient['marital_status']) ?>">
                </div>

                <div class="form-group">
                    <label for="doctor_id">Attending Doctor</label>
                    <select id="doctor_id" name="doctor_id">
                        <option value="">-- Select Doctor --</option>
                        <?php foreach ($doctors as $doc): ?>
                        <option value="<?= htmlspecialchars($doc['doctor_id']) ?>"
                            <?= $doc['doctor_id'] == $patient['doctor_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($doc['fullname']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">Save Changes</button>
            </form>
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