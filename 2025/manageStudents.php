<?php
include 'sessionManager.php';
requireLogin('admin');

include 'gijangovelockersystem.php'; // Assumes $pdo is initialized here
include 'menu.inc';

$errors = [];
$success = '';

// Fetch all students with their parent and locker info
try {
    $stmt = $pdo->prepare("SELECT s.studentID, s.studentSchoolNumber, s.studentName,s.studentSurname, s.dateOfBirth, s.gender, s.studentGrade, p.parentName, p.parentSurname,p.parentEmailAddress, l.lockerID, l.lockerNumber, l.lockerLocation
FROM students s LEFT JOIN parents p ON s.parentID = p.parentID
LEFT JOIN bookings b ON s.studentID = b.studentID
LEFT JOIN lockers l ON b.lockerID = l.lockerID
ORDER BY s.studentGrade, s.studentSurname

    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to retrieve students: " . $e->getMessage();
    $students = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students | Gija‑Ngove</title>
    <link rel="stylesheet" href="styles.css" />
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

        .containerMS {
            background-color: #d1f0cbff;
            padding: 40px;
            padding-bottom: 60px; /* Reserve space for fixed footer */
            margin: 70px auto auto auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 1500px;
            width: 80%;
            text-align: center;
            flex: 1;
        }

        table.custom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table.custom-table th,
        table.custom-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }

        table.custom-table th {
            background-color: #3D7DC1;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .btn-secondary {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #233985;
            color: white;
            text-decoration: none;
            border-radius: 4px;
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
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
<main>
    <div class="containerMS">
        <h2>Manage Students</h2>

        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($students): ?>
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>School No</th>
                        <th>First Name</th>
                        <th>Surname</th>
                        <th>Birth Date</th>
                        <th>Gender</th>
                        <th>Grade</th>
                        <th>Parent</th>
                        <th>Parent Email</th>
                        <th>Locker Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['studentID']) ?></td>
                            <td><?= htmlspecialchars($s['studentSchoolNumber']) ?></td>
                            <td><?= htmlspecialchars($s['studentName']) ?></td>
                            <td><?= htmlspecialchars($s['studentSurname']) ?></td>
                            <td><?= htmlspecialchars($s['dateOfBirth']) ?></td>
                            <td><?= htmlspecialchars($s['gender']) ?></td>
                            <td><?= htmlspecialchars($s['studentGrade']) ?></td>
                            <td><?= htmlspecialchars($s['parentName'] . ' ' . $s['parentSurname']) ?></td>
                            <td><?= htmlspecialchars($s['parentEmailAddress']) ?></td>
                            <td><?= $s['lockerID'] ? "Assigned (ID: " . htmlspecialchars($s['lockerID']) . ")" : "Not Assigned" ?></td>
                            <td>
                                <a href="editStudent.php?id=<?= $s['studentID'] ?>">Edit</a> |
                                <a href="deleteStudent.php?id=<?= $s['studentID'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No students found in the system.</p>
        <?php endif; ?>

        <br>
        <a href="adminDashboard.php" class="btn-secondary">&larr; Back to Dashboard</a>
    </div>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Gija-Ngove Locker System. All rights reserved.</p>
</footer>
</body>
</html>
