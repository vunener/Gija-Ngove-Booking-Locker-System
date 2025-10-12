<?php
session_start();
include 'sessionManager.php';
requireLogin('admin');

include 'gijangovelockersystem.php';
include 'gijaNgoveLockerFunction.php';
include 'menu.inc';

// Initialize admin message variable
$adminMessage = '';

// Handle POST actions: Approve Application or Finalize Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $studentID = $_POST['studentID'] ?? null;
    $parentID = $_POST['parentID'] ?? null;
    $lockerID = $_POST['lockerID'] ?? null;
    $adminID = $_SESSION['userID'] ?? null;
    $status = 'approved';
    $dateSent = date('Y-m-d H:i:s');

    if ($action === 'approve_application' && $studentID && $parentID) {
        // Approve the student's locker application
        $update = $pdo->prepare("UPDATE waitinglist SET status = 'approved' WHERE studentID = ?");
        $update->execute([$studentID]);

        // Notify parent of approval
        $type = 'approved';
        $message = "Your child's locker application has been approved. You will be notified once a locker is allocated.";
        $notify = $pdo->prepare("INSERT INTO notifications (parentID, adminID, type, message, status, dateSent) VALUES (?, ?, ?, ?, ?, ?)");
        $notify->execute([$parentID, $adminID, $type, $message, $status, $dateSent]);

        $adminMessage = "Application approved for student ID: $studentID";
    }

    if ($action === 'finalize_booking' && $studentID && $lockerID && $parentID) {
        try {
            $pdo->beginTransaction();

            // Mark locker as unavailable
            $lockUpdate = $pdo->prepare("UPDATE lockers SET availability = 0 WHERE lockerID = ?");
            $lockUpdate->execute([$lockerID]);

            // Insert booking record
            $bookingDate = '2026-01-01'; // You can pick any date in Jan–June 2026
            $bookingInsert = $pdo->prepare("INSERT INTO bookings (studentID, lockerID, status, bookingDate) VALUES (?, ?, 'completed', ?)");
            $bookingInsert->execute([$studentID, $lockerID, $bookingDate]);

            // Notify parent of successful booking
            $type = 'booking_finalized';
            $message = "Your child's locker has been successfully booked.";
            $notify = $pdo->prepare("INSERT INTO notifications (parentID, adminID, type, message, status, dateSent) VALUES (?, ?, ?, ?, ?, ?)");
            $notify->execute([$parentID, $adminID, $type, $message, $status, $dateSent]);

            $pdo->commit();
            $adminMessage = "Locker booking finalized for student ID: $studentID";
        } catch (Exception $e) {
            $pdo->rollBack();
            $adminMessage = "Error finalizing booking: " . $e->getMessage();
        }
    }
}

