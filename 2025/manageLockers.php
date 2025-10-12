<?php
session_start();

// Include dependencies and setup
require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include 'gijangovelockersystem.php';
include 'menu.inc';

// Only admins allowed
if (!isset($_SESSION['userType']) || $_SESSION['userType'] !== 'admin') {
    http_response_code(403);
    exit('Access denied. Admin only.');
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sendNotificationEmail($to, $subject, $body) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME');
        $mail->Password   = getenv('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($mail->Username, 'Locker System');
        $mail->addAddress($to);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}

// Helper to sanitize integers
function sanitizeInt($value) {
    return filter_var($value, FILTER_VALIDATE_INT);
}

$adminID = $_SESSION['userID'] ?? null;
$dateSent = date('Y-m-d H:i:s');
$status = 'sent';
$successMessage = '';
$errorMessage = '';

// Process POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_locker') {
            $lockerNumber = trim($_POST['lockerNumber'] ?? '');

            if ($lockerNumber === '' || !preg_match('/^\d+$/', $lockerNumber)) {
                throw new Exception('Locker number must be a non-empty numeric value.');
            }

            // Check if locker number already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lockers WHERE lockerNumber = ?");
            $stmt->execute([$lockerNumber]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Locker number already exists.');
            }

            // Insert new locker (availability default 0 = available)
            $stmt = $pdo->prepare("INSERT INTO lockers (lockerNumber, availability) VALUES (?, 0)");
            $stmt->execute([$lockerNumber]);

            $successMessage = "Locker #$lockerNumber added successfully.";

        } else {
            // Existing actions (send_waiting_notification, request_payment, approve_application, confirm_payment, finalize_booking)
            $studentID = sanitizeInt($_POST['studentID'] ?? null);
            $parentID = sanitizeInt($_POST['parentID'] ?? null);
            $paymentID = sanitizeInt($_POST['paymentID'] ?? null);
            $assignedLockerID = sanitizeInt($_POST['lockerID'] ?? null);

            $actionsRequiringStudentAndParent = ['send_waiting_notification', 'request_payment', 'approve_application', 'finalize_booking'];
            if (in_array($action, $actionsRequiringStudentAndParent, true) && ($studentID === false || $parentID === false)) {
                throw new Exception('Invalid student or parent ID.');
            }


            if ($action === 'send_waiting_notification') {
                $type = 'waiting';
                $message = "Dear Parent,\n\nYour child's locker application is currently on the waiting list.\n\nThank you.";
                $parentEmail = filter_var($_POST['parentEmail'] ?? '', FILTER_VALIDATE_EMAIL);

                if ($parentEmail) {
                    if (sendNotificationEmail($parentEmail, "Locker Application - Waiting List", $message)) {
                        $successMessage = "Waiting list email sent to $parentEmail";
                    } else {
                        $errorMessage = "Failed to send waiting list email.";
                    }
                } else {
                    $errorMessage = "Invalid parent email.";
                }

                $stmt = $pdo->prepare("INSERT INTO notifications (parentID, adminID, type, message, status, dateSent) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$parentID, $adminID, $type, $message, $status, $dateSent]);

                // Confirm payment
                $stmt = $pdo->prepare("UPDATE payments SET status = 'confirmed' WHERE paymentID = ?");
                $stmt->execute([$paymentID]);

                // Fetch parent email
                $emailQuery = $pdo->prepare("
                    SELECT p.parentEmailAddress AS parentEmail
                    FROM payments pay
                    JOIN bookings b ON pay.bookingID = b.bookingID
                    JOIN students s ON b.studentID = s.studentID
                    JOIN parents p ON s.parentID = p.parentID
                    WHERE pay.paymentID = ?
                ");
                $emailQuery->execute([$paymentID]);
                $parentEmail = $emailQuery->fetchColumn();

                // Send email notification
                if ($parentEmail && filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
                    $subject = "Locker Payment Confirmed";
                    $message = "Dear Parent,\n\nYour locker payment (ID: $paymentID) has been confirmed. Thank you!\n\nGija-Ngove Locker System";

                    if (sendNotificationEmail($parentEmail, $subject, $message)) {
                        $successMessage .= " Confirmation email sent to $parentEmail.";
                    } else {
                        $errorMessage = "Payment confirmed, but failed to send confirmation email.";
                    }
                } else {
                    $errorMessage = "Payment confirmed, but no valid parent email found.";
                }


            } elseif ($action === 'request_payment') {
                $type = 'payment';
                $message = "Your application was successful. Please make a payment of R100.";

                $stmt = $pdo->prepare("INSERT INTO notifications (parentID, adminID, type, message, status, dateSent) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$parentID, $adminID, $type, $message, $status, $dateSent]);

                $stmt = $pdo->prepare("UPDATE waitinglist SET status = 'awaiting_payment' WHERE studentID = ?");
                $stmt->execute([$studentID]);

                $successMessage = "Payment request sent.";

           } elseif ($action === 'approve_application') {
           // Update waiting list status
                $stmt = $pdo->prepare("UPDATE waitinglist SET status = 'approved' WHERE studentID = ?");
                $stmt->execute([$studentID]);

                // Define email content
                $type = 'approved';
                $title = 'Application Approved';
                $message = "Dear Parent,\n\nYour child's locker application has been approved successfully.\n\nThank you.";
                $status = 'sent';

                // Get parent email
                $stmt = $pdo->prepare("SELECT parentEmailAddress FROM parents WHERE parentID = ?");
                $stmt->execute([$parentID]);
                $parentEmail = $stmt->fetchColumn();

                // Send email
                if ($parentEmail && filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
                    if (sendNotificationEmail($parentEmail, $title, $message)) {
                        $successMessage = "Application approved and email sent to $parentEmail.";
                    } else {
                        $errorMessage = "Application approved, but failed to send email.";
                    }
                } else {
                    $errorMessage = "Application approved, but invalid parent email.";
                }



            // Log notification
            //$stmt = $pdo->prepare("INSERT INTO notifications (parentID, adminID, type, message, status, dateSent) VALUES (?, ?, ?, ?, ?, ?)");
            //$stmt->execute([$parentID, $adminID, $type, $message, $status, $dateSent]);
            $stmt = $pdo->prepare("INSERT INTO notifications (parentID, title, adminID, type, message, status, dateSent) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$parentID, $title, $adminID, $type, $message, $status, $dateSent]);


                    } elseif ($action === 'confirm_payment') {
            $paymentID = $_POST['paymentID'] ?? false;

            if (!$paymentID || !is_numeric($paymentID)) {
                throw new Exception('Invalid payment ID.');
            }

            // 1. Update payment status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'confirmed' WHERE paymentID = ?");
            $stmt->execute([$paymentID]);

            $successMessage = "Payment #$paymentID confirmed.";

            // 2. Fetch parent email and ID
            $emailQuery = $pdo->prepare("
                SELECT p.parentEmailAddress AS parentEmail, p.parentID
                FROM payments pay
                JOIN bookings b ON pay.bookingID = b.bookingID
                JOIN students s ON b.studentID = s.studentID
                JOIN parents p ON s.parentID = p.parentID
                WHERE pay.paymentID = ?
            ");
            $emailQuery->execute([$paymentID]);
            $parentRow = $emailQuery->fetch(PDO::FETCH_ASSOC);

            if ($parentRow && filter_var($parentRow['parentEmail'], FILTER_VALIDATE_EMAIL)) {
                $parentEmail = $parentRow['parentEmail'];
                $parentID = $parentRow['parentID'];

                // 3. Send confirmation email
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'vunenebasa@gmail.com';
                    $mail->Password = 'gtmj ytjl gkhi ftbb'; // WARNING: Do not hardcode credentials in production!
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('vunenebasa@gmail.com', 'Administrator');
                    $mail->addAddress($parentEmail);

                    $mail->isHTML(false);
                    $mail->Subject = "Payment #$paymentID confirmed";
                    $mail->Body = "Dear Parent,\n\nYour payment (ID: $paymentID) has been confirmed.\n\nThank you.";

                    $mail->send();

                    // 4. Log a notification
                    $type = 'payment_confirmed';
                    $statusNotif = 'pending';
                    $dateSent = date('Y-m-d H:i:s');

                    $stmt2 = $pdo->prepare("
                        INSERT INTO notifications (parentID, adminID, type, message, status, dateSent)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt2->execute([$parentID, $adminID, $type, "Payment #$paymentID confirmed", $statusNotif, $dateSent]);

                } catch (Exception $e) {
                    error_log("Email error: " . $e->getMessage());
                    $errorMessage = "Payment confirmed, but email failed.";
                }
            } else {
                $errorMessage = "Payment confirmed, but parent's email not found or invalid.";
            }


                $stmt = $pdo->prepare("UPDATE payments SET status = 'confirmed' WHERE paymentID = ?");
                $stmt->execute([$paymentID]);

                $successMessage = "Payment #$paymentID confirmed.";

            } elseif ($action === 'finalize_booking') {
                if ($assignedLockerID === false) {
                    throw new Exception('Invalid locker ID.');
                }

                $pdo->beginTransaction();

                // Get student grade
                $gradeStmt = $pdo->prepare("SELECT studentGrade FROM students WHERE studentID = ?");
                $gradeStmt->execute([$studentID]);
                $studentGrade = $gradeStmt->fetchColumn();

                $gradeLimits = [
                    'Grade 8' => 10,
                    'Grade 12' => 5,
                    // Add other grades as needed
                ];

                // Check grade booking limits
                if (array_key_exists($studentGrade, $gradeLimits)) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN students s ON b.studentID = s.studentID WHERE s.studentGrade = ? AND LOWER(b.status) = 'completed'");
                    $stmt->execute([$studentGrade]);
                    $currentCount = (int)$stmt->fetchColumn();

                    if ($currentCount >= $gradeLimits[$studentGrade]) {
                        throw new Exception("All lockers for {$studentGrade} are fully booked.");
                    }
                }

                // Check locker availability
                $lockerCheck = $pdo->prepare("SELECT availability FROM lockers WHERE lockerID = ?");
                $lockerCheck->execute([$assignedLockerID]);
                $availability = $lockerCheck->fetchColumn();

                if ((int)$availability !== 0) {
                    throw new Exception("Locker already booked.");
                }

                // Check if locker already assigned in bookings with completed status
                $lockerBookedStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE lockerID = ? AND status = 'completed'");
                $lockerBookedStmt->execute([$assignedLockerID]);
                if ((int)$lockerBookedStmt->fetchColumn() > 0) {
                    throw new Exception("Locker already assigned.");
                }

                // Check if student already has a locker booked
                $studentLockerStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE studentID = ? AND status = 'completed'");
                $studentLockerStmt->execute([$studentID]);
                if ((int)$studentLockerStmt->fetchColumn() > 0) {
                    throw new Exception("Student already has a locker.");
                }

                // Finalize booking
                $comments = "Locker assigned and payment confirmed.";
                $insertBookingStmt = $pdo->prepare("INSERT INTO bookings (studentID, lockerID, status, bookingDate, approvalDate, approvedByAdminID, comments) VALUES (?, ?, 'completed', CURDATE(), NOW(), ?, ?)");
                $insertBookingStmt->execute([$studentID, $assignedLockerID, $adminID, $comments]);

                $updateLockerStmt = $pdo->prepare("UPDATE lockers SET availability = 1 WHERE lockerID = ?");
                $updateLockerStmt->execute([$assignedLockerID]);

               $updateWaitingListStmt = $pdo->prepare("UPDATE waitinglist  SET status = ?, type = ?, lockerID = ? 
                    WHERE studentID = ? ");
                $updateWaitingListStmt->execute(['approved', 'converted', $assignedLockerID, $studentID]);


                $type = 'approved';
                $message = "Your child has been allocated a locker. Thank you!";
                $notificationStmt = $pdo->prepare("INSERT INTO notifications (parentID, adminID, type, message, status, dateSent) VALUES (?, ?, ?, ?, ?, ?)");
                $notificationStmt->execute([$parentID, $adminID, $type, $message, $status, $dateSent]);

                $pdo->commit();
                $successMessage = "Locker booking finalized.";
            } else {
                $errorMessage = 'Invalid action.';
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error processing action '$action': " . $e->getMessage());
        $errorMessage = $e->getMessage(); // For more user-friendly feedback, replace with generic message
    }
}

// Fetch waiting list students
$waitingList = $pdo->prepare(
    "SELECT wl.*, s.studentName, s.studentSurname, s.studentGrade, s.parentID, p.parentEmailAddress AS parentEmail
     FROM waitinglist wl
     JOIN students s ON wl.studentID = s.studentID
     JOIN parents p ON s.parentID = p.parentID
     WHERE wl.status IN ('waiting', 'pending')
     ORDER BY wl.dateAdded DESC"
);
$waitingList->execute();
$waitingList = $waitingList->fetchAll(PDO::FETCH_ASSOC);

// Fetch all pending payments
$allPayments = $pdo->query(
    "SELECT p.paymentID, p.bookingID, p.amount, p.paymentDate, p.status, p.proofOfPayment, b.studentID
     FROM payments p
     JOIN bookings b ON p.bookingID = b.bookingID
     WHERE p.status != 'confirmed'"
)->fetchAll(PDO::FETCH_ASSOC);

$paymentsByStudent = [];
foreach ($allPayments as $payment) {
    $paymentsByStudent[$payment['studentID']][] = $payment;
}

// Fetch available lockers
$availableLockers = $pdo->query("SELECT lockerID, lockerNumber FROM lockers WHERE availability = 0 ORDER BY lockerNumber")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all lockers to show (added for management purposes)
$allLockers = $pdo->query("SELECT lockerID, lockerNumber, availability FROM lockers ORDER BY lockerNumber")->fetchAll(PDO::FETCH_ASSOC);





           

            // Total bookings in Jan–June 2026
            $stmt = $pdo->prepare(" SELECT COUNT(*) AS total
                FROM bookings
                WHERE bookingDate BETWEEN '2026-01-01' AND '2026-06-30'
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalBookingsJanJune = (int)($row['total'] ?? 0);

            // Usage for Grade 8 & 12 during Jan–June
            $stmt = $pdo->prepare("SELECT COUNT(*) AS usageCount
                FROM bookings b
                JOIN students s ON b.studentID = s.studentID
                WHERE b.bookingDate BETWEEN '2026-01-01' AND '2026-06-30'
                AND (s.studentGrade = 'Grade 8' OR s.studentGrade = 'Grade 12')
                AND b.status = 'completed'
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $usageGrade8and12 = (int)($row['usageCount'] ?? 0);

            // Usage for Grade 8 & 11
            $stmt = $pdo->prepare(" SELECT COUNT(*) AS usageCount
                FROM bookings b
                JOIN students s ON b.studentID = s.studentID
                WHERE b.bookingDate BETWEEN '2026-01-01' AND '2026-06-30'
                AND (s.studentGrade = 'Grade 8' OR s.studentGrade = 'Grade 11')
                AND b.status = 'completed'
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $usageGrade8and11 = (int)($row['usageCount'] ?? 0);




?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Lockers</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        /* Your existing styles */
        html, body { height: 100%; margin: 0; }
        body { display: flex; 
            flex-direction: column; 
            min-height: 100vh;
            background-image: url('images/lockers.png');
        }
        .containerML {
            background-color: #d1f0cbff;
            padding: 40px;
            margin: 70px auto 70px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 1500px;
            width: 80%;
            text-align: center;
            flex: 1;
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
                font-size: 0.85rem;
            }
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        th, td { border: 1px solid #333; padding: 8px; }
        th { background-color: #a6d785; }
        form { margin: 0; }
        label { margin-right: 8px; }
        input[type="text"], select { padding: 4px; }
        .message { margin-bottom: 20px; font-weight: bold; }
        .success { color: green; }
        .error { color: red; }
        .btn { padding: 6px 12px; margin: 2px; cursor: pointer; }
        .btn-primary { background-color: #4CAF50; color: white; border: none; }
        .btn-danger { background-color: #f44336; color: white; border: none; }
    </style>
</head>
<body>

    <div class="containerML">
        <h1>Manage Lockers</h1>

        <?php if ($successMessage): ?>
            <div class="message success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="message error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <section>
            <h2>Add New Locker</h2>
            <form method="post" onsubmit="return confirm('Add this new locker?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <label for="lockerNumber">Locker Number:</label>
                <input type="text" name="lockerNumber" id="lockerNumber" required pattern="\d+" title="Locker number must be digits only" />
                <button type="submit" name="action" value="add_locker" class="btn btn-primary">Add Locker</button>
            </form>
        </section>

        <section>
            <h2>Waiting List Students</h2>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Grade</th>
                        <th>Parent Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($waitingList as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['studentName'] . ' ' . $student['studentSurname']) ?></td>
                            <td><?= htmlspecialchars($student['studentGrade']) ?></td>
                            <td><?= htmlspecialchars($student['parentEmail']) ?></td>
                            <td><?= htmlspecialchars($student['status']) ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="studentID" value="<?= $student['studentID'] ?>">
                                    <input type="hidden" name="parentID" value="<?= $student['parentID'] ?>">
                                    <input type="hidden" name="parentEmail" value="<?= htmlspecialchars($student['parentEmail']) ?>">
                                    <button type="submit" name="action" value="send_waiting_notification" class="btn btn-primary" onclick="return confirm('Send waiting notification?');">Send Waiting Notification</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="studentID" value="<?= $student['studentID'] ?>">
                                    <input type="hidden" name="parentID" value="<?= $student['parentID'] ?>">
                                    <button type="submit" name="action" value="request_payment" class="btn btn-primary" onclick="return confirm('Request payment?');">Request Payment</button>
                                </form>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="studentID" value="<?= $student['studentID'] ?>">
                                    <input type="hidden" name="parentID" value="<?= $student['parentID'] ?>">
                                    <button type="submit" name="action" value="approve_application" class="btn btn-primary" onclick="return confirm('Approve application?');">Approve Application</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section>
            <h2>Pending Payments | Completed Payments</h2>
            <table>
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Student ID</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Status</th>
                        <th>Proof</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allPayments as $payment): ?>
                        <tr>
                            <td><?= $payment['paymentID'] ?></td>
                            <td><?= $payment['studentID'] ?></td>
                            <td><?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($payment['paymentDate']) ?></td>
                            <td><?= htmlspecialchars($payment['status']) ?></td>
                            <td>
                                <?php if ($payment['proofOfPayment']): ?>
                                    <a href="<?= htmlspecialchars($payment['proofOfPayment']) ?>" target="_blank">View Proof</a>
                                <?php else: ?>
                                    No proof
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Confirm this payment?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="paymentID" value="<?= $payment['paymentID'] ?>">
                                    <button type="submit" name="action" value="confirm_payment" class="btn btn-primary">Confirm Payment</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section id="finalizeBookingSection" style="display: none;">
            <h2>Finalize Locker Booking</h2>
            <form method="post" onsubmit="return confirm('Finalize locker booking?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <label for="studentID">Select Student (from waiting list):</label>
                <select name="studentID" id="studentID" required>
                    <option value="">-- Select Student --</option>
                    <?php foreach ($waitingList as $student): ?>
                        <option value="<?= $student['studentID'] ?>">
                            <?= htmlspecialchars($student['studentName'] . ' ' . $student['studentSurname'] . " (Grade: " . $student['studentGrade'] . ")") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="parentID">Parent ID:</label>
                <input type="hidden" name="parentID" id="parentID" />
                <span id="parentIDDisplay">Select a student first</span>


                <label for="lockerID">Select Locker:</label>
                <select name="lockerID" id="lockerID" required>
                    <option value="">-- Select Locker --</option>
                    <?php foreach ($availableLockers as $locker): ?>
                        <option value="<?= $locker['lockerID'] ?>">
                            Locker #<?= htmlspecialchars($locker['lockerNumber']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" name="action" value="finalize_booking" class="btn btn-primary">Finalize Booking</button>
            </form>
        </section>

        
    </div>

    <footer>
       <p>&copy; <?= date('Y') ?> Gija-Ngove Locker System. All rights reserved.</p>
    </footer>

    <script>
       document.addEventListener('DOMContentLoaded', () => {
    const waitingList = <?= json_encode($waitingList) ?>;
    const studentSelect = document.getElementById('studentID');
    const parentInput = document.getElementById('parentID');
    const parentDisplay = document.getElementById('parentIDDisplay');

    studentSelect.addEventListener('change', () => {
        const selectedStudent = waitingList.find(s => String(s.studentID) === String(studentSelect.value));
        if (selectedStudent) {
            parentInput.value = selectedStudent.parentID;
            parentDisplay.textContent = selectedStudent.parentID;
        } else {
            parentInput.value = '';
            parentDisplay.textContent = 'Select a student first';
        }
    });
});

    </script>
</body>
</html>
