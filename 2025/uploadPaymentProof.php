<?php
include 'sessionManager.php';
requireLogin('parent'); // Only logged-in parents can upload proof

include 'gijangovelockersystem.php';

$errors = [];
$success = false;

// Optional max file size settings (2MB limit)
ini_set('upload_max_filesize', '2M');
ini_set('post_max_size', '2M');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentID = $_POST['paymentID'] ?? null;

    if (!$paymentID) {
        $errors[] = "Missing payment ID.";
    }

    if (!isset($_FILES['proofOfPayment']) || $_FILES['proofOfPayment']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "No file uploaded or file upload error.";
    }

    if (empty($errors)) {
        $fileTmp = $_FILES['proofOfPayment']['tmp_name'];
        $fileType = mime_content_type($fileTmp);
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];

        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid file type. Only PDF, JPG, and PNG are allowed.";
        }

        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (empty($errors)) {
            $originalName = basename($_FILES['proofOfPayment']['name']);
            $uniqueName = uniqid('proof_') . "_" . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalName);
            $targetPath = $uploadDir . $uniqueName;

            if (move_uploaded_file($fileTmp, $targetPath)) {
                // Update payments table
                $stmt = $pdo->prepare("UPDATE payments SET proofOfPayment = ? WHERE paymentID = ?");
                $stmt->execute([$targetPath, $paymentID]);

                $success = true;
                // Redirect to avoid resubmission
                header("Location: viewStudentInfo.php?paymentUpload=success");
                exit;
            } else {
                $errors[] = "Failed to move uploaded file.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Proof of Payment</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<main>
    <div class="container">
        <h2>Upload Proof of Payment</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-border">
                <label for="proofOfPayment">Select File (PDF, JPG, PNG):</label>
                <input type="file" name="proofOfPayment" id="proofOfPayment" required>

                <input type="hidden" name="paymentID" value="<?= htmlspecialchars($_GET['paymentID'] ?? $_POST['paymentID'] ?? '') ?>">

                <br>
                <input type="submit" class="btn" value="Upload File">
            </div>
        </form>

        <br>
        <a href="viewStudentInfo.php" class="btn-secondary">&larr; Back to Student Info</a>
    </div>
</main>
</body>
</html>