// Helper function to get available lockers
function getAvailableLockers($pdo) {
    $stmt = $pdo->query("SELECT lockerID, lockerNumber FROM lockers WHERE LOWER(availability) = 0");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch reports data
$stmtChart = $pdo->query("SELECT s.studentGrade, COUNT(*) AS cnt FROM bookings b JOIN students s ON b.studentID = s.studentID GROUP BY s.studentGrade");
$dataChart = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
$grades = array_column($dataChart, 'studentGrade');
$counts = array_column($dataChart, 'cnt');

$stmt811 = $pdo->query("SELECT s.studentGrade, COUNT(*) AS cnt FROM bookings b JOIN students s ON b.studentID = s.studentID WHERE LOWER(b.status) = 'completed' AND s.studentGrade IN ('Grade 8', 'Grade 11') GROUP BY s.studentGrade ORDER BY FIELD(s.studentGrade, 'Grade 8', 'Grade 11')");
$data811 = $stmt811->fetchAll(PDO::FETCH_ASSOC);
$grades811 = array_column($data811, 'studentGrade');
$counts811 = array_column($data811, 'cnt');

$stmt812 = $pdo->query("SELECT s.studentGrade, COUNT(*) AS cnt FROM bookings b JOIN students s ON b.studentID = s.studentID WHERE LOWER(b.status) = 'completed' AND s.studentGrade IN ('Grade 8', 'Grade 12') GROUP BY s.studentGrade ORDER BY FIELD(s.studentGrade, 'Grade 8', 'Grade 12')");
$data812 = $stmt812->fetchAll(PDO::FETCH_ASSOC);
$grades812 = array_column($data812, 'studentGrade');
$counts812 = array_column($data812, 'cnt');

$totalParents = (int)$pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$maleStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE gender = 'Male'")->fetchColumn();
$femaleStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE gender = 'Female'")->fetchColumn();
$distinctGrades = (int)$pdo->query("SELECT COUNT(DISTINCT studentGrade) FROM students")->fetchColumn();
$totalLockers = (int)$pdo->query("SELECT COUNT(*) FROM lockers")->fetchColumn();
$availableLockersCount = (int)$pdo->query("SELECT COUNT(*) FROM lockers WHERE LOWER(availability) = 0")->fetchColumn();
$bookedLockers = $totalLockers - $availableLockersCount;


$pendingBookings = getBookingCountByStatus($pdo, 'pending');
$completedBookings = getBookingCountByStatus($pdo, 'completed');
$waitingListCount = getWaitingListCount($pdo);
$pendingNotifications = getNotificationCount($pdo);
$completedJanToJune = getBookingCountBetweenDates($pdo, 'completed', '2026-01-01', '2026-06-30');

// Bookings between Jananuary - June 2026
$janToJuneBookings = $pdo->query("SELECT s.studentID, s.studentSchoolNumber, s.studentName, s.studentSurname, l.lockerNumber, s.studentGrade, b.bookingDate
    FROM bookings b
    LEFT JOIN students s ON b.studentID = s.studentID
    LEFT JOIN lockers l ON b.lockerID = l.lockerID
    WHERE TRIM(LOWER(b.status)) = 'completed' 
      AND b.bookingDate BETWEEN '2026-01-01' AND '2026-06-30'
    ORDER BY b.bookingDate DESC
")->fetchAll(PDO::FETCH_ASSOC);


// Student locker assignment report
$studentLockerReport = $pdo->query("SELECT s.studentID, s.studentSchoolNumber, s.studentName, s.studentSurname, s.studentGrade,
           COALESCE(l.lockerNumber, '-') AS lockerNumber, s.parentID,
           (SELECT status FROM waitinglist WHERE studentID = s.studentID LIMIT 1) AS status
    FROM students s
    LEFT JOIN bookings b ON s.studentID = b.studentID
    LEFT JOIN lockers l ON b.lockerID = l.lockerID
    ORDER BY s.studentGrade, s.studentSurname, s.studentName
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>MIS Report Dashboard</title>
    <link rel="stylesheet" href="styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background-image: url('images/lockers.png');
        }
        .containerMIS {
            background-color: #d1f0cb;
            padding: 40px;
            margin: 70px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            width: 80%;
            text-align: center;
            flex: 1;
        }
        footer {
            flex-shrink: 0;
            background-color: #233985;
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
        .dashboard-stat-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin: 40px 0;
        }
        .dashboard-box {
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 25px;
            width: 320px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: transform 0.2s ease;
        }
        .dashboard-box:hover {
            transform: scale(1.02);
        }
        .dashboard-box h3 {
            margin-bottom: 15px;
            color: #233985;
        }
        .dashboard-box p {
            margin: 8px 0;
            line-height: 1.4;
        }
        .form-inline {
            margin-top: 10px;
        }
        .status-message {
            color: green;
            font-weight: bold;
            margin: 10px 0;
        }
        .error-message {
            color: red;
            font-weight: bold;
            margin: 10px 0;
        }
        .tabs {
            margin-bottom: 20px;
        }
        .tabs button {
            margin: 2px;
            padding: 10px 15px;
            cursor: pointer;
            background-color: #233985;
            color: white;
            border: none;
            border-radius: 5px;
        }
        .tabs button:hover {
            background-color: #1b2d73;
        }
        table {
            border-collapse: collapse;
            margin: 20px auto;
            width: 100%;
            max-width: 1100px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        .btn-secondary {
            text-decoration: none;
            color: #233985;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="containerMIS">
    <h1>MIS Dashboard</h1>

    <?php if ($adminMessage): ?>
        <div class="status-message"><?= htmlspecialchars($adminMessage) ?></div>
    <?php endif; ?>

    <!-- Stats Overview -->
    <div class="dashboard-stat-container">
        <div class="dashboard-box">
            <h3>General Overview</h3>
            <p><strong>Total Parents:</strong> <?= $totalParents ?></p>
            <p><strong>Total Students:</strong> <?= $totalStudents ?></p>
            <p><strong>Male Students:</strong> <?= $maleStudents ?></p>
            <p><strong>Female Students:</strong> <?= $femaleStudents ?></p>
            <p><strong>Grades Represented:</strong> <?= $distinctGrades ?></p>
            <p><strong>Total Lockers:</strong> <?= $totalLockers ?></p>
            <p><strong>Available Lockers:</strong> <?= $availableLockersCount ?></p>
        </div>

        <div class="dashboard-box">
            <h3>Booking & Notification Summary</h3>
            <p><strong>Booked Lockers:</strong> <?= $bookedLockers ?></p>
            <p><strong>Pending Bookings:</strong> <?= $pendingBookings ?></p>
            <p><strong>Completed Bookings:</strong> <?= $completedBookings ?></p>
            <p><strong>Waiting List Count:</strong> <?= $waitingListCount ?></p>
            <p><strong>Pending Notifications:</strong> <?= $pendingNotifications ?></p>
            <p><strong>Completed (Jan-June 2026):</strong> <?= $completedJanToJune ?></p>
        </div>
    </div>

    <!-- Tabs for Reports -->
    <hr />
    <div class="tabs">
        <button onclick="showSection('report1')">Student Locker Assignments</button>
        <button onclick="showSection('report2')">Bookings January - June 2026</button>
        <button onclick="showSection('report3')">Summary January - June 2026</button>
        <button onclick="showSection('report4')">Bookings by Grade</button>
        <button onclick="showSection('report5')">Grade 8 & 11 Usage</button>
        <button onclick="showSection('report6')">Grade 8 & 12 Usage</button>
    </div>

    <!-- Report 1: Student Locker Assignments -->
    <div id="report1" class="report-section">
        <h2>Student Locker Assignments</h2>
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>School Number</th>
                    <th>Name</th>
                    <th>Surname</th>
                    <th>Grade</th>
                    <th>Locker Number</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($studentLockerReport as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['studentID']) ?></td>
                    <td><?= htmlspecialchars($row['studentSchoolNumber']) ?></td>
                    <td><?= htmlspecialchars($row['studentName']) ?></td>
                    <td><?= htmlspecialchars($row['studentSurname']) ?></td>
                    <td><?= htmlspecialchars($row['studentGrade']) ?></td>
                    <td><?= htmlspecialchars($row['lockerNumber']) ?></td>
                    <td><?= htmlspecialchars($row['status'] ?: '-') ?></td>
                    <td>
                        <?php if (strtolower($row['status']) === 'pending'): ?>
                            <!-- Approve Application -->
                            <form method="post" class="form-inline" style="display:inline;">
                                <input type="hidden" name="studentID" value="<?= (int)$row['studentID'] ?>" />
                                <input type="hidden" name="parentID" value="<?= (int)$row['parentID'] ?>" />
                                <input type="hidden" name="action" value="approve_application" />
                                <button type="submit">Approve</button>
                            </form>
                        <?php elseif (strtolower($row['status']) === 'approved' && $row['lockerNumber'] === '-'): ?>
                            <!-- Finalize Booking -->
                            <?php $availableLockers = getAvailableLockers($pdo); ?>
                            <?php if (count($availableLockers) > 0): ?>
                                <form method="post" class="form-inline" style="display:inline;">
                                    <input type="hidden" name="studentID" value="<?= (int)$row['studentID'] ?>" />
                                    <input type="hidden" name="parentID" value="<?= (int)$row['parentID'] ?>" />
                                    <input type="hidden" name="action" value="finalize_booking" />
                                    <select name="lockerID" required>
                                        <option value="">Select Locker</option>
                                        <?php foreach ($availableLockers as $locker): ?>
                                            <option value="<?= (int)$locker['lockerID'] ?>"><?= htmlspecialchars($locker['lockerNumber']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit">Finalize</button>
                                </form>
                            <?php else: ?>
                                <em>No available lockers currently</em>
                            <?php endif; ?>
                        <?php else: ?>
                            <em>-</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Report 2: Bookings January - June 2026 -->
    <div id="report2" class="report-section" style="display:none;">
        <h2>Bookings January - June 2026</h2>
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>School Number</th>
                    <th>Name</th>
                    <th>Surname</th>
                    <th>Grade</th>
                    <th>Locker Number</th>
                    <th>Booking Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($janToJuneBookings as $booking): ?>
                <tr>
                    <td><?= htmlspecialchars($booking['studentID']) ?></td>
                    <td><?= htmlspecialchars($booking['studentSchoolNumber']) ?></td>
                    <td><?= htmlspecialchars($booking['studentName']) ?></td>
                    <td><?= htmlspecialchars($booking['studentSurname']) ?></td>
                    <td><?= htmlspecialchars($booking['studentGrade']) ?></td>
                    <td><?= htmlspecialchars($booking['lockerNumber']) ?></td>
                    <td><?= htmlspecialchars($booking['bookingDate']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Report 3: Summary Jan - June 2026 -->
    <div id="report3" class="report-section" style="display:none;">
        <h2>Summary January - June 2026</h2>
        <p><strong>Total Bookings Completed:</strong> <?= $completedJanToJune ?></p>
        <!-- You can add more summaries here if needed -->
    </div>

    <!-- Report 4: Bookings by Grade Chart -->
    <div id="report4" class="report-section" style="display:none;">
        <h2>Bookings by Grade</h2>
        <canvas id="chartBookingsByGrade" width="800" height="400"></canvas>
    </div>

    <!-- Report 5: Grade 8 & 11 Usage Chart -->
    <div id="report5" class="report-section" style="display:none;">
        <h2>Grade 8 & 11 Usage</h2>
        <canvas id="chartGrade8and11" width="800" height="400"></canvas>
    </div>

    <!-- Report 6: Grade 8 & 12 Usage Chart -->
    <div id="report6" class="report-section" style="display:none;">
        <h2>Grade 8 & 12 Usage</h2>
        <canvas id="chartGrade8and12" width="800" height="400"></canvas>
    </div>
</div>

<footer>
    &copy; <?= date('Y') ?> Gija Ngove. All rights reserved.
</footer>

<script>
function showSection(sectionId) {
    document.querySelectorAll('.report-section').forEach(section => {
        section.style.display = 'none';
    });
    const active = document.getElementById(sectionId);
    if (active) active.style.display = 'block';
}

// Show default tab on load
document.addEventListener('DOMContentLoaded', () => {
    showSection('report1');

    // Chart.js Data and Configurations
    const ctx1 = document.getElementById('chartBookingsByGrade').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?= json_encode($grades) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode($counts) ?>,
                backgroundColor: 'rgba(35, 57, 133, 0.7)'
            }]
        },
        options: { responsive: true }
    });

    const ctx2 = document.getElementById('chartGrade8and11').getContext('2d');
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: <?= json_encode($grades811) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode($counts811) ?>,
                backgroundColor: 'rgba(35, 57, 133, 0.7)'
            }]
        },
        options: { responsive: true }
    });

    const ctx3 = document.getElementById('chartGrade8and12').getContext('2d');
    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: <?= json_encode($grades812) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode($counts812) ?>,
                backgroundColor: 'rgba(35, 57, 133, 0.7)'
            }]
        },
        options: { responsive: true }
    });
});
</script>

</body>
</html>
