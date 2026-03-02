<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!file_exists('config.php')) { header("Location: install.php"); exit; }
require_once 'config.php';

try { 
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); 
} catch (PDOException $e) { 
    die("DB Error: " . $e->getMessage()); 
}

if (session_status() === PHP_SESSION_NONE) session_start();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
if ($user_id == 0) { header("Location: login.php"); exit; }

// Безопасный запрос с проверкой существования колонок
$columns = ['id', 'username', 'email', 'password', 'bio', 'avatar', 'role', 'created_at'];
// Проверяем и добавляем новые поля если они есть в БД
$check_cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'social_%'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['social_vk', 'social_tg', 'social_yt', 'website', 'location', 'birthdate'] as $col) {
    if (in_array($col, $check_cols)) $columns[] = $col;
}

$user = $pdo->prepare("SELECT ".implode(', ', $columns)." FROM users WHERE id = ?");
$user->execute([$user_id]);
$data = $user->fetch();

if (!$data) { die("Пользователь не найден"); }

// Посты одобренные
$posts = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC");
$posts->execute([$user_id]);
$my_posts = $posts->fetchAll();

// Посты на модерации
$pending_posts = [];
$queue_position = 0;
$can_see_pending = (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $user_id || $_SESSION['role'] >= 1));
if ($can_see_pending) {
    $pending = $pdo->prepare("SELECT * FROM posts WHERE user_id = ? AND status = 'pending' ORDER BY created_at ASC");
    $pending->execute([$user_id]);
    $pending_posts = $pending->fetchAll();
    if (!empty($pending_posts)) {
        $qp = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE status='pending' AND created_at <= ?");
        $qp->execute([$pending_posts[0]['created_at']]);
        $queue_position = $qp->fetchColumn();
    }
}
// Подписка
$sub_btn = "";
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user_id && ($data['role'] ?? 0) != -1) {
    $check = $pdo->prepare("SELECT * FROM subscriptions WHERE follower_id=? AND following_id=?");
    $check->execute([$_SESSION['user_id'], $user_id]);
    $sub_btn = $check->rowCount() > 0 
        ? '<a href="subscribe.php?action=unsub&target='.$user_id.'" class="btn btn-outline">Отписаться</a>'
        : '<a href="subscribe.php?action=sub&target='.$user_id.'" class="btn">Подписаться</a>';
}

$followers = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE following_id=$user_id")->fetchColumn();
$following = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE follower_id=$user_id")->fetchColumn();

// Бейдж роли
function getRoleBadge($role) {
    if ($role == -1) return '<span class="badge badge-banned">BAN</span>';
    if ($role == 1) return '<span class="badge badge-admin">ADMIN</span>';
    if ($role == 2) return '<span class="badge badge-mod">MODER</span>';
    return '';
}

