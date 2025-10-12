<?php
session_start();
include 'gijangovelockersystem.php';
include 'sessionManager.php';
requireLogin('parent');
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: studentRegistration.php");
    exit;
}


error_log('DEBUG: $_POST dump: ' . print_r($_POST, true));


$studentName    = trim($_POST['studentName'] ?? '');
$studentSurname = trim($_POST['studentSurname'] ?? '');
$studentGrade = ucwords(strtolower(trim($_POST['studentGrade'] ?? '')));
$dateOfBirth    = $_POST['dateOfBirth'] ?? '';
$gender         = $_POST['gender'] ?? '';
$lockerID       = $_POST['lockerID'] ?? null;
$bookingDate    = $_POST['bookingDate'] ?? null;
$parentID       = $_SESSION['userID'] ?? null;

// Validation
$errors = [];
if (!$studentName || !$studentSurname || !$dateOfBirth || !$gender || !$studentGrade || !$lockerID || !$bookingDate || !$parentID) {
    $errors[] = "All fields are required.";
}
if ($bookingDate < '2026-01-01' || $bookingDate > '2026-06-30') {
    $errors[] = "Booking date must be between Jan-June 2026.";
}

if (!in_array($studentGrade, ['Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'])) {
    $errors[] = "Invalid grade selected.";
}

