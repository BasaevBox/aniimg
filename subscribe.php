<?php
if (!file_exists('config.php')) { header("Location: install.php"); exit; }
require_once 'config.php';
try { $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } 
catch (PDOException $e) { die("DB Error"); }
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$action = $_GET['action'] ?? '';
$target = (int)($_GET['target'] ?? 0);

if ($target > 0 && $target != $_SESSION['user_id']) {
    if ($action == 'sub') {
        $pdo->prepare("INSERT IGNORE INTO subscriptions (follower_id, following_id) VALUES (?, ?)")->execute([$_SESSION['user_id'], $target]);
    } elseif ($action == 'unsub') {
        $pdo->prepare("DELETE FROM subscriptions WHERE follower_id=? AND following_id=?")->execute([$_SESSION['user_id'], $target]);
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: $referer");
exit;
?>
