<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!file_exists('config.php')) { header("Location: install.php"); exit; }
require_once 'config.php';
try { $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } 
catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
if (session_status() === PHP_SESSION_NONE) session_start();

$query = trim($_GET['q'] ?? '');
$tag_id = (int)($_GET['tag'] ?? 0);
$posts = [];

if ($query || $tag_id) {
    if ($tag_id) {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username FROM posts p 
            JOIN users u ON p.user_id = u.id 
            JOIN post_tags pt ON p.id = pt.post_id 
            WHERE p.status = 'approved' AND pt.tag_id = ? 
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$tag_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username FROM posts p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.status = 'approved' AND (p.title LIKE ? OR p.description LIKE ?) 
            ORDER BY p.created_at DESC
        ");
        $term = "%$query%";
        $stmt->execute([$term, $term]);
    }
    $posts = $stmt->fetchAll();
}

$all_tags = $pdo->query("SELECT * FROM tags ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Поиск | AniHou</title>
<style>
:root{--bg:#0f0f13;--card:rgba(30,30,40,0.7);--text:#e0e0e0;--accent:#bb86fc;--border:1px solid rgba(255,255,255,0.1)}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif}
a{color:inherit;text-decoration:none}
.container{max-width:1200px;margin:0 auto;padding:20px}
nav{display:flex;justify-content:space-between;padding:15px 20px;background:var(--card);border-bottom:var(--border)}
.nav-links a{margin-left:20px}.nav-links a:hover{color:var(--accent)}
.search-form{display:flex;gap:10px;margin:30px 0}
.search-form input,.search-form select{padding:12px;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:5px;color:white}
.search-form input{flex:1}
.search-form button{padding:12px 25px;background:var(--accent);border:none;border-radius:5px;color:white;cursor:pointer}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px}
.card{background:var(--card);border:var(--border);border-radius:15px;overflow:hidden}
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
		<a href="profile.php">Профиль</a>
		<a href="settings.php">Настройки</a>
		<a href="index.php"></a>
        <?php if(isset($_SESSION['user_id'])): ?>
        <a href="logout.php">Выход</a>
        <?php else: ?>
        <a href="login.php">Вход</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container">
    <h1>Поиск</h1>
	<h2 style="font-weight:bold;color:var(--accent)">Введите  название картинки а так же тег по желанию</h2>
    <form method="GET" class="search-form">
        <input type="text" name="q" placeholder="Поиск по названию..." value="<?= htmlspecialchars($query) ?>">
        <select name="tag">
            <option value="0">Все теги</option>
            <?php foreach($all_tags as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $tag_id == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Найти</button>
    </form>

    <?php if($query || $tag_id): ?>
    <div class="grid">
        <?php if(empty($posts)): ?>
        <div class="empty" style="grid-column:1/-1">Ничего не найдено</div>
        <?php else: ?>
        <?php foreach($posts as $p): ?>
        <div class="card">
            <a href="post.php?id=<?= (int)$p['id'] ?>">
                <img src="<?= file_exists('uploads/'.$p['image']) ? 'uploads/'.$p['image'] : 'https://via.placeholder.com/300' ?>" alt="">            </a>
            <div class="card-body">
                <small>@<?= htmlspecialchars($p['username']) ?></small>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<footer>&copy; <?= date('Y') ?> AniHou | Все права защищены.</footer>
</body>
</html>