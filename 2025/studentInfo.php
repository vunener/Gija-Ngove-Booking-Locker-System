<?php
session_start();
include 'gijangovelockersystem.php';
include 'sessionManager.php';

requireLogin('parent');

$studentID = $_GET['studentID'] ?? null;
$success   = $_GET['success'] ?? null;

if (!$studentID) {
    echo "<p style='color:red;'>No student ID provided.</p>";
    exit;
}

// Fetch student info
$stmt = $pdo->prepare("SELECT * FROM students WHERE studentID = ?");
$stmt->execute([$studentID]);
$student = $stmt->fetch();

if (!$student) {
    echo "<p style='color:red;'>Student not found.</p>";
    exit;
}

// Optional: fetch status from waitinglist
$stmt = $pdo->prepare("SELECT status FROM waitinglist WHERE studentID = ?");
$stmt->execute([$studentID]);
$waiting = $stmt->fetch();

$status = $waiting['status'] ?? 'unknown';

?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Info</title>
    <link rel="stylesheet" href="styles.css">
    <style>
         html, body {
    height: 100%;
    margin: 0;
}

body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
     background-image: url('images/lockers.png');
}
       .containerSI {
    background-color: #d1f0cbff;
    padding: 40px;
    margin: 70px auto;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 600px;
    width: 50%;
    text-align: center;
    flex: 1;
}

.containerSI h2 {
    margin-bottom: 20px;
    color: #233985ff;
}

.containerSI ul {
    list-style: none;
    padding: 0;
    text-align: left;
    max-width: 400px;
    margin: 0 auto 20px auto;
}

.containerSI ul li {
    padding: 8px 0;
    border-bottom: 1px solid #ccc;
    font-size: 1.1em;
}

.success-message {
    color: green;
    font-weight: bold;
    margin-bottom: 20px;
}

.btn-link {
    display: inline-block;
    padding: 10px 20px;
    background-color: #233985ff;
    color: white;
    border-radius: 5px;
    text-decoration: none;
    transition: background-color 0.3s;
}

.btn-link:hover {
    background-color: #1a2d6d;
}

footer {
    flex-shrink: 0;
    background-color: #233985ff;
    padding: 15px 10px;
    color: white;
    text-align: center;
    font-size: 0.9rem;
}

</style>

</head>
<body>
<body>
<?php include 'menu.inc'; ?>

<div class="containerSI">
    <?php if ($success == 1): ?>
        <p class="success-message">Student registered successfully!</p>
    <?php endif; ?>

    <h2>Student Information</h2>
    <ul>
        <li><strong>School Number:</strong> <?= htmlspecialchars($student['studentSchoolNumber']) ?></li>
        <li><strong>Name:</strong> <?= htmlspecialchars($student['studentName']) ?> <?= htmlspecialchars($student['studentSurname']) ?></li>
        <li><strong>Date of Birth:</strong> <?= htmlspecialchars($student['dateOfBirth']) ?></li>
        <li><strong>Gender:</strong> <?= htmlspecialchars($student['gender']) ?></li>
        <li><strong>Grade:</strong> <?= htmlspecialchars($student['studentGrade']) ?></li>
        <li><strong>Status:</strong> <?= htmlspecialchars($status) ?></li>
    </ul>

    <a href="studentRegistration.php" class="btn-link">Register Another Student</a>
    <br><br>

    <a href="parentDashboard.php" class="btn-link">Back to Parent Dashboard</a>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
</footer>
</body>

</html>
