<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!file_exists('config.php')) { header("Location: install.php"); exit; }
require_once 'config.php';
try { $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } 
catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$uid = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$uid]);
$data = $user->fetch();

$msg = ""; $success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Аватар
    $avatar = $data['avatar'];
    if (!empty($_FILES['avatar']['name'])) {
        if (!is_dir('uploads')) mkdir('uploads', 0777, true);
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'])) {
            $avatar = uniqid().'.'.$ext;
            move_uploaded_file($_FILES['avatar']['tmp_name'], 'uploads/'.$avatar);
        }
    }
    // Обновление данных
    $updates = []; $params = [];
    
    if (!empty($_POST['username']) && $_POST['username'] !== $data['username']) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username=? AND id!=?");
        $check->execute([trim($_POST['username']), $uid]);
        if (!$check->fetch()) { $updates[] = "username=?"; $params[] = trim($_POST['username']); }
        else { $msg = "Имя занято"; }
    }
    if (!empty($_POST['email']) && $_POST['email'] !== $data['email']) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $check->execute([trim($_POST['email']), $uid]);
        if (!$check->fetch()) { $updates[] = "email=?"; $params[] = trim($_POST['email']); }
        else { $msg = "Email занят"; }
    }
    if (!empty($_POST['password'])) {
        if (strlen($_POST['password']) >= 6) {
            $updates[] = "password=?"; $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        } else { $msg = "Пароль мин. 6 символов"; }
    }
    $updates[] = "bio=?"; $params[] = trim($_POST['bio'] ?? '');
    $updates[] = "avatar=?"; $params[] = $avatar;
    $updates[] = "social_vk=?"; $params[] = trim($_POST['social_vk'] ?? '');    $updates[] = "social_tg=?"; $params[] = trim($_POST['social_tg'] ?? '');
    $updates[] = "social_yt=?"; $params[] = trim($_POST['social_yt'] ?? '');
    
    if (empty($msg) && !empty($updates)) {
        $params[] = $uid;
        $sql = "UPDATE users SET ".implode(', ', $updates)." WHERE id=?";
        if ($pdo->prepare($sql)->execute($params)) {
            $success = "Профиль обновлен!";
            $_SESSION['avatar'] = $avatar;
            $data = $pdo->prepare("SELECT * FROM users WHERE id=?"); $data->execute([$uid]); $data = $data->fetch();
        } else { $msg = "Ошибка БД"; }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Настройки | AniHou</title>
<style>
:root{--bg:#0f0f13;--card:rgba(30,30,40,0.7);--text:#e0e0e0;--accent:#bb86fc;--danger:#cf6679;--border:1px solid rgba(255,255,255,0.1)}
*{box-sizing:border-box;margin:0;padding:0}body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif}
a{color:inherit;text-decoration:none}.container{max-width:600px;margin:0 auto;padding:20px}
nav{display:flex;justify-content:space-between;padding:15px 20px;background:var(--card);border-bottom:var(--border)}
.nav-links a{margin-left:20px}.nav-links a:hover{color:var(--accent)}
.form-box{background:var(--card);padding:30px;border-radius:15px;border:var(--border);margin:30px 0}
.form-box h2{color:var(--accent);margin-bottom:20px;text-align:center}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:500;color:#aaa}
input,textarea{width:100%;padding:12px;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:8px;color:white;font-size:1em}
input:focus,textarea:focus{outline:none;border-color:var(--accent)}
textarea{min-height:100px;resize:vertical}
.avatar-preview{width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);display:block;margin:0 auto 15px}
.btn{width:100%;padding:14px;background:var(--accent);border:none;border-radius:8px;color:white;font-weight:bold;font-size:1.1em;cursor:pointer;margin-top:10px}
.btn:hover{background:#9955e8}
.alert{padding:12px;border-radius:8px;margin-bottom:20px;text-align:center}
.alert-error{background:rgba(207,102,121,0.2);color:var(--danger)}
.alert-success{background:rgba(100,200,100,0.2);color:#66ff66}
.social-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
footer{text-align:center;padding:20px;color:#666;margin-top:40px;border-top:var(--border)}
</style>
</head>
<body>
<nav>
    <a href="index.php" style="font-weight:bold;color:var(--accent)">AniHou</a>
    <div class="nav-links"><a href="profile.php">Профиль</a><a href="logout.php">Выход</a></div>
</nav>
<div class="container">
    <div class="form-box">
        <h2>Настройки профиля</h2>        <?php if($msg): ?><div class="alert alert-error"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group" style="text-align:center">
                <img src="<?= file_exists('uploads/'.$data['avatar']) ? 'uploads/'.$data['avatar'] : 'https://via.placeholder.com/120' ?>" class="avatar-preview">
				<label>Аватарка</label>
                <input type="file" name="avatar" accept="image/*">
            </div>
            
            <div class="form-group">
                <label>Имя пользователя</label>
                <input type="text" name="username" value="<?= htmlspecialchars($data['username']) ?>" placeholder="@username">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>">
            </div>
            <div class="form-group">
                <label>Новый пароль (оставьте пустым, чтобы не менять)</label>
                <input type="password" name="password" placeholder="••••••">
            </div>
            <div class="form-group">
                <label>Описание</label>
                <textarea name="bio" placeholder="Расскажите о себе..."><?= htmlspecialchars($data['bio'] ?? '') ?></textarea>
            </div>
            
            <label style="color:var(--accent);font-weight:500;margin:20px 0 10px;display:block">Соцсети</label>
            <div class="social-grid">
                <div class="form-group"><label>VK</label><input type="url" name="social_vk" placeholder="https://vk.com/..." value="<?= htmlspecialchars($data['social_vk'] ?? '') ?>"></div>
                <div class="form-group"><label>Telegram</label><input type="url" name="social_tg" placeholder="https://t.me/..." value="<?= htmlspecialchars($data['social_tg'] ?? '') ?>"></div>
                <div class="form-group"><label>YouTube</label><input type="url" name="social_yt" placeholder="https://youtube.com/..." value="<?= htmlspecialchars($data['social_yt'] ?? '') ?>"></div>
                <div class="form-group"><label>WebSite</label><input type="url" name="website" placeholder="https://..." value="<?= htmlspecialchars($data['website'] ?? '') ?>"></div>
            </div>
            
            <button type="submit" class="btn">Сохранить изменения</button>
        </form>
    </div>
</div>
<footer>&copy; <?= date('Y') ?> AniHou | Все права защищены.</footer>
</body></html>