<?php
// Database configuration
require_once 'db_connection.php';
try {
    // Get patient number from query parameter
    $patient_number = $_GET['id'] ?? null;

    if (!$patient_number) {
        die("Patient number not specified");
    }

    // Debug query to see what's actually in the database
    if (isset($_GET['debug'])) {
        $tables = executeQuery("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = 'public'
        ");
        
        echo "<h3>Available Tables:</h3>";
        echo "<pre>";
        print_r($tables);
        echo "</pre>";
        
        // Try to get patient data
        $rawPatient = getSingleRecord("
            SELECT * FROM patients WHERE patient_number = ?
        ", [$patient_number]);
        
        echo "<h3>Raw Patient Data:</h3>";
        echo "<pre>";
        print_r($rawPatient);
        echo "</pre>";
    }
    
    // Use lowercase table names for PostgreSQL
    $patient = getSingleRecord("
        SELECT 
            p.*,
            ps.bed_number,
            ps.required_ward,
            w.ward_name
        FROM 
            patients p
        LEFT JOIN 
            patient_status ps ON p.patient_number = ps.patient_number
        LEFT JOIN 
            wards w ON ps.required_ward = w.ward_number
        WHERE 
            p.patient_number = ?
    ", [$patient_number]);
    
    if (!$patient) {
        die("Patient not found");
    }
    
    // Get patient medications - using lowercase table names
    $medications = executeQuery("
        SELECT 
            pm.*,
            ps.drug_name,
            ps.description,
            ps.dosage,
            ps.method_of_administration 
        FROM 
            patient_medication pm
        JOIN 
            pharmaceutical_supplies ps ON pm.drug_number = ps.drug_number
        WHERE 
            pm.patient_number = ?
    ", [$patient_number]);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Patient Details - Wellmeadows Hospital</title>

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
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .container {
        width: 1140px;
        max-width: 90%;
        margin: 0 auto;
        padding: 20px 15px;
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
        font-weight: 600;
    }

    nav ul li a:hover {
        color: var(--light-color);
    }

    main {
        flex: 1;
        padding: 2rem 0;
    }

    h3 {
        margin-bottom: 1rem;
        color: var(--secondary-color);
        font-weight: 700;
        font-size: 1.6rem;
    }

    .patient-info {
        background-color: white;
        padding: 20px;
        border-radius: 6px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .patient-info p {
        font-size: 1.1rem;
        margin-bottom: 12px;
    }

    .error {
        background-color: var(--danger-color);
        color: white;
        padding: 12px 15px;
        border-radius: 4px;
        font-weight: bold;
        margin-bottom: 20px;
    }

    table.medication-table {
        width: 100%;
        border-collapse: collapse;
        background-color: white;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    table.medication-table thead tr {
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
    }

    table.medication-table th,
    table.medication-table td {
        padding: 12px 15px;
        border: 1px solid #ddd;
        text-align: left;
        font-size: 0.95rem;
    }

    table.medication-table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    table.medication-table tbody tr:hover {
        background-color: #e9f1fc;
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

    footer {
        background-color: var(--secondary-color);
        color: white;
        padding: 1rem 0;
        text-align: center;
        margin-top: auto;
    }

    @media (max-width: 768px) {
        header .container {
            flex-direction: column;
            align-items: flex-start;
        }

        nav ul {
            flex-wrap: wrap;
            margin-top: 10px;
        }

        nav ul li {
            margin-left: 0;
            margin-right: 15px;
            margin-bottom: 10px;
        }

        .container {
            padding: 15px 10px;
        }

        .patient-info,
        table.medication-table {
            font-size: 0.9rem;
        }

        h3 {
            font-size: 1.3rem;
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
        <section class="patient-info">
            <a href="patients.php" class="btn-back">&larr; Back to Patients List</a>

            <h3>Patient Information</h3>
            <?php if ($patient): ?>
            <p><strong>Full Name:</strong>
                <?= htmlspecialchars($patient['first_name']) . ' ' . htmlspecialchars($patient['last_name']) ?></p>
            <p><strong>Bed Number:</strong> <?= htmlspecialchars($patient['bed_number'] ?? 'N/A') ?></p>
            <p><strong>Ward Number:</strong> <?= htmlspecialchars($patient['required_ward'] ?? 'N/A') ?></p>
            <p><strong>Ward Name:</strong> <?= htmlspecialchars($patient['ward_name'] ?? 'N/A') ?></p>
            <?php else: ?>
            <div class="error">No patient found with number <?= htmlspecialchars($patient_number) ?></div>
            <?php endif; ?>
        </section>

        <section class="medications">
            <h3>Medication Details</h3>
            <?php if (!empty($medications)): ?>
            <table class="medication-table" role="grid" aria-label="Medication Details">
                <thead>
                    <tr>
                        <th scope="col">Drug Number</th>
                        <th scope="col">Drug Name</th>
                        <th scope="col">Description</th>
                        <th scope="col">Dosage</th>
                        <th scope="col">Method of Administration</th>
                        <th scope="col">Units/Day</th>
                        <th scope="col">Start Date</th>
                        <th scope="col">Finish Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medications as $med): ?>
                    <tr>
                        <td><?= htmlspecialchars($med['drug_number']) ?></td>
                        <td><?= htmlspecialchars($med['drug_name']) ?></td>
                        <td><?= htmlspecialchars($med['description']) ?></td>
                        <td><?= htmlspecialchars($med['dosage']) ?></td>
                        <td><?= htmlspecialchars($med['method_of_administration']) ?></td>
                        <td><?= htmlspecialchars($med['units_per_day']) ?></td>
                        <td><?= htmlspecialchars($med['start_date']) ?></td>
                        <td><?= htmlspecialchars($med['finish_date'] ?? 'Ongoing') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No medication records found for this patient.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y'); ?> Wellmeadows Hospital. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>