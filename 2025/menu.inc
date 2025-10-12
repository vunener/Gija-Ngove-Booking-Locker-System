<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['userType']);
$userType = $isLoggedIn ? $_SESSION['userType'] : null;
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Start Menu -->
<style>
    ul.horizontal-menu {
        list-style-type: none;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: right;
        background-color: #233985ff;
    }

    ul.horizontal-menu li {
        padding: 10px 20px;
    }

    ul.horizontal-menu li a {
        text-decoration: none;
        color: white;
        font-weight: bold;
    }

    ul.horizontal-menu li a:hover {
        color: #d2ddb4ff;
    }

    .site-header {
        text-align: center;
        background-color: #f4f4f4;
        padding: 20px 10px;
        color: #233985ff;
        font-family: Arial, sans-serif;
        border-bottom: 2px solid #233985ff;
       
    }
</style>



<ul class="horizontal-menu">
    <?php if ($isLoggedIn): ?>
        <?php if ($userType === 'admin'): ?>
            <li><a href="adminDashboard.php">Admin Dashboard</a></li>
            <li><a href="adminPayments.php">Review Payments</a></li>
            <li><a href="manageUsers.php">Manage Users</a></li>
            <li><a href="manageLockers.php">Manage Lockers</a></li>
            <li><a href="misReport.php">MIS Reports</a></li>
            <li><a href="logout.php">Logout</a></li>
        <?php elseif ($userType === 'parent'): ?>
            <li><a href="parentDashboard.php">Parent Dashboard</a></li>
            <li><a href="lockerBooking.php">Book Locker</a></li>
            <li><a href="viewStudentInfo.php">View Student Info</a></li>
            <li><a href="payments.php">Payments</a></li>
            <li><a href="logout.php">Logout</a></li>
        <?php endif; ?>
    <?php else: ?>
        <li><a href="login.php">Login</a></li>
        <li><a href="registration.php">Register</a></li>
    <?php endif; ?>
</ul>

<!-- Page Header -->
<div class="site-header">
    <h1>Gija-Ngove Locker System</h1>
</div>
<!-- End Menu -->
