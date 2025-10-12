<?php
// to ensure the session and login as admin
include 'sessionManager.php';
requireLogin(['admin']);

// DB connection
include 'gijangovelockersystem.php';

$adminName = 'Admin';

// to get the admin username from DB
try {
    $stmt = $pdo->prepare("SELECT adminUserName FROM admins WHERE adminID = ?");
    $stmt->execute([$_SESSION['userID']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && isset($admin['adminUserName'])) {
        $adminName = htmlspecialchars($admin['adminUserName'], ENT_QUOTES, 'UTF-8');
    }
} catch (PDOException $e) {
    // it logs error in production
    $adminName = 'Unknown';
}

// to count payments pending review
$pendingCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) AS count FROM payments WHERE proofOfPayment IS NOT NULL AND status = 'Pending Review'");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pendingCount = (int) $row['count'];
    }
} catch (PDOException $e) {
    $pendingCount = 0; 
}
// it fetch pending locker bookings
$pendingBookings = [];

try {
    $stmt = $pdo->query(" SELECT b.bookingID, b.bookingDate, s.studentName, s.studentSurname, l.lockerNumber
        FROM bookings b
        JOIN students s ON b.studentID = s.studentID
        JOIN lockers l ON b.lockerID = l.lockerID
        WHERE b.status = 'pending'
        ORDER BY b.bookingDate DESC
    ");
    $pendingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pendingBookings = [];
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Gija‑Ngove Locker System</title>
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
.containerAD {
    background-color: #d1f0cbff;
    padding: 40px;
     margin: 70px auto 70px auto;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 600px;
    width: 50%;
    text-align: center;

   
    flex: 1;
}

.containerAD fieldset {
    width: 75%;
    margin: 70px auto 0 auto; 
    border: 2px solid #233985ff;
    padding: 20px;
    border-radius: 6px;
    background-color: #d1f0cbff;

    display: flex;
    flex-direction: column;
    align-items: center;
}

.containerAD fieldset form {
    width: 100%;
    max-width: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.containerAD form input,
.containerAD form select {
    width: 100%;
    margin-top: 5px;
    margin-bottom: 15px;
    box-sizing: border-box;
}


.containerAD nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.containerAD nav ul li a {
    text-decoration: none;
    color: #007BFF;
    font-size: 1.1em;
    transition: color 0.3s;
}

.containerAD nav ul li a:hover {
    color: #0056b3;
    text-decoration: none;
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
</style>

</head>
<body>
    <?php include 'menu.inc'; ?>

    <div class="containerAD">
     <fieldset>
    <legend>Admin Dashboard</legend>

    <p>Welcome, <strong><?= $adminName ?></strong></p>

    <nav>
        <ul>
            <li><a href="misReport.php">📊 View MIS Report</a></li>
            <li><a href="manageUsers.php">👥 Manage Users</a></li>
            <li><a href="manageLockers.php">🔒 Manage Lockers</a></li>
            <li><a href="adminPayments.php">💳 Review Payments </a></li>
            <li><a href="manageStudents.php">🎓 Manage Students</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </nav>
</fieldset>
<?php if (!empty($pendingBookings)): ?>
    <fieldset>
        <legend>Pending Locker Bookings</legend>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Locker</th>
                    <th>Booking Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingBookings as $booking): ?>
                    <tr>
                        <td><?= htmlspecialchars($booking['studentName'] . ' ' . $booking['studentSurname']) ?></td>
                        <td><?= htmlspecialchars($booking['lockerNumber']) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($booking['bookingDate']))) ?></td>
                        <td>
                            <a class="btn" href="approveBooking.php?bookingID=<?= (int)$booking['bookingID'] ?>&action=approve">Approve</a><br>
                            <span> | </span><br>
                            <a class="btn" style="background-color: #d9534f;" href="approveBooking.php?bookingID=<?= (int)$booking['bookingID'] ?>&action=reject">Reject</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </fieldset>
<?php else: ?>
    <fieldset>
        <legend>Pending Locker Bookings</legend>
        <p>No pending locker bookings at this time.</p>
    </fieldset>
<?php endif; ?>

    </div>

    <footer>
        <p>&copy; <?= date('Y') ?> Gija-Ngove Locker System. All rights reserved.</p>
    </footer>
</body>
</html>
