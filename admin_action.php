<?php
require_once 'functions.php';
if (!isAdmin()) redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_post'])) {
        $post_id = (int)$_POST['post_id'];
        // Получаем имя файла перед удалением
        $stmt = $pdo->prepare("SELECT image FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();
        
        if ($post) {
            // Удаляем файл
            $file_path = UPLOAD_DIR . $post['image'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            // Удаляем запись из БД (каскад удалит теги и лайки)
            $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$post_id]);
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        if ($user_id != $_SESSION['user_id']) { // Нельзя удалить себя
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        }
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'admin.php';
header("Location: $referer");
exit;
?>