<?php

/**
 * Fetch parent details by username.
 *
 * @param PDO $pdo
 * @param string $username
 * @return array|false
 */
function getParentByUsername(PDO $pdo, string $username) {
    $stmt = $pdo->prepare("SELECT * FROM parents WHERE parentUsername = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Count bookings by status.
 *
 * @param PDO $pdo
 * @param string $status
 * @return int
 */
function getBookingCountByStatus($pdo, $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE TRIM(LOWER(status)) = ?");
    $stmt->execute([strtolower(trim($status))]);
    return (int)$stmt->fetchColumn();
}


/**
 * Count bookings between two dates with a given status.
 *
 * @param PDO $pdo
 * @param string $status
 * @param string $startDate
 * @param string $endDate
 * @return int
 */
function getBookingCountBetweenDates(PDO $pdo, string $status, string $startDate, string $endDate): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
        WHERE TRIM(LOWER(status)) = ? AND bookingDate BETWEEN ? AND ?");
    $stmt->execute([strtolower(trim($status)), $startDate, $endDate]);
    return (int) $stmt->fetchColumn();
}


/**
 * Send an email to notify parent they are on the waiting list.
 *
 * @param string $parentEmail
 * @return void
 */
function sendWaitingEmail(string $parentEmail): void {
    if (filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
        $subject = "Locker Application Waiting Notification";
        $message = "Dear Parent,\n\nYour locker application is currently in waiting. We will notify you once a locker is available.\n\nThank you.";
        $headers = "From: vunenebasa@gmail.com\r\nContent-Type: text/plain; charset=UTF-8";
        mail($parentEmail, $subject, $message, $headers);
    }
}

/**
 * Send a payment request email to the parent.
 *
 * @param string $parentEmail
 * @param int $bookingID
 * @return void
 */
function sendPaymentRequestEmail(string $parentEmail, int $bookingID): void {
    if (filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {
        $subject = "Locker Payment Request";
        $message = "Dear Parent,\n\nYour locker has been allocated. Please make the payment for booking #$bookingID.\n\nAttach your proof of payment after completing payment.\n\nThank you.";
        $headers = "From: vunenebasa@gmail.com\r\nContent-Type: text/plain; charset=UTF-8";
        mail($parentEmail, $subject, $message, $headers);
    }
}

/**
 * Get number of parents currently on the waiting list.
 *
 * @param PDO $pdo
 * @return int
 */
function getWaitingListCount(PDO $pdo): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM waitinglist WHERE LOWER(status) = 'waiting'");
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

/**
 * Get number of pending notifications.
 *
 * @param PDO $pdo
 * @return int
 */
function getNotificationCount(PDO $pdo): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE LOWER(status) = 'pending'");
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

?>
