<?php
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp-relay.brevo.com';       // Brevo SMTP
    $mail->SMTPAuth = true;
    $mail->Username = 'vunenebasa@gmail.com'; // e.g., yourname@gmail.com
    $mail->Password = 'k2vrdjSW8b31AcDz';     // Get this from Brevo
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    // From and to
    $mail->setFrom('vunenebasa@gmail.com', 'Locker System');
    $mail->addAddress('yinhlanthavela@gmail.com', 'Parent Name');

    // Content
    $mail->isHTML(false);
    $mail->Subject = 'Test Email from Locker System';
    $mail->Body    = "Hello,\n\nThis is a test email from PHPMailer using Brevo.\n\n- Locker System";

    $mail->send();
    echo "✅ Test email sent successfully using Brevo.";
} catch (Exception $e) {
    echo "❌ Email could not be sent. Error: {$mail->ErrorInfo}";
}
?>
