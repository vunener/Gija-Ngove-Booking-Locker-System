<?php
// Database configuration
$host     = 'localhost';
$dbname   = 'gijangovelockersystem';
$username = 'root';
$password = ''; // Change in production!

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Never expose detailed errors in production
    die("Database connection failed. Please try again later.");
    // For debugging (DEV ONLY): echo "Error: " . $e->getMessage();
}
