<?php
session_start();

if (isset($_SESSION['user']) && $_SESSION['user_type'] === 'rider') {
    require_once 'db.php';
    $stmt = $pdo->prepare("UPDATE riders SET Rider_Status = 'offline' WHERE Rider_Email = ?");
    $stmt->execute([$_SESSION['user']]);
}

session_unset();
session_destroy();
header('Location: login.php');
exit;