// Безопасное получение соцсетей
function getSocial($data, $key) {
    return isset($data[$key]) && !empty($data[$key]) ? htmlspecialchars($data[$key]) : '';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@<?= htmlspecialchars($data['username']) ?> | AniHou </title>
<style>
:root{--bg:#0f0f13;--card:rgba(30,30,40,0.7);--text:#e0e0e0;--accent:#bb86fc;--border:1px solid rgba(255,255,255,0.1);--danger:#cf6679;--warning:#ffb74d;--mod:#03dac6}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;word-wrap:break-word}
a{color:inherit;text-decoration:none}
.container{max-width:1000px;margin:0 auto;padding:20px}
nav{display:flex;justify-content:space-between;padding:15px 20px;background:var(--card);border-bottom:var(--border);position:sticky;top:0;z-index:100}
.nav-links a{margin-left:20px;font-weight:500}.nav-links a:hover{color:var(--accent)}
.profile-header{text-align:center;background:var(--card);padding:40px 20px;border-radius:15px;border:var(--border);margin:30px 0}
.avatar-wrapper{position:relative;display:inline-block;margin-bottom:20px}
.avatar-lg{width:150px;height:150px;border-radius:50%;object-fit:cover;border:4px solid var(--accent)}
.profile-info h1{color:var(--accent);margin:10px 0;font-size:1.6em;display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap}
.profile-info p{color:#aaa;line-height:1.6;margin:15px auto;max-width:600px;white-space:pre-wrap}
.social-links{display:flex;justify-content:center;gap:15px;margin:15px 0;flex-wrap:wrap}
.social-links a{padding:6px 15px;background:rgba(187,134,252,0.15);border-radius:20px;font-size:0.9em;color:var(--accent)}.social-links a:hover{background:var(--accent);color:#000}
.stats{display:flex;justify-content:center;gap:30px;margin:20px 0;color:#888}
.stat strong{color:var(--text);font-size:1.1em}
.btn{display:inline-block;padding:10px 25px;background:var(--accent);color:white;border-radius:8px;margin:8px 5px;font-weight:500}.btn:hover{background:#9955e8}
.btn-outline{background:transparent;border:1px solid var(--accent);color:var(--accent)}.btn-outline:hover{background:var(--accent);color:white}.badge{padding:4px 12px;border-radius:20px;font-size:0.75em;font-weight:bold;margin-left:5px}
.badge-admin{background:var(--accent);color:#000}.badge-mod{background:var(--mod);color:#000}.badge-banned{background:var(--danger);color:white}
.moderation-box{background:rgba(255,183,77,0.1);border:1px solid var(--warning);border-radius:12px;padding:20px;margin:30px 0;text-align:left}
.moderation-box h3{color:var(--warning);margin-bottom:15px}
.pending-item{display:flex;justify-content:space-between;align-items:center;padding:12px;background:rgba(0,0,0,0.2);border-radius:8px;margin-bottom:10px}
.pending-item img{width:50px;height:50px;border-radius:6px;object-fit:cover}
.queue-badge{background:var(--warning);color:#000;padding:3px 12px;border-radius:20px;font-size:0.8em;font-weight:bold}
.section-title{color:var(--accent);margin:40px 0 20px;font-size:1.3em;text-align:center}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:15px}
.card{background:var(--card);border:var(--border);border-radius:12px;overflow:hidden}.card img{width:100%;height:180px;object-fit:cover}
.empty{text-align:center;padding:50px;color:#666;background:var(--card);border-radius:12px}
footer{text-align:center;padding:25px;color:#666;margin-top:40px;border-top:var(--border)}
@media(max-width:600px){.stats{flex-wrap:wrap}.grid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<nav>
    <a href="index.php" style="font-weight:bold;color:var(--accent);font-size:1.3em">AniHou </a>
    <div class="nav-links">
        <a href="index.php">Главная</a>
        <?php if(isset($_SESSION['user_id'])): ?><a href="post.php">Загрузить</a><a href="logout.php">Выход</a><?php else: ?><a href="login.php">Вход</a><?php endif; ?>
    </div>
</nav>

<div class="container">
    <div class="profile-header">
        <div class="avatar-wrapper">
            <img src="<?= file_exists('uploads/'.($data['avatar'] ?? 'default.png')) ? 'uploads/'.($data['avatar'] ?? 'default.png') : 'https://via.placeholder.com/150' ?>" class="avatar-lg" alt="">
        </div>
        <div class="profile-info">
            <h1>@<?= htmlspecialchars($data['username']) ?> <?= getRoleBadge($data['role'] ?? 0) ?></h1>
            <p><?= htmlspecialchars($data['bio'] ?? 'Нет информации') ?></p>
            
            <!-- Соцсети (только если поля существуют) -->
            <?php if(!empty(getSocial($data, 'social_vk')) || !empty(getSocial($data, 'social_tg')) || !empty(getSocial($data, 'social_yt')) || !empty(getSocial($data, 'website'))): ?>
            <div class="social-links">
                <?php if(!empty(getSocial($data, 'social_vk'))): ?><a href="<?= getSocial($data, 'social_vk') ?>" target="_blank">VK</a><?php endif; ?>
                <?php if(!empty(getSocial($data, 'social_tg'))): ?><a href="<?= getSocial($data, 'social_tg') ?>" target="_blank">Telegram</a><?php endif; ?>
                <?php if(!empty(getSocial($data, 'social_yt'))): ?><a href="<?= getSocial($data, 'social_yt') ?>" target="_blank">YouTube</a><?php endif; ?>
                <?php if(!empty(getSocial($data, 'website'))): ?><a href="<?= getSocial($data, 'website') ?>" target="_blank">YouTube</a><?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="stats">
                <div class="stat"><strong><?= count($my_posts) ?></strong> работ</div>
                <div class="stat"><strong><?= (int)$followers ?></strong> подписчиков</div>
                <div class="stat"><strong><?= (int)$following ?></strong> подписок</div>
            </div>
            <div>
                <?= $sub_btn ?>                <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id): ?>
                    <a href="settings.php" class="btn btn-outline">Редактировать профиль</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if(!empty($pending_posts) && $can_see_pending): ?>
    <div class="moderation-box">
        <h3>⏳ На модерации (<?= count($pending_posts) ?>)</h3>
        <?php foreach($pending_posts as $p): ?>
        <div class="pending-item">
            <div style="display:flex;align-items:center;gap:12px">
                <img src="<?= file_exists('uploads/'.$p['image']) ? 'uploads/'.$p['image'] : 'https://via.placeholder.com/50' ?>" alt="">
                <div><strong><?= htmlspecialchars($p['title']) ?></strong><div style="font-size:0.85em;color:#888"><?= date('d.m H:i', strtotime($p['created_at'])) ?></div></div>
            </div>
            <span class="queue-badge">#<?= $queue_position ?> в очереди</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2 class="section-title">Работы</h2>
    <?php if(empty($my_posts)): ?>
    <div class="empty"><p>Нет опубликованных работ</p></div>
    <?php else: ?>
    <div class="grid">
        <?php foreach($my_posts as $p): ?>
        <div class="card"><a href="post.php?id=<?= (int)$p['id'] ?>"><img src="<?= file_exists('uploads/'.$p['image']) ? 'uploads/'.$p['image'] : 'https://via.placeholder.com/300' ?>" alt=""></a></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<footer>&copy; <?= date('Y') ?> AniHou | Все права защищены.</footer>
</body></html>