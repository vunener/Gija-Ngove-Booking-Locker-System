<?php
include 'sessionManager.php';
requireLogin(['parent', 'admin']);

include 'gijangovelockersystem.php';
include 'menu.inc';

// Validate student ID
$studentID = filter_input(INPUT_GET, 'studentID', FILTER_VALIDATE_INT) 
          ?? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$studentID) {
    die("Invalid or missing student ID.");
}

// Fetch student record
$stmt = $pdo->prepare("SELECT * FROM students WHERE studentID = ?");
$stmt->execute([$studentID]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found.");
}

// Fetch current locker (if assigned)
$stmtLocker = $pdo->prepare("SELECT l.lockerID, l.lockerNumber 
    FROM lockers l
    JOIN bookings b ON b.lockerID = l.lockerID
    WHERE b.studentID = ? AND LOWER(b.status) = 'completed'
");
$stmtLocker->execute([$studentID]);
$currentLocker = $stmtLocker->fetch(PDO::FETCH_ASSOC);

// Fetch available lockers
$availableLockers = $pdo->query("SELECT lockerID, lockerNumber 
    FROM lockers 
    WHERE availability = 0
")->fetchAll(PDO::FETCH_ASSOC);


// Form handling
$errors = [];
$success = isset($_GET['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['studentName'] ?? '');
    $surname = trim($_POST['studentSurname'] ?? '');
    $dob = $_POST['dateOfBirth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $grade = $_POST['studentGrade'] ?? '';
    $newLockerID = $_POST['lockerID'] ?? 'no_change';

    // Validation
    if (!$name || !$surname || !$dob || !$gender || !$grade) {
        $errors[] = "All fields are required.";
    }

    if (!in_array($gender, ['Male', 'Female'])) {
        $errors[] = "Invalid gender selection.";
    }

    if (!DateTime::createFromFormat('Y-m-d', $dob)) {
        $errors[] = "Invalid date of birth.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update student info
            $stmt = $pdo->prepare("  UPDATE students 
                SET studentName = ?, studentSurname = ?, dateOfBirth = ?, gender = ?, studentGrade = ? 
                WHERE studentID = ?
            ");
            $stmt->execute([$name, $surname, $dob, $gender, $grade, $studentID]);

            // Locker reassignment
            if ($newLockerID !== 'no_change' && $newLockerID != $currentLocker['lockerID']) {
                if ($currentLocker) {
                    // Mark old locker available
                    $pdo->prepare("UPDATE lockers SET availability = 0 WHERE lockerID = ?")
                        ->execute([$currentLocker['lockerID']]);
                }

                $pdo->prepare(" UPDATE bookings 
                    SET lockerID = ? 
                    WHERE studentID = ? AND LOWER(status) = 'completed'
                ")->execute([$newLockerID, $studentID]);

               // Mark new locker booked
                $pdo->prepare("UPDATE lockers SET availability = 1 WHERE lockerID = ?")
                    ->execute([$newLockerID]);
            }

            $pdo->commit();
            header("Location: editStudent.php?studentID=$studentID&success=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error updating student: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student</title>
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

        .containerES {
            background-color: #d1f0cbff;
            padding: 40px;
            margin: 70px auto 70px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 50%;
            text-align: center;

            /* Optional: keep flex:1 if it's part of your layout */
            flex: 1;
        }

        .containerES fieldset {
            width: 75%;
            margin: 70px auto 0 auto; /* or change to 50px if preferred */
            border: 2px solid #233985ff;
            padding: 20px;
            border-radius: 6px;
            background-color: #d1f0cbff;

            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .containerES fieldset form {
            width: 100%;
            max-width: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .containerES form input,
        .containerES form select {
            width: 100%;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        .containerES nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .containerES nav ul li a {
            text-decoration: none;
            color: #007BFF;
            font-size: 1.1em;
            transition: color 0.3s;
        }

        .containerES nav ul li a:hover {
            color: #0056b3;
            text-decoration: none;
        }

        footer {
            flex-shrink: 0;
            background-color: #233985ff; /* or use #064180ff if preferred */
            padding: 15px 10px;
            color: white;
            text-align: center;
            font-size: 0.9rem;
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
    <div class="containerES">
        <h2>Edit Student Details</h2>

        <?php if ($success): ?>
            <div class="success-message">Student information updated successfully.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="form-border">
                <label for="studentName">Name:</label>
                <input type="text" name="studentName" id="studentName" value="<?= htmlspecialchars($student['studentName']) ?>" required>

                <label for="studentSurname">Surname:</label>
                <input type="text" name="studentSurname" id="studentSurname" value="<?= htmlspecialchars($student['studentSurname']) ?>" required>

                <label for="dateOfBirth">Date of Birth:</label>
                <input type="date" name="dateOfBirth" id="dateOfBirth" value="<?= htmlspecialchars($student['dateOfBirth']) ?>" required>

                <label for="gender">Gender:</label>
                <select name="gender" id="gender" required>
                    <option value="">-- Select Gender --</option>
                    <option value="Male" <?= $student['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $student['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>

                <label for="studentGrade">Grade:</label>
                <select name="studentGrade" id="studentGrade" required>
                    <?php
                    $grades = ["Grade 8", "Grade 9", "Grade 10", "Grade 11", "Grade 12"];
                    foreach ($grades as $g):
                        $selected = ($student['studentGrade'] === $g) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($g) . "\" $selected>" . htmlspecialchars($g) . "</option>";
                    endforeach;
                    ?>
                </select>

                <label for="lockerID">Reassign Locker:</label>
                <select name="lockerID" id="lockerID">
                    <option value="no_change">-- No Change --</option>
                    <?php foreach ($availableLockers as $locker): ?>
                        <option value="<?= htmlspecialchars($locker['lockerID']) ?>">
                            Locker <?= htmlspecialchars($locker['lockerNumber']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($currentLocker): ?>
                    <p><strong>Current Locker:</strong> <?= htmlspecialchars($currentLocker['lockerNumber']) ?></p>
                <?php else: ?>
                    <p><em>No locker currently assigned.</em></p>
                <?php endif; ?>

                <input type="submit" class="btn" value="Save Changes">
            </div>
        </form>

        <br>
        
        <?php if ($_SESSION['userType'] === 'admin'): ?>
            <a href="adminDashboard.php" class="btn-secondary">&larr; Back to Dashboard</a>
        <?php elseif ($_SESSION['userType'] === 'parent'): ?>
            <a href="parentDashboard.php" class="btn-secondary">&larr; Back to Dashboard</a>
        <?php endif; ?>
    </div>
</main>

 <footer>
        <p>&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
    </footer>
</body>
</html>
