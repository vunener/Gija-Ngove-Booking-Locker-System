<?php
session_start();
require_once 'sessionManager.php';
requireLogin(['admin', 'parent']);
require_once 'gijangovelockersystem.php';
include 'menu.inc';

$parentID = $_SESSION['userID'] ?? null;
$success = false;
$error = "";


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// to fetch students and linked to the parent
$students = [];
$lockers = [];
$bookings = [];

if ($parentID) {
   
    $stmtStudents = $pdo->prepare("SELECT * FROM students WHERE parentID = ?");
    $stmtStudents->execute([$parentID]);
    $students = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

    // available lockers only 
    $stmtLockers = $pdo->prepare("SELECT lockerID, lockerNumber FROM lockers WHERE availability = 0 ORDER BY lockerNumber");
    $stmtLockers->execute();
    $lockers = $stmtLockers->fetchAll(PDO::FETCH_ASSOC);

    
    $stmtBookings = $pdo->prepare(" SELECT b.bookingID, b.status, b.bookingDate, b.approvalDate, b.comments, s.studentName, s.studentSurname, l.lockerNumber, a.adminUserName AS approverName
        FROM bookings b
        JOIN students s ON b.studentID = s.studentID
        JOIN lockers l ON b.lockerID = l.lockerID
        LEFT JOIN admins a ON b.approvedByAdminID = a.adminID
        WHERE s.parentID = ?
        ORDER BY b.bookingDate DESC
    ");
    $stmtBookings->execute([$parentID]);
    $bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);
}

