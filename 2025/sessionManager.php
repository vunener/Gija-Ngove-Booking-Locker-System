<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout duration in seconds (30 minutes)
$timeout_duration = 1800;

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();

/**
 * Require user login with optional role(s)
 * 
 * @param string|array|null $requiredUserType Single role or array of allowed roles
 */
function requireLogin($requiredUserType = null) {
    // If user not logged in, redirect to login page
    if (!isset($_SESSION['userType'])) {
        header("Location: login.php");
        exit;
    }

    // If roles are specified, check if user role is allowed
    if ($requiredUserType) {
        if (is_array($requiredUserType)) {
            if (!in_array($_SESSION['userType'], $requiredUserType)) {
                redirectBasedOnRole($_SESSION['userType']);
            }
        } else {
            if ($_SESSION['userType'] !== $requiredUserType) {
                redirectBasedOnRole($_SESSION['userType']);
            }
        }
    }
}

/**
 * Redirect user to their appropriate dashboard based on role
 * 
 * @param string $role
 */
function redirectBasedOnRole($role) {
    switch ($role) {
        case 'admin':
            header("Location: adminDashboard.php");
            break;
        case 'parent':
            header("Location: parentDashboard.php");
            break;
        default:
            header("Location: login.php");
            break;
    }
    exit;
}

/**
 * Get current logged in user type (optional helper)
 * 
 * @return string|null
 */
function getUserType() {
    return $_SESSION['userType'] ?? null;
}
?>
