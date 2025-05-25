<?php
require_once 'db_connection.php';

$message = '';
$messageType = '';
$patientNumber = $_GET['id'] ?? null;

if (!$patientNumber) {
    die("Patient ID is required.");
}

try {
    // Check if the patient is currently admitted (no actual_leave_date or actual_leave_date > today)
    $status = getSingleRecord(
        "SELECT * FROM Patient_Status WHERE patient_number = ? AND is_outpatient = FALSE AND (actual_leave_date IS NULL OR actual_leave_date > CURRENT_DATE)",
        [$patientNumber]
    );

    if (!$status) {
        $message = "Patient is not currently admitted or already discharged.";
        $messageType = 'danger';
    } else {
        // Set actual_leave_date to today to discharge
        $result = executeNonQuery(
            "UPDATE Patient_Status SET actual_leave_date = CURRENT_DATE WHERE patient_number = ?",
            [$patientNumber]
        );

        if ($result) {
            $message = "Patient discharged successfully.";
            $messageType = 'success';

            // Optional: redirect to the ward page after discharge with a success message
            // Assuming the ward number is in $status['required_ward']
            header("Location: ward_patients.php?ward=" . urlencode($status['required_ward']) . "&message=" . urlencode($message) . "&messageType=" . urlencode($messageType));
            exit;
        } else {
            $message = "Failed to discharge patient.";
            $messageType = 'danger';
        }
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = 'danger';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Discharge Patient</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        padding: 2rem;
        background-color: #f5f7fa;
        color: #333;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        padding: 1rem;
        border-radius: 5px;
        margin-bottom: 1rem;
    }

    a {
        display: inline-block;
        margin-top: 1rem;
        color: #3498db;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <h1>Discharge Patient</h1>

    <?php if ($message): ?>
    <div class="alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <a href="javascript:history.back()">Go Back</a>
</body>

</html>