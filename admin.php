<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!file_exists('config.php')) { header("Location: install.php"); exit; }
require_once 'config.php';
try { $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } 
catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
if (session_status() === PHP_SESSION_NONE) session_start();

// Роли: -1=ban, 0=user, 1=admin, 2=moder
if (!isset($_SESSION['role']) || $_SESSION['role'] < 1) { header("Location: index.php"); exit; }
$main_admin = 'anihou@vk.com';
$is_main = (isset($_SESSION['email']) && $_SESSION['email'] === $main_admin);
$is_admin = ($_SESSION['role'] == 1);

// === ОБРАБОТКА POST ===
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Модерация постов
    if (isset($_POST['approve_post'])) {
        $pdo->prepare("UPDATE posts SET status='approved' WHERE id=?")->execute([(int)$_POST['post_id']]);
        header("Location: admin.php"); exit;
    }
    if (isset($_POST['reject_post'])) {
        $pid = (int)$_POST['post_id'];
        $img = $pdo->query("SELECT image FROM posts WHERE id=$pid")->fetchColumn();
        if ($img && file_exists('uploads/'.$img)) unlink('uploads/'.$img);
        $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$pid]);
        header("Location: admin.php"); exit;
    }
    // Теги
    if (isset($_POST['add_tag']) && !empty($_POST['tag_name'])) {
        $pdo->prepare("INSERT INTO tags (name) VALUES (?)")->execute([trim($_POST['tag_name'])]);
        header("Location: admin.php"); exit;
    }
    // Управление пользователями (только админ, не главный)
    if ($is_admin && isset($_POST['user_action'], $_POST['target_id'])) {
        $tid = (int)$_POST['target_id'];
        $target = $pdo->prepare("SELECT email, role FROM users WHERE id=?");
        $target->execute([$tid]); $t = $target->fetch();
        if ($t && $t['email'] !== $main_admin) { // Защита главного
            if ($_POST['user_action'] == 'ban') {
                $pdo->prepare("UPDATE users SET role=-1 WHERE id=?")->execute([$tid]);
            } elseif ($_POST['user_action'] == 'unban') {
                $pdo->prepare("UPDATE users SET role=0 WHERE id=?")->execute([$tid]);
            } elseif ($_POST['user_action'] == 'make_moder' && $is_main) {
                $pdo->prepare("UPDATE users SET role=2 WHERE id=?")->execute([$tid]);
            } elseif ($_POST['user_action'] == 'remove_moder' && $is_main) {
                $pdo->prepare("UPDATE users SET role=0 WHERE id=?")->execute([$tid]);
            } elseif ($_POST['user_action'] == 'make_admin' && $is_main) {
                $pdo->prepare("UPDATE users SET role=1 WHERE id=?")->execute([$tid]);
            } elseif ($_POST['user_action'] == 'remove_admin' && $is_main) {                $pdo->prepare("UPDATE users SET role=0 WHERE id=?")->execute([$tid]);
            }
        }
        header("Location: admin.php"); exit;
    }
}

