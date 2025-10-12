<?php
include 'sessionManager.php';
requireLogin(['admin']);

include 'gijangovelockersystem.php';
include 'menu.inc';

// Fetch pending locker requests
$stmt = $pdo->query("SELECT w.waitingListID, w.dateAdded, s.studentName, s.studentSurname, 
           l.lockerNumber, w.status 
    FROM waitinglist w
    JOIN students s ON w.studentID = s.studentID
    JOIN lockers l ON w.lockerID = l.lockerID
    WHERE w.status = 'pending'
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $waitingListID = intval($_POST['waitingListID']);
    $action = $_POST['action']; // 'approve' or 'reject'

    if ($action === 'approve') {
        // 1. Mark request as approved
        $pdo->prepare("UPDATE waitinglist SET status = 'approved' WHERE waitingListID = ?")
            ->execute([$waitingListID]);

        // 2. Mark locker as unavailable
        $pdo->prepare("UPDATE lockers SET availability = 'unavailable' 
                       WHERE lockerID = (SELECT lockerID FROM waitinglist WHERE waitingListID = ?)")
            ->execute([$waitingListID]);

    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE waitinglist SET status = 'rejected' WHERE waitingListID = ?")
            ->execute([$waitingListID]);
    }

    header("Location: manageRequests.php"); // Refresh the page
    exit;
}
?>

<h2>Pending Locker Requests</h2>
<table border="1">
    <tr>
        <th>Student</th>
        <th>Locker #</th>
        <th>Date</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($requests as $req): ?>
        <tr>
            <td><?= htmlspecialchars($req['studentName'] . ' ' . $req['studentSurname']) ?></td>
            <td><?= htmlspecialchars($req['lockerNumber']) ?></td>
            <td><?= htmlspecialchars($req['dateAdded']) ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="waitingListID" value="<?= $req['waitingListID'] ?>">
                    <button name="action" value="approve">Approve</button>
                    <button name="action" value="reject">Reject</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<a href="adminDashboard.php">← Back to Dashboard</a>
