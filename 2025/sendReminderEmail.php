<?php
require_once 'sessionManager.php';
requireLogin('admin');

require_once 'gijangovelockersystem.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = false;

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'], $_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $studentID = (int)$_POST['student_id'];

        try {
            // Fetch parent email for the given student
            $stmt = $pdo->prepare("SELECT p.parentEmailAddress, p.parentUsername, s.studentName
                FROM students s
                JOIN parents p ON s.parentID = p.parentID
                WHERE s.studentID = ?");
            $stmt->execute([$studentID]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                throw new Exception("Parent email not found for the selected student.");
            }

            // Prepare and send email
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'vunenebasa@gmail.com';
            $mail->Password = 'gtmj ytjl gkhi ftbb';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('vunenebasa@gmail.com', 'Administrator');
            $mail->addAddress($parent['parentEmailAddress']);

            $mail->isHTML(false);
            $mail->Subject = 'Locker Payment Reminder';
            $mail->Body = "Dear {$parent['parentUsername']},\n\n"
                . "This is a reminder to upload proof of payment for your child's locker application.\n"
                . "Student: {$parent['studentName']}\n"
                . "Amount Due: R100.00\n\n"
                . "Please log in to your account and submit the payment as soon as possible.\n\n"
                . "Best regards,\nAdministrator";

            $mail->send();
            $success = true;

        } catch (Exception $e) {
            $errors[] = "Mailer Error: " . $mail->ErrorInfo;
        } catch (PDOException $e) {
            $errors[] = "Database Error: " . $e->getMessage();
        }
    }
} else {
    $errors[] = "Invalid request.";
}

// Redirect back with result
if ($success) {
    header("Location: adminPayments.php?emailSent=1");
} else {
    $errMsg = urlencode($errors[0] ?? 'Unknown error');
    header("Location: adminPayments.php?emailSent=0&error={$errMsg}");
}
exit;