// === ДАННЫЕ ===
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'posts' => $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM posts WHERE status='pending'")->fetchColumn(),
    'mods' => $pdo->query("SELECT COUNT(*) FROM users WHERE role=2")->fetchColumn()
];
$pending = $pdo->query("SELECT p.*, u.username FROM posts p JOIN users u ON p.user_id=u.id WHERE p.status='pending' ORDER BY p.created_at ASC")->fetchAll();
$users = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 100")->fetchAll();
$tags = $pdo->query("SELECT * FROM tags ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Админ панель | AniHou</title>
<style>
:root{--bg:#0f0f13;--card:rgba(30,30,40,0.7);--text:#e0e0e0;--accent:#bb86fc;--danger:#cf6679;--success:#66ff66;--warning:#ffb74d;--mod:#03dac6;--border:1px solid rgba(255,255,255,0.1)}
*{box-sizing:border-box;margin:0;padding:0}body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif}
a{color:inherit;text-decoration:none}.container{max-width:1400px;margin:0 auto;padding:20px}
nav{display:flex;justify-content:space-between;padding:15px 20px;background:var(--card);border-bottom:var(--border);position:sticky;top:0;z-index:100}
.nav-links a{margin-left:20px;font-weight:500}.nav-links a:hover{color:var(--accent)}
h1{color:var(--accent);margin:20px 0}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin:20px 0}
.stat{background:var(--card);padding:20px;border-radius:12px;border:var(--border);text-align:center}
.stat-num{font-size:2em;color:var(--accent);font-weight:bold}.stat-label{color:#888;font-size:0.9em}
.section{background:var(--card);padding:25px;border-radius:15px;border:var(--border);margin:25px 0}
.section h2{color:var(--accent);margin-bottom:20px;font-size:1.3em}
.post-item{display:flex;justify-content:space-between;align-items:center;padding:15px;border-bottom:var(--border)}
.post-item:last-child{border-bottom:none}.post-thumb{width:70px;height:70px;border-radius:8px;object-fit:cover;margin-right:15px}
.post-info{flex:1}.post-info strong{display:block;margin-bottom:5px}.post-info small{color:#888}
form{display:inline}
.btn{padding:6px 14px;border:none;border-radius:6px;cursor:pointer;font-size:0.9em;margin:2px;font-weight:500}
.btn-success{background:#2e7d32;color:white}.btn-danger{background:var(--danger);color:white}
.btn-warning{background:var(--warning);color:#000}.btn-mod{background:var(--mod);color:#000}
.btn-outline{background:transparent;border:1px solid #555;color:var(--text)}.btn-outline:hover{border-color:var(--accent)}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 10px;text-align:left;border-bottom:var(--border)}
th{color:#888;font-weight:500;font-size:0.9em}
.badge{padding:4px 10px;border-radius:20px;font-size:0.75em;font-weight:bold}
.badge-admin{background:var(--accent);color:#000}.badge-mod{background:var(--mod);color:#000}.badge-ban{background:var(--danger);color:white}
.tag-form{display:flex;gap:10px;margin-bottom:15px}
.tag-form input{flex:1;padding:10px;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:6px;color:white}footer{text-align:center;padding:20px;color:#666;margin-top:40px;border-top:var(--border)}
@media(max-width:768px){.post-item{flex-direction:column;align-items:flex-start;gap:10px}.post-thumb{width:100%;height:200px}}
</style>
</head>
<body>
<nav>
    <a href="index.php" style="font-weight:bold;color:var(--accent);font-size:1.3em">AniHou</a>
    <div class="nav-links"><a href="index.php">Главная</a><a href="logout.php">Выход</a><a href="profile.php">Профиль</a></div>
</nav>
<div class="container">
    
	<h1 style="color:red">ВНИМАНИЕ!</h1>
    <div class="section">
	<h2>1. на данную страницу может попасть только пользователь с ролью Admin и Moderator (1),(2). Если вы попали сюда по ошибке, просим вас немедленно покинуть ее и сообщить об этом разработчмку <a style="color:red" href="profile.php?id=1">Анатолию</a> с информацией о баге, за серъезный баг последует вознаграждение.</h2>
	<h2>2. модератора назначает лично администратор <a style="color:red" href="profile.php?id=1">Анатолий</a>.</h2>
	<h2>3. модератор может только модерировать контент, доступа к блокировке других он не имеет (т е после того как пользователь загрузил фотографию, она попадает сюда, ваша задача проверить, не нарушает ли она правила нашего рессурка). Правила вам должен был прислать администратор перед наймом на должность, если же нет - попростите прислать.</h2>
	<h2>4. не забывате отчитываться в общи чат команды о работе модератора, за это вам и платят).</h2>
                    </div>



<h1> Панель управления <?= $is_main ? '(ГЛАВНЫЙ)' : '' ?></h1>
    <div class="stats">
        <div class="stat"><div class="stat-num"><?= (int)$stats['users'] ?></div><div class="stat-label">Пользователей</div></div>
        <div class="stat"><div class="stat-num"><?= (int)$stats['posts'] ?></div><div class="stat-label">Постов</div></div>
        <div class="stat"><div class="stat-num"><?= (int)$stats['pending'] ?></div><div class="stat-label">На модерации</div></div>
        <div class="stat"><div class="stat-num"><?= (int)$stats['mods'] ?></div><div class="stat-label">Модераторов</div></div>
    </div>

    <!-- Модерация -->
    <div class="section">
        <h2> всего: <?= count($pending) ?></h2>
        <?php if(empty($pending)): ?><p style="color:#888">Нет постов на проверке</p><?php else: ?>
            <?php foreach($pending as $p): ?>
            <div class="post-item">
                <div style="display:flex;align-items:center">
                    <img src="<?= file_exists('uploads/'.$p['image']) ? 'uploads/'.$p['image'] : 'https://via.placeholder.com/70' ?>" class="post-thumb">
                    <div class="post-info">
                        <strong><?= htmlspecialchars($p['title']) ?></strong>
						<a href="<?= 'uploads/'.$p['image'] ?>" download class="download-link">скачать от</a>
						<small>@<?= htmlspecialchars($p['username']) ?> • <?= date('d.m | H:i', strtotime($p['created_at'])) ?></small>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" name="approve_post" class="btn btn-success">одобрить</button>
                    <button type="submit" name="reject_post" class="btn btn-danger">запретить</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Пользователи -->
    <div class="section">
        <h2>👥 Пользователи</h2>
        <div style="overflow-x:auto">
        <table>
            <thead><tr><th>ID</th><th>Пользователь</th><th>Email</th><th>Роль</th><th>Действия</th></tr></thead>
            <tbody>
            <?php foreach($users as $u):                 $is_main_target = ($u['email'] === $main_admin);
                $role_badge = $u['role']==-1?'<span class="badge badge-ban">BAN</span>':($u['role']==1?'<span class="badge badge-admin">ADMIN</span>':($u['role']==2?'<span class="badge badge-mod">MODER</span>':''));
            ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td>@<?= htmlspecialchars($u['username']) ?> <?= $role_badge ?></td>
                <td style="font-size:0.9em;color:#888"><?= htmlspecialchars($u['email']) ?></td>
                <td><small style="color:#666"><?= date('d.m', strtotime($u['created_at'])) ?></small></td>
                <td>
                <?php if(!$is_main_target): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="target_id" value="<?= (int)$u['id'] ?>">
                        <?php if($u['role']==-1): ?>
                            <button type="submit" name="user_action" value="unban" class="btn btn-outline">Разбан</button>
                        <?php else: ?>
                            <button type="submit" name="user_action" value="ban" class="btn btn-danger">Бан</button>
                            <?php if($is_main): ?>
                                <?php if($u['role']==0): ?>
                                    <button type="submit" name="user_action" value="make_moder" class="btn btn-mod">Moder</button>
                                    <button type="submit" name="user_action" value="make_admin" class="btn btn-warning">Admin</button>
                                <?php elseif($u['role']==2): ?>
                                    <button type="submit" name="user_action" value="remove_moder" class="btn btn-outline">Снять Mod</button>
                                <?php elseif($u['role']==1): ?>
                                    <button type="submit" name="user_action" value="remove_admin" class="btn btn-outline">Снять Admin</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                <?php else: ?><span style="color:#666;font-size:0.85em">не сможешь</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Теги -->
    <div class="section">
        <h2>Теги</h2>
        <form method="POST" class="tag-form">
            <input type="text" name="tag_name" placeholder="Название тега" required>
            <button type="submit" name="add_tag" class="btn btn-success"><span>&#43;</span>
</button>
        </form>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach($tags as $t): ?><span class="badge" style="background:#333"><?= htmlspecialchars($t['name']) ?></span><?php endforeach; ?>
        </div>
    </div>
</div>
<footer>&copy; <?= date('Y') ?> AniHou | Все права защищены.</footer></body></html>