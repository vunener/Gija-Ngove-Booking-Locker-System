<?php
require_once 'sessionManager.php';
require_once 'gijangovelockersystem.php';

// Ensure parent is logged in
if (!isset($_SESSION['userType']) || $_SESSION['userType'] !== 'parent') {
    header("Location: login.php");
    exit;
}

// Set dynamic page title
$pageTitle = "Parent Dashboard";
include 'menu.inc';

// Fetch parent name
$parentName = "Unknown Parent";
if (!empty($_SESSION['userID'])) {
    $stmt = $pdo->prepare("SELECT parentName, parentSurname FROM parents WHERE parentID = ?");
    $stmt->execute([$_SESSION['userID']]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($parent) {
        $parentName = htmlspecialchars($parent['parentName'] . ' ' . $parent['parentSurname']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>

    <!-- This is where you include styles.css -->
    <link rel="stylesheet" href="styles.css">

    <style>
        /* Optional: Page-specific styling */
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
        .containerPD {
            background-color: #d1f0cbff;
            padding: 40px;
            padding-bottom: 60px;
            margin: 70px auto auto auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            width: 80%;
            text-align: center;
        }

        .form-box-tabs {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
        }

        .form-box {
            border: 1px solid #233985ff;
            padding: 30px;
            border-radius: 10px;
            width: 300px;
            background-color: white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .button-link {
            text-decoration: none;
            color: inherit;
        }

        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: #233985;
            color: white;
            text-align: center;
            padding: 15px 10px;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
<main>
    <div class="containerPD">
        <h2>Welcome to the Parent Dashboard</h2>
        <p><strong>Parent Logged In:</strong> <?= $parentName ?></p>

        <div class="form-box-tabs">
            <div class="form-box">
                <a href="viewStudentInfo.php" class="button-link">
                    <h3>View My Child's Info</h3>
                    <p>See and update your child's details</p>
                </a>
            </div>

            <div class="form-box">
                <a href="lockerBooking.php" class="button-link">
                    <h3>Book a Locker</h3>
                    <p>Reserve a locker for your child</p>
                </a>
            </div>
        </div>
    </div>
</main>

<footer>
    <p>&copy; Gija‑Ngove Locker System <?= date('Y') ?></p>
</footer>
</body>
</html>
