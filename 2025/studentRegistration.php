<?php
session_start();
include 'sessionManager.php';
requireLogin('parent');

include 'gijangovelockersystem.php';

$availableLockersStmt = $pdo->query("SELECT lockerID, lockerNumber, lockerLocation 
FROM lockers 
WHERE availability = 'available'");
$availableLockers = $availableLockersStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Register a Student</title>
    <!-- <link rel="stylesheet" href="styles.css"> -->

    <style>
        /* Optional: Page-specific styling */
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
                .containerSR {
            background-color: #d1f0cbff;
            padding: 40px;
            padding-bottom: 60px;
            margin: 70px auto 70px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 80%;
            text-align: center;
        }

        .form-box-tabs {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
        }

        .form-box {
            border: 1px solid #233985ff;
            padding: 30px;
            border-radius: 10px;
            width: 300px;
            background-color: white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .button-link {
            text-decoration: none;
            color: inherit;
        }
           .btn, .btn-secondary {
        display: inline-block;
        padding: 10px 20px;
        margin: 5px;
        text-decoration: none;
        color: white;
        background-color: #233985ff;
        border-radius: 4px;
        }

        .btn-secondary {
            background-color: #233985ff;
        }

        .btn:hover, .btn-secondary:hover {
            opacity: 0.8;
        }
        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: #233985;
            color: white;
            text-align: center;
            padding: 15px 10px;
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
    <?php include 'menu.inc'; ?>

    <div class="containerSR">
        <h2>Student Registration</h2>

        <form action="processStudentRegistration.php" method="POST">
            <label for="studentName">Student First Name:</label><br>
            <input type="text" name="studentName" id="studentName" required><br><br>

            <label for="studentSurname">Student Surname:</label><br>
            <input type="text" name="studentSurname" id="studentSurname" required><br><br>

            <label for="dateOfBirth">Date of Birth:</label><br>
            <input type="date" name="dateOfBirth" id="dateOfBirth" required><br><br>

            <label for="gender">Gender:</label><br>
            <select name="gender" id="gender" required>
                <option value="">Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select><br><br>

            <label for="studentGrade">Grade:</label><br>
            <select name="studentGrade" required>
                <option value="">Select Grade</option>
                <option value="Grade 8">Grade 8</option>
                <option value="Grade 9">Grade 9</option>
                <option value="Grade 10">Grade 10</option>
                <option value="Grade 11">Grade 11</option>
                <option value="Grade 12">Grade 12</option>
            </select><br><br>

        <label for="lockerID">Select a Locker:</label><br>
        <select name="lockerID" id="lockerID" required>
            <option value="">-- Choose a Locker --</option>
            <?php foreach ($availableLockers as $locker): ?>
                <option value="<?= htmlspecialchars($locker['lockerID']) ?>">
                    <?= htmlspecialchars($locker['lockerNumber'] . ' (' . $locker['lockerLocation'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>


            <!-- Booking Date Field -->
    <label for="bookingDate">Locker Booking Date (January - June 2026):</label><br>
    <input type="date"
           name="bookingDate"
           id="bookingDate"
           min="2026-01-01"
           max="2026-06-30"
           value="2026-01-01"
           required><br><br>

      <input type="submit" value="Register Student" class="btn-secondary">

        </form>
    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
    </footer>
</body>
</html>
