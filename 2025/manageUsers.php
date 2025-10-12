<?php
session_start();
require_once 'sessionManager.php';
requireLogin('admin'); // Only admin access

require_once 'gijangovelockersystem.php'; // PDO connection

$pageTitle = "Manage Users";
include 'menu.inc';

// Fetch counts
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalParents = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();

// Initialize variables for search results
$searchedStudent = null;
$searchedParent = null;
$studentSearchError = '';
$parentSearchError = '';

// Handle search forms submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['searchStudent'])) {
        $studentSchoolNumber = trim($_POST['studentSchoolNumber'] ?? '');
        if ($studentSchoolNumber === '') {
            $studentSearchError = "Please enter a student school number.";
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, l.lockerNumber 
                FROM students s
                LEFT JOIN lockers l ON s.lockerID = l.lockerID
                WHERE s.studentSchoolNumber = ?
            ");
            $stmt->execute([$studentSchoolNumber]);
            $searchedStudent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$searchedStudent) {
                $studentSearchError = "No student found with that school number.";
            }
        }
    }

    if (isset($_POST['searchParent'])) {
        $parentIDNumber = trim($_POST['parentIDNumber'] ?? '');
        if ($parentIDNumber === '') {
            $parentSearchError = "Please enter a parent ID number.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM parents WHERE parentIDNumber = ?");
            $stmt->execute([$parentIDNumber]);
            $searchedParent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$searchedParent) {
                $parentSearchError = "No parent found with that ID number.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?= htmlspecialchars($pageTitle) ?></title>
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
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            background-image: url('images/lockers.png');
        }

        .containerMU {
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

        fieldset {
            width: 75%;
            margin: 50px auto 0 auto;
            border: 2px solid #233985ff;
            padding: 20px;
            border-radius: 6px;
            background-color: #d1f0cbff;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        legend {
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #233985ff;
        }

        form {
            width: 100%;
            max-width: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        form input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        form button {
            padding: 10px 20px;
            background-color: #233985ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        form button:hover:not(:disabled) {
            background-color: #1a2f6eff;
        }

        form button:disabled {
            background-color: #a0a0a0;
            cursor: not-allowed;
        }

        table {
            margin: 20px auto;
            border-collapse: collapse;
            width: 80%;
            max-width: 500px;
        }

        table, th, td {
            border: 1px solid #333;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        a.btn-secondary {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 15px;
            background-color: #233985ff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        a.btn-secondary:hover {
            background-color: #1a2f6eff;
        }

        footer {
            flex-shrink: 0;
            background-color: #233985ff;
            padding: 15px 10px;
            color: white;
            text-align: center;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            footer {
                padding: 10px 5px;
                font-size: 1rem;
            }
            .containerMU {
                width: 90%;
                margin: 40px auto;
                padding: 30px;
            }
            fieldset {
                width: 90%;
            }
        }
    </style>
    <script>
        // Simple client-side disable button if input empty
        function toggleButton(inputId, buttonId) {
            const input = document.getElementById(inputId);
            const button = document.getElementById(buttonId);
            button.disabled = !input.value.trim();
            input.addEventListener('input', () => {
                button.disabled = !input.value.trim();
            });
        }
        window.addEventListener('DOMContentLoaded', () => {
            toggleButton('studentSchoolNumber', 'searchStudentBtn');
            toggleButton('parentIDNumber', 'searchParentBtn');
        });
    </script>
</head>
<body>

<main>
    <div class="containerMU">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p><strong>Number of Students in the Database:</strong> <?= htmlspecialchars($totalStudents) ?></p>
        <p><strong>Number of Parents in the Database:</strong> <?= htmlspecialchars($totalParents) ?></p>

        <!-- Student Search Form -->
        <fieldset>
            <legend>Search Student by School Number</legend>
            <form method="POST" action="">
                <input type="text" id="studentSchoolNumber" name="studentSchoolNumber" placeholder="Enter student school number"
                    value="<?= htmlspecialchars($_POST['studentSchoolNumber'] ?? '') ?>" autocomplete="off" />
                <button type="submit" name="searchStudent" id="searchStudentBtn" disabled>Search Student</button>
            </form>
            <?php if ($studentSearchError): ?>
                <p style="color:red;"><?= htmlspecialchars($studentSearchError) ?></p>
            <?php endif; ?>

            <?php if ($searchedStudent): ?>
                <h3>Student Found:</h3>
                <table>
                    <tr><th>ID</th><td><?= htmlspecialchars($searchedStudent['studentID']) ?></td></tr>
                    <tr><th>Name</th><td><?= htmlspecialchars($searchedStudent['studentName'] . ' ' . $searchedStudent['studentSurname']) ?></td></tr>
                    <tr><th>Grade</th><td><?= htmlspecialchars($searchedStudent['studentGrade']) ?></td></tr>
                    <tr><th>Locker</th><td><?= htmlspecialchars($searchedStudent['lockerNumber'] ?? 'Not Assigned') ?></td></tr>
                    <tr><th>Action</th><td><a href="editStudent.php?studentID=<?= urlencode($searchedStudent['studentID']) ?>">Edit</a></td></tr>
                </table>
            <?php endif; ?>
        </fieldset>

        <!-- Parent Search Form -->
        <fieldset>
            <legend>Search Parent by ID Number</legend>
            <form method="POST" action="">
                <input type="text" id="parentIDNumber" name="parentIDNumber" placeholder="Enter parent ID number"
                    value="<?= htmlspecialchars($_POST['parentIDNumber'] ?? '') ?>" autocomplete="off" />
                <button type="submit" name="searchParent" id="searchParentBtn" disabled>Search Parent</button>
            </form>
            <?php if ($parentSearchError): ?>
                <p style="color:red;"><?= htmlspecialchars($parentSearchError) ?></p>
            <?php endif; ?>

            <?php if ($searchedParent): ?>
                <h3>Parent Found:</h3>
                <table>
                    <tr><th>ID</th><td><?= htmlspecialchars($searchedParent['parentID']) ?></td></tr>
                    <tr><th>Username</th><td><?= htmlspecialchars($searchedParent['parentUsername']) ?></td></tr>
                    <tr><th>Email</th><td><?= htmlspecialchars($searchedParent['parentEmailAddress'] ?? 'N/A') ?></td></tr>
                    <tr><th>Action</th><td><a href="editParent.php?parentID=<?= urlencode($searchedParent['parentID']) ?>">Edit</a></td></tr>
                </table>
            <?php endif; ?>
        </fieldset>

        <?php if (isset($_SESSION['userType']) && $_SESSION['userType'] === 'admin'): ?>
            <a href="adminDashboard.php" class="btn-secondary">&larr; Back to Dashboard</a>
        <?php endif; ?>
    </div>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Gija-Ngove Locker System. All rights reserved.</p>
</footer>

</body>
</html>
