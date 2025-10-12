<?php
require_once 'sessionManager.php';
requireLogin(['admin']);

require_once 'gijangovelockersystem.php';

$adminID = $_SESSION['userID'] ?? null;
$bookingID = isset($_GET['bookingID']) ? (int)$_GET['bookingID'] : 0;
$action = $_GET['action'] ?? '';

if (!$bookingID || !in_array($action, ['approve', 'reject'], true)) {
    die('Invalid request.');
}

try {
    // If etch booking details
    $stmt = $pdo->prepare("SELECT studentID, lockerID FROM bookings WHERE bookingID = ?");
    $stmt->execute([$bookingID]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception("Booking not found.");
    }

    $studentID = (int)$booking['studentID'];
    $lockerID = (int)$booking['lockerID'];

    $stmt = $pdo->prepare("SELECT parentID FROM students WHERE studentID = ?");
    $stmt->execute([$studentID]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    $parentID = $parent['parentID'] ?? null;

    if ($action === 'approve') {
        $pdo->beginTransaction();

        // Approve the booking
        $stmt = $pdo->prepare(" UPDATE bookings 
            SET status = 'approved', approvalDate = NOW(), approvedByAdminID = ?
            WHERE bookingID = ?
        ");
        $stmt->execute([$adminID, $bookingID]);

        // Mark locker as booked
        $stmt = $pdo->prepare("UPDATE lockers 
            SET availability = 1 
            WHERE lockerID = ?
        ");
        $stmt->execute([$lockerID]);

        //Update student record with lockerID
        $stmt = $pdo->prepare(" UPDATE students 
            SET lockerID = ?
            WHERE studentID = ?
        ");
        $stmt->execute([$lockerID, $studentID]);

        // Update waiting list if applicable
        $stmt = $pdo->prepare(" UPDATE waitinglist 
            SET lockerID = ?, status = 'completed'
            WHERE studentID = ?
        ");
        $stmt->execute([$lockerID, $studentID]);

        // update the booking to 'completed' immediately after approval
        $stmt = $pdo->prepare("UPDATE bookings 
            SET status = 'completed' 
            WHERE bookingID = ? AND status = 'approved'");
        $stmt->execute([$bookingID]);

        $pdo->commit();

        
        // Update any existing 'Payment' notifications from 'pending' to 'completed'
        $stmt = $pdo->prepare("UPDATE notifications 
            SET status = 'completed' 
            WHERE parentID = ? 
            AND type = 'Payment' 
            AND status = 'pending'");
        $stmt->execute([$parentID]);

    } elseif ($action === 'reject') {
        // Just reject booking
        $stmt = $pdo->prepare(" UPDATE bookings 
            SET status = 'rejected', approvalDate = NOW(), approvedByAdminID = ?
            WHERE bookingID = ?
        ");
        $stmt->execute([$adminID, $bookingID]);

        
        $stmt = $pdo->prepare("UPDATE waitinglist 
            SET status = 'Rejected'
            WHERE studentID = ?
        ");
        $stmt->execute([$studentID]);
    }

    header('Location: adminDashboard.php');
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . htmlspecialchars($e->getMessage()));
}
