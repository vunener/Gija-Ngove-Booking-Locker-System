<?php
include 'sessionManager.php';
requireLogin('parent', 'admin');

include 'gijangovelockersystem.php';

$userRole = $_SESSION['userRole'] ?? 'parent'; // get role
$parentID = $_SESSION['userID'];

// Fetch all students for this parent
    if ($userRole === 'admin') {
        $stmt = $pdo->query(" SELECT s.*, l.lockerNumber 
                                FROM students s
                                LEFT JOIN lockers l ON s.lockerID = l.lockerID
");

    } else {
        $stmt = $pdo->prepare(" SELECT s.*, l.lockerNumber 
                                FROM students s
                                LEFT JOIN lockers l ON s.lockerID = l.lockerID
                                WHERE s.parentID = ?
");
$stmt->execute([$parentID]);

        $stmt->execute([$parentID]);
    }
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$successMsg = '';
if (isset($_GET['registered'])) {
    $successMsg = "Student registered successfully.";
} elseif (isset($_GET['deleted'])) {
    $successMsg = "Student deleted successfully.";
} elseif (isset($_GET['updated'])) {
    $successMsg = "Student updated successfully.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Your Registered Students</title>
    <!-- <link rel="stylesheet" href="styles.css"> -->

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

.containerVSI {
    background-color: #d1f0cbff;
    padding: 40px;
    padding-bottom: 60px; /* Reserve space for fixed footer */
    margin: 50px auto auto auto;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 800px;
    width: 50%;
    text-align: center;
    flex: 1;
}
.btn-secondary {
            display: inline-block;
            padding: 8px 12px;
            background-color:  #233985ff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 30px;
            transition: background-color 0.3s ease;

}
footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background-color: #233985ff;
    padding: 15px 10px;
    color: white;
    text-align: center;
    font-size: 0.9rem;
    z-index: 1000;
}

  @media (max-width: 768px) {
            footer {
                padding: 10px 5px;
                font-size: 1rem;
            }
        }
.page-wrapper {
    flex: 1;
    display: flex;
    justify-content: center;  /* centers horizontally */
    align-items: center;      /* centers vertically */
}


</style>
</head>
<body>
    <?php include 'menu.inc'; ?>

    <div class="containerVSI">
        <h2>Your Registered Students</h2>

        <?php if ($successMsg): ?>
            <p style="color: green;"><?= htmlspecialchars($successMsg) ?></p>
        <?php endif; ?>

        <?php if (empty($students)): ?>
            <p>You have not registered any students yet.</p>
            <a href="studentRegistration.php">Register a Student</a>
        <?php else: ?>
            <table border="1" cellpadding="8" cellspacing="0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Surname</th>
                        <th>Date of Birth</th>
                        <th>Gender</th>
                        <th>Grade</th>
                        <th>Locker Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['studentName']) ?></td>
                            <td><?= htmlspecialchars($student['studentSurname']) ?></td>
                            <td><?= htmlspecialchars($student['dateOfBirth']) ?></td>
                            <td><?= htmlspecialchars($student['gender']) ?></td>
                            <td><?= htmlspecialchars($student['studentGrade']) ?></td>
                            <td><?= htmlspecialchars($student['lockerNumber'] ?? 'Not Assigned') ?></td>
                            <td>
                                <a href="editStudent.php?studentID=<?= $student['studentID'] ?>">Edit</a>
                                <?php if ($userRole === 'admin'): ?>
                                    | <a href="deleteStudent.php?studentID=<?= $student['studentID'] ?>" onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <a href="studentRegistration.php">Register another Student</a>
        <?php endif; ?>

        <br><br>
        <a href="parentDashboard.php" class="btn-secondary">&larr; Back to Dashboard</a>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Gija-Ngove Locker System. All rights reserved.</p>
    </footer>
</body>
</html>
