<?php
include 'sessionManager.php';
requireLogin('parent');

include 'gijangovelockersystem.php';

// Validate input
$studentID = isset($_GET['studentID']) ? (int)$_GET['studentID'] : 0;

if ($studentID <= 0) {
    header("Location: viewStudentInfo.php?error=invalid_id");
    exit;
}

try {
    // Confirm that student belongs to the logged-in parent
    $stmtCheck = $pdo->prepare("SELECT parentID FROM students WHERE studentID = ?");
    $stmtCheck->execute([$studentID]);
    $student = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$student || (int)$student['parentID'] !== (int)$_SESSION['userID']) {
        header("Location: viewStudentInfo.php?error=unauthorized");
        exit;
    }

    // Delete student
    $stmtDelete = $pdo->prepare("DELETE FROM students WHERE studentID = ?");
    $stmtDelete->execute([$studentID]);

    header("Location: viewStudentInfo.php?deleted=1");
    exit;

} catch (PDOException $e) {
    // Log the error in a real system (file, DB, etc.)
    header("Location: viewStudentInfo.php?error=db_error");
    exit;
}
