<?php
include 'gijangovelockersystem.php';
include 'gijaNgovelockerFunction.php';
include 'menu.inc';

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parentTitle = trim($_POST['Title'] ?? '');
    $parentIDNumber = trim($_POST['idNumber'] ?? '');
    $parentName = trim($_POST['firstName'] ?? '');
    $parentSurname = trim($_POST['lastName'] ?? '');
    $parentEmailAddress = trim($_POST['email'] ?? '');
    $parentPhoneNumber = trim($_POST['phone'] ?? '');
    $parentHomeAddress = trim($_POST['address'] ?? '');
    $parentUsername = trim($_POST['username'] ?? '');
    $parentPassword = $_POST['password'] ?? '';
    $confirm = $_POST['confirmPassword'] ?? '';
    $preferredNotification = $_POST['preferredNotification'] ?? '';

    // Validate required fields
    if (!$parentUsername || !$parentPassword || !$confirm || !$parentName || !$parentSurname) {
        $errors[] = "All fields except phone and address are required.";
    }

    // Check password match
    if ($parentPassword !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Check if username exists
    $existing = getParentByUsername($pdo, $parentUsername);
    if ($existing) {
        $errors[] = "Username already exists.";
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $hashedPassword = password_hash($parentPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO parents (parentTitle, parentIDNumber, parentName, parentSurname, parentEmailAddress, parentPhoneNumber, parentHomeAddress, parentUsername, parentPassword, preferredNotification, dateCreated) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$parentTitle, $parentIDNumber, $parentName, $parentSurname, $parentEmailAddress, $parentPhoneNumber, $parentHomeAddress, $parentUsername, $hashedPassword, $preferredNotification]);
        $messages[] = "Registration successful. Please login.";
    }
}

// Display errors and messages
foreach ($errors as $error) {
    echo '<p class="error">' . htmlspecialchars($error) . '</p>';
}
foreach ($messages as $msg) {
    echo '<p style="color:green;">' . htmlspecialchars($msg) . '</p>';
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Parent Registration Form</title>
    <!-- <link rel="stylesheet" type="text/css" href="styles.css"> -->

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

        .containerR {
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

        .containerR fieldset {
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

        .containerR fieldset form {
            width: 100%;
            max-width: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .containerR form input,
        .containerR form select {
            width: 100%;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        .containerR nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .containerR nav ul li a {
            text-decoration: none;
            color: #007BFF;
            font-size: 4em;
            transition: color 0.3s;
        }

        .containerR nav ul li a:hover {
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
<div class="containerR">
   
<fieldset class="form-border">
    <legend class="legend">Parent Registration </legend>
    
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
</fieldset>

</div>


 <div class="page-container">
     
        <main>
    
        </main>

        <footer>
            <p>&copy; <?= date('Y') ?> Gija‑Ngove Locker System. All rights reserved.</p>
        </footer>
    </div>

</body>
</html>
