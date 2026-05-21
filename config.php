<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../db.php';

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$_SESSION['user']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user does not exist in DB anymore, or is not active, destroy session and redirect
if (!$user || $user['account_status'] !== 'active') {
    session_destroy();
    header('Location: ../login.php');
    exit;
}
?>
