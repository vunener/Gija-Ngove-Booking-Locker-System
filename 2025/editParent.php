<?php
include 'sessionManager.php';
requireLogin('admin');

include 'gijangovelockersystem.php';
include 'menu.inc';

$parentID = isset($_GET['parentID']) ? (int)$_GET['parentID'] : 0;

if ($parentID <= 0) {
    die("Missing or invalid parent ID.");
}

// Fetch parent record
$stmt = $pdo->prepare("SELECT * FROM parents WHERE parentID = ?");
$stmt->execute([$parentID]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent not found.");
}

$errors = [];
$success = isset($_GET['success']) && $_GET['success'] == '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['parentName']);
    $surname = trim($_POST['parentSurname']);
    $username = trim($_POST['parentUsername']);
    $email = trim($_POST['parentEmailAddress']);
    $password = $_POST['newPassword'] ?? '';
    $confirm = $_POST['confirmPassword'] ?? '';

    // Validation
    if (!$name || !$surname || !$username || !$email) {
        $errors[] = "All fields except password are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if ($password || $confirm) {
        if ($password !== $confirm) {
            $errors[] = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
    }

    if (empty($errors)) {
        // Update parent basic info
        $stmt = $pdo->prepare("
            UPDATE parents 
            SET parentName = ?, parentSurname = ?, parentUsername = ?, parentEmailAddress = ?
            WHERE parentID = ?
        ");
        $stmt->execute([$name, $surname, $username, $email, $parentID]);

        // Update password if set
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pwdStmt = $pdo->prepare("UPDATE parents SET parentPassword = ? WHERE parentID = ?");
            $pwdStmt->execute([$hashed, $parentID]);
        }

        // Redirect to avoid resubmission
        header("Location: editParent.php?parentID=$parentID&success=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Parent</title>
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

        .containerEP {
            background-color: #d1f0cbff;
            padding: 40px;
            margin: 70px auto 70px auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 50%;
            text-align: center;

            /* Optional: keep flex:1 if it's part of your layout */
            flex: 1;
        }

        .containerEP fieldset {
            width: 75%;
            margin: 70px auto 0 auto; /* or change to 50px if preferred */
            border: 2px solid #233985ff;
            padding: 20px;
            border-radius: 6px;
            background-color: #d1f0cbff;

            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .containerEP fieldset form {
            width: 100%;
            max-width: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .containerEP form input,
        .containerEP form select {
            width: 100%;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        .containerEP nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .containerEP nav ul li a {
            text-decoration: none;
            color: #007BFF;
            font-size: 4em;
            transition: color 0.3s;
        }

        .containerEP nav ul li a:hover {
            color: #0056b3;
            text-decoration: none;
        }

        footer {
            flex-shrink: 0;
            background-color: #233985ff; /* or use #064180ff if preferred */
            padding: 15px 10px;
            color: white;
            text-align: center;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            footer {
                padding: 10px 5px;
                font-size: 1rem;
            }
        }
</style>

</head>
<body>
<main>
    <div class="containerEP">
        <h2>Edit Parent Profile</h2>

        <?php if ($success): ?>
            <div class="success-message">Parent information updated successfully.</div>
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

        <form method="POST">
            <div class="form-border">
                <label for="parentName">Name:</label>
                <input type="text" name="parentName" id="parentName" value="<?= htmlspecialchars($parent['parentName']) ?>" required>

                <label for="parentSurname">Surname:</label>
                <input type="text" name="parentSurname" id="parentSurname" value="<?= htmlspecialchars($parent['parentSurname']) ?>" required>

                <label for="parentUsername">Username:</label>
                <input type="text" name="parentUsername" id="parentUsername" value="<?= htmlspecialchars($parent['parentUsername']) ?>" required>

                <label for="parentEmailAddress">Email:</label>
                <input type="email" name="parentEmailAddress" id="parentEmailAddress" value="<?= htmlspecialchars($parent['parentEmailAddress']) ?>" required>

                <hr>

                <label for="newPassword">New Password (optional):</label>
                <input type="password" name="newPassword" id="newPassword" minlength="6">

                <label for="confirmPassword">Confirm Password:</label>
                <input type="password" name="confirmPassword" id="confirmPassword" minlength="6">

                <input type="submit" class="btn" value="Save Changes">
            </div>
        </form>

        <br>
        <a href="manageUsers.php" class="button-link">&larr; Back to Manage Users</a>

        <?php if ($_SESSION['userType'] === 'admin'): ?>
            <a href="adminDashboard.php" class="btn-secondary">&larr; Back to Dashboard</a>
        <?php endif; ?>
    </div>
</main>

    <footer>
    <p>&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
    </footer>
</body>
</html>
