<?php
session_start();
require 'gijangovelockersystem.php';
include 'menu.inc';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['newPassword'] ?? '';
    $confirm = $_POST['confirmPassword'] ?? '';

    if (!$username || !$email || !$password || !$confirm) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if (empty($errors)) {
        // Verify if the user exists
        $stmt = $pdo->prepare("SELECT parentID FROM parents WHERE parentUsername = ? AND parentEmailAddress = ?");
        $stmt->execute([$username, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE parents SET parentPassword = ? WHERE parentID = ?");
            $update->execute([$hashed, $user['parentID']]);
            $success = true;
        } else {
            $errors[] = "No parent found with provided credentials.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Self Service - Reset Password</title>
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

        .containerFP {
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

        .containerFP fieldset {
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

        .containerFP fieldset form {
            width: 100%;
            max-width: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .containerFP form input,
        .containerFP form select {
            width: 100%;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        .containerFP nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .containerFP nav ul li a {
            text-decoration: none;
            color: #007BFF;
            font-size: 1.1em;
            transition: color 0.3s;
        }

        .containerFP nav ul li a:hover {
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
                font-size: 0.85rem;
            }
        }
</style>

</head>
<body>
<div class="containerFP">
    <h2>Self Service - Reset Password</h2>

    <?php if ($success): ?>
        <p class="success">Password reset successfully. <a href="login.php">Login</a></p>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            <input type="email" name="email" placeholder="Email Address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <input type="password" name="newPassword" placeholder="New Password" required minlength="6">
            <input type="password" name="confirmPassword" placeholder="Confirm Password" required minlength="6">
            <input type="submit" class="btn" value="Reset Password">
        </form>
    <?php endif; ?>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
</footer>
</body>
</html>