// validate student 
function isValidStudentForParent(PDO $pdo, int $studentID, int $parentID): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE studentID = ? AND parentID = ?");
    $stmt->execute([$studentID, $parentID]);
    return $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $studentID = isset($_POST['studentID']) ? (int)$_POST['studentID'] : 0;
    $lockerID = isset($_POST['lockerID']) ? (int)$_POST['lockerID'] : 0;

    if ($studentID <= 0 || $lockerID <= 0) {
        $error = "Please select both a student and a locker.";
    } elseif (!isValidStudentForParent($pdo, $studentID, $parentID)) {
        $error = "Invalid student selection.";
    } else {
        // check active bookings 
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE studentID = ? AND status IN ('pending', 'waiting', 'approved')");
        $stmtCount->execute([$studentID]);
        $activeBookingCount = $stmtCount->fetchColumn();

        if ($activeBookingCount >= 2) {
            $error = "This student cannot have more than two active locker bookings.";
        } else {
            try {
               
                $pdo->beginTransaction();

                // to double-check locker availability 
                $stmtCheckLocker = $pdo->prepare("SELECT availability FROM lockers WHERE lockerID = ? FOR UPDATE");
                $stmtCheckLocker->execute([$lockerID]);
                $lockerAvail = $stmtCheckLocker->fetchColumn();

                if ($lockerAvail != 0) {
                    throw new Exception("Locker is no longer available. Please select a different locker.");
                }

                // to insert booking with status 'pending'
               $defaultComment = "Your booking is pending approval.";

                $stmtInsert = $pdo->prepare("INSERT INTO bookings (studentID, lockerID, status, bookingDate, comments)
                    VALUES (?, ?, 'pending', NOW(), ?)
                ");
                $stmtInsert->execute([$studentID, $lockerID, $defaultComment]);


                // to get student's grade
                $stmtGrade = $pdo->prepare("SELECT studentGrade FROM students WHERE studentID = ?");
                $stmtGrade->execute([$studentID]);
                $grade = $stmtGrade->fetchColumn();

                // it update the locker availability and assign student grade
                $stmtUpdateLocker = $pdo->prepare("UPDATE lockers SET availability = 1, studentGrade = ? WHERE lockerID = ?");
                $stmtUpdateLocker->execute([$grade, $lockerID]);

                $pdo->commit();
                $success = true;

                // refresh bookings
                $stmtBookings->execute([$parentID]);
                $bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC);

                // refresh available lockers
                $stmtLockers->execute();
                $lockers = $stmtLockers->fetchAll(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Locker Booking</title>
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
        .containerLB {
            background-color: #d1f0cbff;
            padding: 40px;
            margin: 70px auto 70px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            max-width: 900px;
            width: 90%;
            text-align: center;
            flex: 1;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #000;
            padding: 8px;
        }
        .available-lockers {
            margin: 20px 0;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .locker-badge {
            display: inline-block;
            background-color: #e0f7fa;
            color: #006064;
            padding: 5px 10px;
            margin: 4px;
            border-radius: 4px;
            font-weight: bold;
        }
        .success-message {
            color: green;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .error-message {
            color: red;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .btn {
            padding: 10px 15px;
            background-color: #233985ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #1a2a70ff;
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
        .btn-secondary:hover {
            background-color: #555;
        }
        form label {
            display: block;
            margin-top: 15px;
            font-weight: 600;
            text-align: left;
        }
        form select {
            padding: 6px;
            font-size: 1rem;
            width: 250px;
            max-width: 100%;
            margin-top: 5px;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            color: white;
            display: inline-block;
        }
        .status-pending { background-color: #f0ad4e; }  
        .status-approved { background-color: #5cb85c; } 
        .status-waiting { background-color: #5bc0de; }  
        .status-rejected { background-color: #d9534f; } 

        fieldset {
    border: 1px solid #ccc;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 6px;
    text-align: center;
}

fieldset label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    font-size: 1.1rem;
}

fieldset select {
    padding: 8px;
    font-size: 1rem;
    width: 60%;
    max-width: 300px;
    margin: 0 auto 10px auto;
    display: block;
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
        }
    </style>
</head>
<body>
<main>
    <div class="containerLB">
        <h2>Request a Locker</h2>

        <?php if ($success): ?>
            <div class="success-message" role="alert">
                Your booking request has been submitted. You will be notified once approved.
            </div>
        <?php elseif ($error): ?>
            <div class="error-message" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($students)): ?>
            <p>You must register a student before requesting a locker. <a href="studentRegistration.php">Register a student</a></p>
        <?php else: ?>
            <form method="POST" novalidate aria-describedby="formDesc">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <fieldset>
                    <legend>Select Student</legend>
                    <label for="studentID">Student:</label>
                    <select name="studentID" id="studentID" required>
                        <option value="" disabled selected>-- Select Student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= (int)$student['studentID'] ?>">
                                <?= htmlspecialchars($student['studentName'] . ' ' . $student['studentSurname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </fieldset>

                <fieldset>
                    <legend>Select Preferred Locker</legend>
                    <label for="lockerID">Locker:</label>
                    <select name="lockerID" id="lockerID" required <?= empty($lockers) ? 'disabled' : '' ?>>
                        <option value="" disabled selected>-- Select Locker --</option>
                        <?php foreach ($lockers as $locker): ?>
                            <option value="<?= (int)$locker['lockerID'] ?>">
                                Locker #<?= htmlspecialchars($locker['lockerNumber']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($lockers)): ?>
                        <p style="color: #a00;">No lockers are currently available.</p>
                    <?php endif; ?>
                </fieldset>

                <div style="text-align: center; margin-top: 20px;">
                    <input type="submit" class="btn" value="Submit Locker Booking" <?= empty($lockers) ? 'disabled' : '' ?>>
                </div>
            </form>
        <?php endif; ?>

        <?php if (!empty($lockers)): ?>
            <h3>Available Locker Numbers</h3>
            <div class="available-lockers" aria-live="polite" aria-atomic="true">
                <?php foreach ($lockers as $locker): ?>
                    <span class="locker-badge"><?= htmlspecialchars($locker['lockerNumber']) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($bookings)): ?>
            <h3>Your Locker Bookings</h3>
            <table aria-label="Locker bookings table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Booking Date</th>
                        <th>Approval Date</th>
                        <th>Approved By</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
    <?php foreach ($bookings as $b): ?>
        <?php
            $statusClass = 'status-' . strtolower($b['status']);
            $bookingDate = date('M d, Y H:i', strtotime($b['bookingDate']));
            $approvalDate = $b['approvalDate'] ? date('M d, Y H:i', strtotime($b['approvalDate'])) : '-';
            $approver = htmlspecialchars($b['approverName'] ?? '-');

            // to comment on the DB if available, otherwise it provide default status
            if (!empty(trim($b['comments']))) {
                $comments = htmlspecialchars($b['comments']);
            } else {
                switch (strtolower($b['status'])) {
                    case 'approved':
                        $comments = "Congratulations! Your booking was approved.";
                        break;
                    case 'rejected':
                        $comments = "Sorry, try again.";
                        break;
                    case 'pending':
                        $comments = "Your booking is pending approval.";
                        break;
                    case 'waiting':
                        $comments = "You are on the waiting list.";
                        break;
                    default:
                        $comments = "-";
                }
            }
        ?>
        <tr>
            <td><?= htmlspecialchars($b['studentName'] . ' ' . $b['studentSurname']) ?></td>
            <td><span class="status-badge <?= htmlspecialchars($statusClass) ?>"><?= ucfirst(htmlspecialchars($b['status'])) ?></span></td>
            <td><?= htmlspecialchars($bookingDate) ?></td>
            <td><?= $approvalDate ?></td>
            <td><?= $approver ?></td>
            <td><?= $comments ?></td>
        </tr>
    <?php endforeach; ?>
</tbody>

            </table>
        <?php endif; ?>

        <br>
        <a href="<?= ($_SESSION['userType'] ?? '') === 'admin' ? 'adminDashboard.php' : 'parentDashboard.php' ?>" class="btn-secondary" aria-label="Back to Dashboard">
            &larr; Back to Dashboard
        </a>
    </div>
</main>

<footer>
    <p style="text-align:center; margin: 30px 0;">&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
</footer>
</body>
</html>
