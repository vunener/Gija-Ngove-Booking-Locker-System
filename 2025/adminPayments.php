<?php
include 'sessionManager.php';
requireLogin('admin');

include 'gijangovelockersystem.php';
include 'menu.inc';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$errors = [];
$success = '';


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// It handle form submissions, update status, allocate locker, reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paymentID'], $_POST['action'])) {

    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // it validate paymentID as int
        $paymentID = filter_input(INPUT_POST, 'paymentID', FILTER_VALIDATE_INT);
        if ($paymentID === false || $paymentID === null) {
            $errors[] = "Invalid payment ID.";
        } else {
            $action = $_POST['action'];

            try {
                if ($action === 'update_status' && isset($_POST['newStatus'])) {
                    $newStatus = $_POST['newStatus'];

                    if (!in_array($newStatus, ['Available', 'Pending', 'Completed', 'Rejected'])) {
                        throw new Exception("Invalid status selected.");
                    }

                    // It update payment status
                    $stmt = $pdo->prepare("UPDATE payments SET status = ? WHERE paymentID = ?");
                    $stmt->execute([$newStatus, $paymentID]);

                    // If status updated to Completed, update booking status as well
                    if ($newStatus === 'Completed') {
                        $stmt2 = $pdo->prepare("SELECT bookingID FROM payments WHERE paymentID = ?");
                        $stmt2->execute([$paymentID]);
                        $bookingID = $stmt2->fetchColumn();

                        if ($bookingID) {
                            $stmt3 = $pdo->prepare("UPDATE bookings SET status = 'Approved' WHERE bookingID = ?");
                            $stmt3->execute([$bookingID]);
                        }


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

                            $stmt = $pdo->prepare("SELECT par.parentEmailAddress FROM payments p 
                                                JOIN bookings b ON p.bookingID = b.bookingID
                                                JOIN students s ON b.studentID = s.studentID
                                                JOIN parents par ON s.parentID = par.parentID
                                                WHERE p.paymentID = ?");
                            $stmt->execute([$paymentID]);
                            $parentEmailAddress = $stmt->fetchColumn();

                            if ($parentEmailAddress) {
                                $mail->addAddress($parentEmailAddress);
                            } else {
                                $mail->addAddress('yinhlanthavela@gmail.com');
                            }

                            $mail->isHTML(false);
                            $mail->Subject = 'Payment Confirmation';
                            $mail->Body = "Dear Parent,\n\nYour payment has been confirmed. Your child's locker application is now being processed.\n\nBest regards,\nAdministrator";

                            $mail->send();
                        } catch (Exception $e) {
                            error_log("Email error: " . $mail->ErrorInfo);
                        }
                    }


                    $success = "Payment #{$paymentID} status updated to {$newStatus}.";

                } elseif ($action === 'allocate_locker') {
                    

                    // It get the bookingID
                    $stmt = $pdo->prepare("SELECT bookingID FROM payments WHERE paymentID = ?");
                    $stmt->execute([$paymentID]);
                    $bookingID = $stmt->fetchColumn();

                    if (!$bookingID) throw new Exception("Booking not found.");

                    // It check if locker already allocated
                    $stmt = $pdo->prepare("SELECT lockerID FROM bookings WHERE bookingID = ?");
                    $stmt->execute([$bookingID]);
                    $lockerID = $stmt->fetchColumn();

                    if ($lockerID) {
                        throw new Exception("Locker already allocated.");
                    }

                    // it find available locker
                    $stmt = $pdo->prepare("SELECT lockerID FROM lockers WHERE availability = 0 LIMIT 1");
                    $stmt->execute();
                    $newLockerID = $stmt->fetchColumn();

                    if (!$newLockerID) throw new Exception("No lockers available.");

                    // It allocate locker
                    $stmt = $pdo->prepare("UPDATE lockers SET availability = 1 WHERE lockerID = ?");
                    $stmt->execute([$newLockerID]);


                    // It assign locker to booking
                    $adminID = $_SESSION['adminID'] ?? null;
                    if (!$adminID) throw new Exception("Admin ID missing.");

                    $stmt = $pdo->prepare("UPDATE bookings
                        SET lockerID = ?, status = 'Locker Allocated', approvalDate = NOW(), approvedByAdminID = ?
                        WHERE bookingID = ?
                    ");
                    $stmt->execute([$newLockerID, $adminID, $bookingID]);

                    $success = "Locker #{$newLockerID} allocated to booking.";

                } elseif ($action === 'reject') {
                    // it reject payment
                    $stmt = $pdo->prepare("UPDATE payments SET status = 'Rejected' WHERE paymentID = ?");
                    $stmt->execute([$paymentID]);
                    $success = "Payment #{$paymentID} rejected.";
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// it fetch all bookings with payment info
try { $stmt = $pdo->prepare("SELECT b.bookingID, b.lockerID, l.lockerNumber, b.status 
AS bookingStatus, b.approvalDate,  s.studentID, s.studentName, s.studentSurname, s.studentGrade, par.parentUsername, p.paymentID, p.amount, p.status 
AS paymentStatus, p.proofOfPayment, p.paymentDate
FROM bookings b
JOIN students s ON b.studentID = s.studentID
JOIN parents par ON s.parentID = par.parentID
LEFT JOIN payments p ON b.bookingID = p.bookingID
LEFT JOIN lockers l ON b.lockerID = l.lockerID  
ORDER BY b.bookingDate DESC
");


    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to retrieve bookings and payments: " . $e->getMessage();
    $records = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Payments & Locker Allocation | Gija‑Ngove</title>
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
        .containerAP {
            background-color: #d1f0cbff;
            padding: 40px;
            padding-bottom: 60px;
            margin: 70px auto auto auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 1500px;
            width: 80%;
            text-align: center;
            flex: 1;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
        }
        .custom-table th, .custom-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .custom-table thead {
            background-color: #3D7DC1;
            color: white;
        }
        form.inline-form {
            display: inline-block;
            margin-top: 5px;
        }
        select:disabled, button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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
        .btn-secondary:hover {
            background-color: #66a0cdff;
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
    <div class="containerAP">
        <h2>All Bookings & Payment Status</h2>

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


                  <?php if (isset($_GET['emailSent'])): ?>
                <?php if ($_GET['emailSent'] == '1'): ?>
                    <div class="alert success">Reminder email sent to parent successfully.</div>
                <?php else: ?>
                    <div class="alert error">Failed to send email: <?= htmlspecialchars($_GET['error'] ?? 'Unknown error') ?></div>
                <?php endif; ?>
            <?php endif; ?>



        <?php if ($records): ?>
            <table class="custom-table" border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse: collapse;">
                
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Grade</th>
                        <th>Parent</th>
                        <th>Amount</th>
                        <th>Payment Status</th>
                        <th>Proof</th>
                        <th>Payment Date</th>
                        <th>Locker Number</th>
                        <th>LockerID Allocated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $rec): ?>
                        <?php
                            $proofExists = !empty($rec['proofOfPayment']) && file_exists(__DIR__ . '/' . $rec['proofOfPayment']);
                            $effectiveStatus = $rec['paymentStatus'] ?? 'Not Paid';
                            $disableDropdown = in_array($effectiveStatus, ['Completed', 'Rejected']);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($rec['studentName'] . ' ' . $rec['studentSurname']) ?></td>
                            <td><?= htmlspecialchars($rec['studentGrade']) ?></td>
                            <td><?= htmlspecialchars($rec['parentUsername']) ?></td>
                            <td>R<?= number_format($rec['amount'] ?? 0, 2) ?></td>
                            <td>
                                <?php
                                $statusLower = strtolower($effectiveStatus);
                                if ($statusLower === 'rejected') {
                                    echo '<span style="color: red; font-weight: bold;">Rejected</span>';
                                } elseif ($statusLower === 'completed') {
                                    echo '<span style="color: green;">Completed</span>';
                                } else {
                                    echo htmlspecialchars($effectiveStatus);
                                }
                                ?>
                            </td>

                            <td>
                                <?php if ($proofExists): ?>
                                    <a href="<?= htmlspecialchars($rec['proofOfPayment']) ?>" target="_blank" rel="noopener noreferrer">Received</a>
                                <?php else: ?>
                                    Not Received
                                <?php endif; ?>
                            </td>
                            <td><?= $rec['paymentDate'] ? htmlspecialchars($rec['paymentDate']) : '-' ?></td>
                            <td><?= !empty($rec['lockerNumber']) ? htmlspecialchars($rec['lockerNumber']) : 'None' ?></td>
                            <td><?= !empty($rec['lockerID']) ? htmlspecialchars($rec['lockerID']) : 'None' ?></td>
                            <td>
                                <?php if (!empty($rec['paymentID'])): ?>
                                    <form method="POST" style="display:flex; gap:5px; align-items:center; flex-wrap: wrap;">
                                        <input type="hidden" name="paymentID" value="<?= (int)$rec['paymentID'] ?>" />
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <select name="newStatus" required <?= $disableDropdown ? 'disabled' : '' ?>>
                                            <option value="Available" <?= $effectiveStatus === 'Available' ? 'selected' : '' ?>>Available</option>
                                            <option value="Pending" <?= $effectiveStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Completed" <?= $effectiveStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="Rejected" <?= $effectiveStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                        <button type="submit" name="action" value="update_status" onclick="return confirm('Update payment status?');" <?= $disableDropdown ? 'disabled' : '' ?>>Update</button>
                                    </form>

                                    <?php if ($effectiveStatus === 'Completed' && empty($rec['lockerID'])): ?>
                                        <!-- Show Allocate Locker button -->
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="paymentID" value="<?= (int)$rec['paymentID'] ?>" />
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <button type="submit" name="action" value="allocate_locker" onclick="return confirm('Allocate locker?');">Allocate Locker</button>
                                        </form>
                                    <?php elseif ($effectiveStatus === 'Completed' && !empty($rec['lockerID'])): ?>
                                        <div style="margin-top:5px;"><em>Locker Allocated</em></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                <form method="POST" action="sendReminderEmail.php" style="display:inline;">
                                    <input type="hidden" name="student_id" value="<?= (int)$rec['studentID'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <button type="submit" onclick="return confirm('Send email to parent?');">Send Email</button>
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
        <a href="adminDashboard.php" class="btn-secondary">&larr; Back to Dashboard</a>
    </div>
</main>
<footer>
   <p>&copy; <?= date('Y') ?> Gija-Ngove Locker System. All rights reserved.</p>
</footer>
</body>
</html>
