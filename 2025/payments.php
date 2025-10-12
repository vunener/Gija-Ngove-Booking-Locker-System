<?php
require_once 'sessionManager.php';
requireLogin('parent');

require_once 'gijangovelockersystem.php';
require_once 'menu.inc';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$parentID = $_SESSION['parentID'] ?? null;
$errors = [];
$success = false;

if (!$parentID) {
    $errors[] = "Parent ID not found in session.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bookingID'])) {
    $bookingID = (int)$_POST['bookingID'];

    // Validate booking ownership by parent
    $stmt = $pdo->prepare("SELECT b.bookingID FROM bookings b
        JOIN students s ON b.studentID = s.studentID
        WHERE b.bookingID = ? AND s.parentID = ?");
    $stmt->execute([$bookingID, $parentID]);
    $validBooking = $stmt->fetch();

    if (!$validBooking) {
        $errors[] = "Invalid booking ID.";
    }

    // Handle file upload if no booking errors
    if (empty($errors)) {
        if (isset($_FILES['proofOfPayment']) && $_FILES['proofOfPayment']['error'] === UPLOAD_ERR_OK) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
            $maxFileSize = 5 * 1024 * 1024; // 5 MB

            $fileTmpPath = $_FILES['proofOfPayment']['tmp_name'];
            $fileName = $_FILES['proofOfPayment']['name'];
            $fileSize = $_FILES['proofOfPayment']['size'];
            $fileType = mime_content_type($fileTmpPath);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Validate MIME type and extension
            if (!in_array($fileType, $allowedMimeTypes) || !in_array($fileExt, $allowedExtensions)) {
                $errors[] = "Only JPG, PNG, or PDF files are allowed.";
            } elseif ($fileSize > $maxFileSize) {
                $errors[] = "File size must be less than 5MB.";
            } else {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Generate unique sanitized filename
                $uniqueName = uniqid('proof_') . '.' . $fileExt;
                $targetPath = $uploadDir . $uniqueName;

                if (move_uploaded_file($fileTmpPath, $targetPath)) {
                    try {
                        // Check if payment exists for booking
                        $check = $pdo->prepare("SELECT paymentID FROM payments WHERE bookingID = ?");
                        $check->execute([$bookingID]);
                        $existingPayment = $check->fetch();

                        if ($existingPayment) {
                            // Update existing payment record
                            $stmt = $pdo->prepare("UPDATE payments
                                SET proofOfPayment = ?, status = 'completed', paymentDate = NOW()
                                WHERE bookingID = ?");
                            $stmt->execute([$targetPath, $bookingID]);
                        } else {
                            // Insert new payment record, fixed amount 100.00
                            $stmt = $pdo->prepare("INSERT INTO payments (bookingID, amount, paymentDate, status, proofOfPayment)
                            VALUES (?, ?, NOW(), ?, ?)");
                             $stmt->execute([$bookingID, 100.00, 'completed', $targetPath]);

                        }

                         
                        

                        // Fetch student info for email
                        $studentInfoStmt = $pdo->prepare("SELECT s.studentName, s.studentSurname, s.studentGrade, p.parentEmailAddress
                            FROM bookings b 
                            JOIN students s ON b.studentID = s.studentID 
                            JOIN parents p ON s.parentID = p.parentID
                            WHERE b.bookingID = ?");
                        $studentInfoStmt->execute([$bookingID]);
                        $student = $studentInfoStmt->fetch(PDO::FETCH_ASSOC);

                        $studentName = $student['studentName'] ?? 'Unknown';
                        $studentSurname = $student['studentSurname'] ?? '';
                        $studentGrade = $student['studentGrade'] ?? '';
                        $parentEmailAddress = $student['parentEmailAddress'] ?? null;

                        $proofLink = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/' . $targetPath;

                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host = 'smtp.gmail.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = 'vunenebasa@gmail.com';
                            $mail->Password = 'gtmj ytjl gkhi ftbb'; // App password, correct format
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = 587;

                            $mail->setFrom('vunenebasa@gmail.com', 'Locker System');

                            // Optional: notify parent
                            if ($parentEmailAddress) {
                                $mail->addAddress($parentEmailAddress);
                                $mail->isHTML(false);
                                $mail->Subject = 'Proof of Payment Received';
                                $mail->Body = "Dear Parent,\n\nWe have received your proof of payment for Booking ID: $bookingID.\n\nThank you.\n\nBest regards,\nAdministrator";
                                $mail->send();
                                $mail->clearAddresses();
                            }

                            // Send notification to admin
                            $mail->addAddress('vunenebasa@gmail.com');
                            $mail->Subject = 'New Proof of Payment Uploaded';
                            $mail->Body = "A parent has uploaded a proof of payment.\n\nStudent: $studentName $studentSurname\nGrade: $studentGrade\nBooking ID: $bookingID\n\nProof Link: $proofLink\n\nPlease review this in the admin panel.";
                            $mail->send();

                        } catch (Exception $e) {
                            error_log("Email sending failed: " . $mail->ErrorInfo);
                        }



                        $success = true;
                    } catch (PDOException $e) {
                        $errors[] = "Database error: " . $e->getMessage();
                    }
                } else {
                    $errors[] = "Failed to move uploaded file.";
                }
            }
        } else {
            // Handle file upload errors
            $uploadError = $_FILES['proofOfPayment']['error'] ?? UPLOAD_ERR_NO_FILE;
            switch ($uploadError) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = "Uploaded file exceeds the allowed size.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = "Please upload a proof of payment file.";
                    break;
                default:
                    $errors[] = "File upload error. Please try again.";
            }
        }
    }
}

// Fetch payment records for this parent
    $payments = [];
    if ($parentID) {
        $stmt = $pdo->prepare("SELECT p.*, s.studentName, s.studentSurname, s.studentGrade, b.bookingID
    FROM students s
    JOIN (
        SELECT MIN(bookingID) as bookingID, studentID
        FROM bookings
        GROUP BY studentID) b_min ON s.studentID = b_min.studentID
    JOIN bookings b ON b.bookingID = b_min.bookingID
    LEFT JOIN payments p ON b.bookingID = p.bookingID
    WHERE s.parentID = ?
    ORDER BY COALESCE(p.paymentDate, b.bookingID) DESC
        ");
    $stmt->execute([$parentID]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Locker Payment Status</title>
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
        .containerP {
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
         .btn-secondary {
            text-decoration: none;
            background-color:  #233985ff;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 20px;
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid #333;
        }
        th, td {
            padding: 10px;
        }
        .success-message {
            color: green;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .error-messages {
            color: red;
            margin-bottom: 15px;
        }
        .upload-form {
            display: inline-block;
        }
    </style>
</head>
<body>
<main>
    <div class="containerP">
        <h2>Locker Payment Status</h2>

        <?php if ($success): ?>
            <div class="success-message">Proof of payment uploaded successfully.</div>
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

        <?php if (!empty($payments)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Grade</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Proof of Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $payment):
                    $filePath = !empty($payment['proofOfPayment']) ? __DIR__ . '/' . $payment['proofOfPayment'] : '';
                    $proofExists = !empty($payment['proofOfPayment']) && file_exists($filePath);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($payment['studentName'] . ' ' . $payment['studentSurname']) ?></td>
                        <td><?= htmlspecialchars($payment['studentGrade']) ?></td>
                        <td>R<?= number_format($payment['amount'] ?? 0, 2) ?></td>
                        <td><?= !empty($payment['status']) ? htmlspecialchars($payment['status']) : 'Not Paid' ?></td>
                        <td>
                            <?php if ($proofExists): ?>
                                <a href="<?= htmlspecialchars($payment['proofOfPayment']) ?>" target="_blank">View Proof</a>
                            <?php else: ?>
                                Not submitted
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$proofExists): ?>
                                <form method="POST" enctype="multipart/form-data" class="upload-form">
                                    <input type="hidden" name="bookingID" value="<?= htmlspecialchars($payment['bookingID']) ?>">
                                    <input type="file" name="proofOfPayment" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <button type="submit">Upload</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" enctype="multipart/form-data" class="upload-form">
                                    <input type="hidden" name="bookingID" value="<?= htmlspecialchars($payment['bookingID']) ?>">
                                    <input type="file" name="proofOfPayment" accept=".jpg,.jpeg,.png,.pdf" required>
                                    <button type="submit">Replace Proof</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No bookings or payment records found.</p>
        <?php endif; ?>

        <br>
        <a href="parentDashboard.php" class="btn-secondary">&larr; Back to Dashboard</a>
    </div>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Gija-Ngove Locker System. All rights reserved.</p>
</footer>
</body>
</html>
