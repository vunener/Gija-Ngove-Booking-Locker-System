<?php
session_start();
require 'gijangovelockersystem.php';
include 'menu.inc'; // Top navigation/menu

// Redirect logged-in users to the dashboard
if (isset($_SESSION['userType'])) {
    $redirect = $_SESSION['userType'] === 'admin' ? 'adminDashboard.php' : 'parentDashboard.php';
    header("Location: $redirect");
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = $_POST['userType'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $errors[] = "Invalid session. Please refresh the page and try again.";
    }

    if (!$username || !$password || !$userType) {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {
        $table = $userType === 'admin' ? 'admins' : 'parents';
        $userCol = $userType === 'admin' ? 'adminUserName' : 'parentUsername';
        $passCol = $userType === 'admin' ? 'adminPassword' : 'parentPassword';
        $idCol = $userType === 'admin' ? 'adminID' : 'parentID';

        $stmt = $pdo->prepare("SELECT * FROM $table WHERE $userCol = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user[$passCol])) {
            session_regenerate_id(true);
            $_SESSION['userType'] = $userType;
            $_SESSION['userID'] = $user[$idCol];
            $_SESSION['username'] = $username;

            if ($userType === 'parent') {
                $_SESSION['parentID'] = $user[$idCol];
                header("Location: parentDashboard.php");
            } else {
                header("Location: adminDashboard.php");
            }
            exit;
        } else {
            $errors[] = "Invalid credentials.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
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

.containerLog {
    background-color: #d1f0cbff;
    padding: 40px;
    margin: 70px auto 70px auto; /* same as containerAD */
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 600px;    /* same as containerAD */
    width: 50%;          /* same as containerAD */
    text-align: center;

    flex: 1; /* allows container to grow and push footer down */
}

.errors {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 5px;
}

input[type="text"], input[type="password"], select {
    width: 80%;
    padding: 10px;
    margin-bottom: 15px;
}

.btn {
    background-color: #233985ff;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn:hover {
    opacity: 0.9;
}

footer {
    flex-shrink: 0;
    background-color: #233985ff; 
    padding: 15px 10px;
    color: white;
    text-align: center;
    font-size: 0.9rem;
}

.form-links a {
    color: #007BFF;
    text-decoration: none;
}

.form-links a:hover {
    text-decoration: underline;
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

<div class="containerLog">
    <h2>Login</h2>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <label>Username:</label><br>
        <input type="text" name="username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"><br>

        <label>Password:</label><br>
        <input type="password" name="password" id="password" required>
        <br>
        <input type="checkbox" onclick="togglePassword()"> Show Password
        <br><br>

        <label>User Type:</label><br>
        <select name="userType" required>
            <option value="">Select</option>
            <option value="parent" <?= ($_POST['userType'] ?? '') === 'parent' ? 'selected' : '' ?>>Parent</option>
            <option value="admin" <?= ($_POST['userType'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select><br><br>

        <div class="form-links">
            <a href="forgotPassword.php">Forgot Password?</a>
        </div><br>

        <input type="submit" value="Login" class="btn">
    </form>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
</footer>

<script>
function togglePassword() {
    const pwd = document.getElementById("password");
    pwd.type = pwd.type === "password" ? "text" : "password";
}
</script>
</body>
</html>
