<?php
session_start();
$msg = "";

if (file_exists('config.php')) {
    die("<h1>Сайт уже установлен</h1><p>Удалите config.php для переустановки</p><a href='index.php'>На главную</a>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = $_POST['host'];
    $dbname = $_POST['dbname'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];
    $admin_user = $_POST['admin_user'];
    $admin_email = $_POST['admin_email'];
    $admin_pass = $_POST['admin_pass'];
    
    try {
        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                bio TEXT,
                avatar VARCHAR(255) DEFAULT 'default.png',
                role INT DEFAULT 0,
                social_vk VARCHAR(255),
                social_tg VARCHAR(255),
                social_yt VARCHAR(255),
                website VARCHAR(255),
                location VARCHAR(100),
                birthdate DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS posts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(150) NOT NULL,
                description TEXT,
                image VARCHAR(255) NOT NULL,                status ENUM('pending','approved','rejected') DEFAULT 'pending',
                views INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) UNIQUE NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS post_tags (
                post_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY (post_id, tag_id),
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS likes (
                user_id INT NOT NULL,
                post_id INT NOT NULL,
                PRIMARY KEY (user_id, post_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS favorites (
                user_id INT NOT NULL,
                post_id INT NOT NULL,
                PRIMARY KEY (user_id, post_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subscriptions (
                follower_id INT NOT NULL,
                following_id INT NOT NULL,
                PRIMARY KEY (follower_id, following_id),
                FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,                FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 1)");
        $stmt->execute([$admin_user, $admin_email, $hash]);
        
        $tags = ['арт','рисунок','digital','скетч','аниме','персонаж','пейзаж','портрет','фанарт','оригинал'];
        $stmt = $pdo->prepare("INSERT IGNORE INTO tags (name) VALUES (?)");
        foreach ($tags as $t) { $stmt->execute([$t]); }
        
        $config = "<?php
define('DB_HOST', '$host');
define('DB_NAME', '$dbname');
define('DB_USER', '$user');
define('DB_PASS', '$pass');
define('UPLOAD_DIR', 'uploads/');
?>";
        file_put_contents('config.php', $config);
        
        if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
        
        $msg = "<div style='background:#2e7d32;color:white;padding:20px;border-radius:8px;margin:20px 0;'>
            <h2>✅ Установка завершена!</h2>
            <p>Админ: <b>$admin_email</b></p>
            <a href='login.php' style='color:white;background:#000;padding:10px 20px;border-radius:5px;display:inline-block;margin-top:10px;'>Войти</a>
            <a href='index.php' style='color:white;background:#bb86fc;padding:10px 20px;border-radius:5px;display:inline-block;margin-top:10px;margin-left:10px;'>На сайт</a>
        </div>";
        
    } catch (PDOException $e) {
        $msg = "<div style='background:#cf6679;color:white;padding:20px;border-radius:8px;margin:20px 0;'>
            ❌ Ошибка: " . htmlspecialchars($e->getMessage()) . "
        </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Установка ArtShare</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f0f13;color:#e0e0e0;font-family:'Segoe UI',sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px}
.box{background:rgba(30,30,40,0.7);padding:40px;border-radius:15px;border:1px solid rgba(255,255,255,0.1);width:100%;max-width:450px}
h1{color:#bb86fc;text-align:center;margin-bottom:30px}
.form-group{margin-bottom:20px}
label{display:block;margin-bottom:8px;color:#aaa;font-size:0.9em}input{width:100%;padding:12px;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:8px;color:white;font-size:1em}
input:focus{outline:none;border-color:#bb86fc}
button{width:100%;padding:14px;background:#bb86fc;border:none;border-radius:8px;color:white;font-weight:bold;font-size:1.1em;cursor:pointer;margin-top:10px}
button:hover{background:#9955e8}
hr{border:none;border-top:1px solid #333;margin:25px 0}
.small{font-size:0.8em;color:#888}
</style>
</head>
<body>
<div class="box">
    <h1>🎨 Установка ArtShare</h1>
    <?= $msg ?>
    <form method="POST">
        <div class="form-group">
            <label>Хост MySQL</label>
            <input type="text" name="host" value="localhost" required>
        </div>
        <div class="form-group">
            <label>Имя базы данных</label>
            <input type="text" name="dbname" required>
        </div>
        <div class="form-group">
            <label>Пользователь MySQL</label>
            <input type="text" name="user" value="root" required>
        </div>
        <div class="form-group">
            <label>Пароль MySQL</label>
            <input type="password" name="pass">
        </div>
        <hr>
        <div class="form-group">
            <label>Имя админа</label>
            <input type="text" name="admin_user" required>
        </div>
        <div class="form-group">
            <label>Email админа</label>
            <input type="email" name="admin_email" value="anihou@vk.com" required>
        </div>
        <div class="form-group">
            <label>Пароль админа</label>
            <input type="password" name="admin_pass" required minlength="6">
        </div>
        <button type="submit">Установить</button>
        <p class="small" style="text-align:center;margin-top:15px">Пароль мин. 6 символов</p>
    </form>
</div>
</body>
</html>