if (!empty($errors)) {
    echo "<h2>Registration Failed</h2>";
    foreach ($errors as $error) {
        echo "<p style='color:red;'>" . htmlspecialchars($error) . "</p>";
    }
    echo "<a href='studentRegistration.php'>Go Back</a>";
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Lock the locker row and check availability
    $checkLockerStmt = $pdo->prepare("SELECT availability FROM lockers WHERE lockerID = ? FOR UPDATE");
    $checkLockerStmt->execute([$lockerID]);
    $availability = $checkLockerStmt->fetchColumn();

    if ((int)$availability !== 0 && $availability !== 'available') {
        throw new Exception("Locker is no longer available. Please choose a different one.");
    }

    // Generate new studentSchoolNumber
    $lastStudent = $pdo->query("SELECT MAX(studentSchoolNumber) AS maxNumber FROM students")->fetch();
    $lastNumber = (int)($lastStudent['maxNumber'] ?? 0);
    $newSchoolNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

    // Insert student (with NULL lockerID )
    $stmt = $pdo->prepare("INSERT INTO students (studentSchoolNumber, studentName, studentSurname, dateOfBirth, gender, studentGrade, parentID, lockerID) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$newSchoolNumber, $studentName, $studentSurname, $dateOfBirth, $gender, $studentGrade, $parentID, null]);
    $studentID = $pdo->lastInsertId();

    
   $gradeQuota = [
    'Grade 8'  => 10,
    'Grade 9'  => null,
    'Grade 10' => null,
    'Grade 11' => null,
    'Grade 12' => 5,
];
    if (!array_key_exists($studentGrade, $gradeQuota)) {
    throw new Exception("Invalid or unsupported student grade: " . htmlspecialchars($studentGrade));
    }

    $quota = $gradeQuota[$studentGrade];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b 
                        JOIN students s ON b.studentID = s.studentID 
                        WHERE s.studentGrade = ? AND b.status IN ('pending', 'approved')");
    $stmt->execute([$studentGrade]);
    $currentCount = (int)$stmt->fetchColumn();

    //$status = ($currentCount >= $quota) ? 'waiting' : 'pending';
    $status = 'waiting';


    // Insert booking and take actions based on status
    $adminID = 1; 
    define('LOCKER_AVAILABLE', 0);
    define('LOCKER_BOOKED', 1);

    if ($status === 'pending') {
        // Insert booking with lockerID
        $stmt = $pdo->prepare("INSERT INTO bookings (studentID, lockerID, status, bookingDate) VALUES (?, ?, ?, ?)");
        $stmt->execute([$studentID, $lockerID, $status, $bookingDate]);

        // Update locker availability
        $updateLockerStmt = $pdo->prepare("UPDATE lockers SET availability = ?, studentGrade = ? WHERE lockerID = ?");
        $updateLockerStmt->execute([LOCKER_BOOKED, $studentGrade, $lockerID]);

        // Update student record with lockerID
        $stmt = $pdo->prepare("UPDATE students SET lockerID = ? WHERE studentID = ?");
        $stmt->execute([$lockerID, $studentID]);

        // Notify parent about booking
        $notificationType = "Locker Booking Pending";
        $notificationMessage = "Your child $studentName $studentSurname has a locker booking pending approval.";
    } else {
        // Insert booking with NULL lockerID
        //$stmt = $pdo->prepare("INSERT INTO bookings (studentID, lockerID, status, bookingDate) VALUES (?, NULL, ?, ?)");
       //$stmt->execute([$studentID, $status, $bookingDate]);

        // Insert into waiting list
        $stmt = $pdo->prepare("INSERT INTO waitinglist (studentID, studentGrade, dateAdded, status, type, lockerID) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$studentID, $studentGrade, date('Y-m-d'), 'Pending', 'Email', null]);


        // DEBUG LOGGING: log the student grade just before insert
error_log("Student Grade at waitinglist insert: " . $studentGrade);



        // Notification for waiting list
        $notificationType = "Email";
        $notificationMessage = "Your child $studentName $studentSurname has been added to the locker waiting list.";
    }

    // Insert the notification
        $type = 'Email'; 
        $notificationStatus = 'pending'; 

        // Adjust status based on booking outcome
        if ($status === 'waiting') {
            $notificationStatus = 'available'; 
        } elseif ($status === 'approved') {
            $notificationStatus = 'completed';
        } elseif ($status === 'rejected') {
            $notificationStatus = 'rejected';
        } 

    $stmt = $pdo->prepare("INSERT INTO notifications (parentID, adminID, type, message, dateSent, status, title) 
                           VALUES (?, ?, ?, ?, NOW(), 'pending', 'Student Added to Waiting List')");
    $stmt->execute([$parentID, $adminID, $type, $notificationMessage]);

    $stmt = $pdo->prepare("UPDATE notifications 
    SET status = 'completed' 
    WHERE parentID = ? 
    AND type = 'Payment' 
    AND status = 'pending'
    ORDER BY dateSent DESC
    LIMIT 1");
    $stmt->execute([$parentID]);

    // Email sending logic
    $stmt = $pdo->prepare("SELECT parentEmailAddress FROM parents WHERE parentID = ?");
    $stmt->execute([$parentID]);
    $parentEmailAddress = $stmt->fetchColumn();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'vunenebasa@gmail.com';
        $mail->Password = 'gtmj ytjl gkhi ftbb'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('vunenebasa@gmail.com', 'Administrator');

        // Send to parent
        $mail->addAddress($parentEmailAddress);
        $mail->isHTML(false);
        $mail->Subject = 'Student Registration Update';
        $mail->Body = ($status === 'waiting')
            ? "Dear Parent,\n\nThank you for registering your child. The student has been added to the waiting list.\n\nPlease submit payment and proof of payment via the portal.\n\nBest regards,\nAdministrator"
            : "Dear Parent,\n\nThank you for registering your child. The locker booking is pending and under review.\n\nYou will receive further communication shortly.\n\nBest regards,\nAdministrator";
        $mail->send();

        // Send to admin
        $mail->clearAddresses();
        $mail->addAddress('vunenebasa@gmail.com');
        $mail->Subject = ($status === 'waiting') ? 'New Student on Waiting List' : 'New Pending Locker Booking';
        $mail->Body = "Student: $studentName $studentSurname\nGrade: $studentGrade\nStatus: $status\n\nPlease review this in the admin panel.";
        $mail->send();

    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
    }

    // Commit transaction
    $pdo->commit();

    header("Location: studentInfo.php?registered=1&studentID=" . urlencode($studentID));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2>Error</h2>";
    echo "<p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<a href='studentRegistration.php'>Go Back</a>";
}
?>
