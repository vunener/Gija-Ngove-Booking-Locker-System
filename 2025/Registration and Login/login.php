<?php

include 'menu.inc';

session_start();
//require_once 'models.php'; 

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = $_POST['userType'] ?? '';

    if (!$username || !$password || !$userType) {
        $errors[] = "All fields are required.";
    } else {
        if ($userType === 'parent') {
            $stmt = $db->prepare("SELECT * FROM parents WHERE parentUsername = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['parentPassword'])) {
                $_SESSION['userType'] = 'parent';
                $_SESSION['userID'] = $user['parentID'];
                header("Location: parent_dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid parent login.";
            }
        } elseif ($userType === 'admin') {
            $stmt = $db->prepare("SELECT * FROM admins WHERE adminUserName = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['adminPassword'])) {
                $_SESSION['userType'] = 'admin';
                $_SESSION['userID'] = $user['adminID'];
                header("Location: admin_dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid admin login.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Page</title>
    <style>
        body { font-family: Arial; background-color: #f0f0f0; }
        .container {
            width: 400px; margin: 100px auto; padding: 20px;
            background: #fff; box-shadow: 0 0 10px #aaa; border-radius: 8px;
        }
        input, select { width: 90%; padding: 10px; margin: 10px 0; }
        .btn { background: rgb(61, 125, 193); color: #fff; border: none; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
<div class="container">
    <h2>Login</h2>
    <?php foreach ($errors as $e): ?>
        <p class="error"><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
    <form method="POST">
        <label>Username:</label>
        <input type="text" name="username" required>

        <label>Password:</label>
        <input type="password" name="password" required>

        <label>User Type:</label>
        <select name="userType" required>
            <option value="">Select</option>
            <option value="parent">Parent</option>
            <option value="admin">Administrator</option>
        </select>

        <input class="btn" type="submit" value="Login">
    </form>
</div>
  <div class="page-container">
     
        <main>
    
        </main>

        <footer>
            <h4> Assignment 3 &copy 2025 </h4>
        </footer>
    </div>

</body>
</html>
