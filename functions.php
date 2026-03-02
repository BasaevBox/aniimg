<?php
require_once 'db.php';

// Защита от повторного объявления
if (!function_exists('redirect')) {
    function redirect($url) { header("Location: $url"); exit; }
}

if (!function_exists('getTags')) {
    function getTags($pdo) {
        return $pdo->query("SELECT * FROM tags")->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('uploadImage')) {
    function uploadImage($file) {
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($ext), $allowed)) return false;
        $filename = uniqid() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename);
        return $filename;
    }
}

if (!function_exists('isAdminOrMod')) {
    function isAdminOrMod() {
        return isset($_SESSION['role']) && ($_SESSION['role'] == 1 || $_SESSION['role'] == 2);
    }
}


if (!function_exists('getStats')) {
    function getStats($pdo) {
        $stats = [];
        $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['posts'] = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
        $stats['pending'] = $pdo->query("SELECT COUNT(*) FROM posts WHERE status='pending'")->fetchColumn();
        $stats['likes'] = $pdo->query("SELECT COUNT(*) FROM likes")->fetchColumn();
        return $stats;
    }
}
?>