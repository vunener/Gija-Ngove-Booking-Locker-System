<?php
include 'menu.inc';

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirmPassword'] ?? '';
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $idNumber = trim($_POST['idNumber'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notification = $_POST['preferredNotification'] ?? 'Email';

    if (!$username || !$password || !$confirm || !$firstName || !$lastName || !$idNumber || !$email) {
        $errors[] = "Please fill in all required fields.";
    } elseif ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } else {
        $stmt = $db->prepare("SELECT * FROM parents WHERE parentUsername = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Username already exists.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $db->prepare("INSERT INTO parents 
                (parentUsername, parentPassword, parentName, parentSurname, parentIDNumber, parentEmailAddress, parentPhoneNumber, parentHomeAddress, preferredNotification, dateCreated)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert->execute([
                $username, $hashedPassword, $firstName, $lastName, $idNumber, $email, $phone, $address, $notification
            ]);
            $messages[] = "Registration successful. You can now log in.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Parent Registration</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; }
        .container {
            width: 500px; margin: 50px auto; padding: 20px;
            background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input, textarea, select {
            width: 95%; padding: 10px; margin: 10px 0;
            border: 1px solid #ccc; border-radius: 4px;
        }
        .btn {
            background:rgb(61, 125, 193); color: white; padding: 10px;
            border: none; border-radius: 4px; cursor: pointer;
        }
        .btn:hover { background: rgb(10, 55, 102); }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
<div class="container">
    <h2>Parent Registration</h2>

    <?php foreach ($errors as $e): ?>
        <p class="error"><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>

    <?php foreach ($messages as $m): ?>
        <p class="success"><?= htmlspecialchars($m) ?></p>
    <?php endforeach; ?>

    <form method="POST">
        <label>Username:</label>
        <input type="text" name="username" required>
        
        <label>Password:</label>
        <input type="password" name="password" required>

        <label>Confirm Password:</label>
        <input type="password" name="confirmPassword" required>

        <label>First Name:</label>
        <input type="text" name="firstName" required>

        <label>Last Name:</label>
        <input type="text" name="lastName" required>

        <label>ID Number:</label>
        <input type="text" name="idNumber" required>

         <label>Title:</label>
        <input type="text" name="Title" required>

        <label>Email Address:</label>
        <input type="email" name="email" required>

        <label>Phone Number:</label>
        <input type="text" name="phone">

        <label>Home Address:</label>
        <textarea name="address"></textarea>

        <label>Preferred Notification:</label>
        <select name="preferredNotification">
            <option value="Email">Email</option>
            <option value="Phone">Phone</option>
        </select>

        <input class="btn" type="submit" value="Register">
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
