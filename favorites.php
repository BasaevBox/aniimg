<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!file_exists('config.php')) { header("Location: install.php"); exit; }
require_once 'config.php';
try { $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } 
catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$stmt = $pdo->prepare("
    SELECT p.*, u.username 
    FROM posts p 
    JOIN favorites f ON p.id = f.post_id 
    JOIN users u ON p.user_id = u.id 
    WHERE f.user_id = ? AND p.status = 'approved'
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$favorites = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Избранное | AniHou </title>
<link rel="stylesheet" href="style.css">
<style>
:root{--bg:#0f0f13;--card:rgba(30,30,40,0.7);--text:#e0e0e0;--accent:#bb86fc;--border:1px solid rgba(255,255,255,0.1)}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif}
a{color:inherit;text-decoration:none}
.container{max-width:1200px;margin:0 auto;padding:20px}
nav{display:flex;justify-content:space-between;padding:15px 20px;background:var(--card);border-bottom:var(--border)}
.nav-links a{margin-left:20px}
.nav-links a:hover{color:var(--accent)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;margin:30px 0}
.card{background:var(--card);border:var(--border);border-radius:15px;overflow:hidden}
.card:hover{transform:translateY(-5px)}
.card img{width:100%;height:250px;object-fit:cover}
.card-body{padding:15px}
.card-body small{color:#888}
.empty{text-align:center;padding:60px;color:#888}
h1{color:var(--accent);margin:20px 0}
footer{text-align:center;padding:20px;color:#666;margin-top:40px;border-top:var(--border)}
</style>
</head>
<body>
<nav>
    <a href="index.php" style="font-weight:bold;color:var(--accent)">AniHou</a>
    <div class="nav-links">
        <a href="index.php">Главная</a>
		<a href="post.php">Загрузить</a>
        <a href="profile.php">Профиль</a>
        <a href="logout.php">Выход</a>
    </div>
</nav>

<div class="container">
    <h1>Избранное</h1>
    <?php if(empty($favorites)): ?>
    <div class="empty">
        <p>У вас пока нет избранных работ</p>
        <br>
        <a href="index.php" style="color:var(--accent)">Смотреть все работы →</a>
    </div>
    <?php else: ?>
    <div class="grid">
        <?php foreach($favorites as $p): ?>
        <div class="card">
            <a href="post.php?id=<?= (int)$p['id'] ?>">
                <img src="<?= file_exists('uploads/'.$p['image']) ? 'uploads/'.$p['image'] : 'https://via.placeholder.com/300' ?>" alt="">
            </a>
            <div class="card-body">
                <small>@<?= htmlspecialchars($p['username']) ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<footer>&copy; <?= date('Y') ?> AniHou | Все права защищены.</footer>
</body>
</html>