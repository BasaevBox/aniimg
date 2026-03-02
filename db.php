<?php
if (!file_exists('config.php')) {
    die("Ошибка: config.php не найден. Запустите install.php");
}
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (session_status() === PHP_SESSION_NONE) session_start();
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Функции с защитой от повторного объявления
if (!function_exists('checkAuth')) {
    function checkAuth() { return isset($_SESSION['user_id']); }
}

if (!function_exists('isAdmin')) {
    function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] == 1; }
}
?>