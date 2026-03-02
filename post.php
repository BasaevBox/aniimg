<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
if (!file_exists('config.php')) { header("Location: install.php"); exit; }
require_once 'config.php';
try { $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } 
catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }
if (session_status() === PHP_SESSION_NONE) session_start();

// === ОБРАБОТКА ДЕЙСТВИЙ (LIKE, FAV, SUB) ===
if (isset($_GET['action']) && isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $pid = (int)$_GET['id']; $uid = $_SESSION['user_id'];
    if ($_GET['action'] == 'like') {
        $check = $pdo->prepare("SELECT * FROM likes WHERE user_id=? AND post_id=?");
        $check->execute([$uid, $pid]);
        if ($check->rowCount()) {
            $pdo->prepare("DELETE FROM likes WHERE user_id=? AND post_id=?")->execute([$uid, $pid]);
        } else {
            $pdo->prepare("INSERT IGNORE INTO likes (user_id, post_id) VALUES (?, ?)")->execute([$uid, $pid]);
        }
    }
    if ($_GET['action'] == 'fav') {
        $pdo->prepare("INSERT IGNORE INTO favorites (user_id, post_id) VALUES (?, ?)")->execute([$uid, $pid]);
    }
    if ($_GET['action'] == 'sub' && isset($_GET['target'])) {
        $pdo->prepare("INSERT IGNORE INTO subscriptions (follower_id, following_id) VALUES (?, ?)")->execute([$uid, (int)$_GET['target']]);
    }
    header("Location: post.php?id=$pid"); exit;
}

// === ПОКАЗ ПОСТА ===
if (!isset($_GET['id'])) {
    // Если нет ID и пользователь не авторизован - редирект
    if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
    
    // Форма загрузки (если нет ID)
    $msg = "";
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
        $title = trim($_POST['title']); $desc = trim($_POST['description']); $tags = $_POST['tags'] ?? [];
        if (!empty($_FILES['image']['name'])) {
            if (!is_dir('uploads')) mkdir('uploads', 0777, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'])) {
                $filename = uniqid().'.'.$ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/'.$filename)) {
                    $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, description, image, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$_SESSION['user_id'], $title, $desc, $filename]);
                    $last_id = $pdo->lastInsertId();
                    if (!empty($tags)) {
                        $st = $pdo->prepare("INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)");
                        foreach($tags as $t) $st->execute([$last_id, (int)$t]);                    }
                    header("Location: profile.php?success=1"); exit;
                }
            }
            $msg = "Ошибка формата файла";
        }
    }
    $all_tags = $pdo->query("SELECT * FROM tags ORDER BY name ASC")->fetchAll();
    include_upload_form($all_tags, $msg);
    exit;
}

// Просмотр поста
$post_id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT p.*, u.username, u.avatar, u.email as author_email FROM posts p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
$stmt->execute([$post_id]); $post = $stmt->fetch();
if (!$post) { die("Пост не найден"); }

// Проверка доступа
$can_view = ($post['status'] == 'approved') || (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $post['user_id'] || $_SESSION['role'] == 1));
if (!$can_view) { die("Пост на модерации или удален"); }

// Данные для отображения
$tags = $pdo->prepare("SELECT t.name FROM tags t JOIN post_tags pt ON t.id=pt.tag_id WHERE pt.post_id=?");
$tags->execute([$post_id]); $tag_list = $tags->fetchAll(PDO::FETCH_COLUMN);
$likes_count = $pdo->query("SELECT COUNT(*) FROM likes WHERE post_id=$post_id")->fetchColumn();
$is_liked = (isset($_SESSION['user_id']) && $pdo->query("SELECT 1 FROM likes WHERE user_id=".$_SESSION['user_id']." AND post_id=$post_id")->fetch());
$is_fav = (isset($_SESSION['user_id']) && $pdo->query("SELECT 1 FROM favorites WHERE user_id=".$_SESSION['user_id']." AND post_id=$post_id")->fetch());
$is_subscribed = (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $post['user_id'] && $pdo->query("SELECT 1 FROM subscriptions WHERE follower_id=".$_SESSION['user_id']." AND following_id=".$post['user_id'])->fetch());

include_post_view($post, $tag_list, $likes_count, $is_liked, $is_fav, $is_subscribed);

// === ШАБЛОНЫ ===

function include_upload_form($tags, $msg) { ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Загрузить | AniHou</title><style>
:root{--bg:#0f0f13;--card:rgba(30,30,40,0.7);--text:#e0e0e0;--accent:#bb86fc;--danger:#cf6679;--border:1px solid rgba(255,255,255,0.1)}
*{box-sizing:border-box;margin:0;padding:0}body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif}
a{color:inherit;text-decoration:none}.container{max-width:700px;margin:0 auto;padding:20px}
nav{display:flex;justify-content:space-between;padding:15px 20px;background:var(--card);border-bottom:var(--border)}
.nav-links a{margin-left:20px}.nav-links a:hover{color:var(--accent)}
.form-box{background:var(--card);padding:30px;border-radius:15px;border:var(--border);margin:30px 0}
.form-box h2{color:var(--accent);margin-bottom:20px}
input,textarea{width:100%;padding:12px;margin:10px 0;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:8px;color:white}
textarea{min-height:120px;resize:vertical}
button{padding:12px 30px;background:var(--accent);border:none;border-radius:8px;color:white;font-weight:bold;cursor:pointer}
button:hover{background:#9955e8}
.alert{padding:12px;background:rgba(207,102,121,0.2);color:var(--danger);border-radius:8px;margin-bottom:20px}
.tag-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;margin:10px 0}.tag-opt{display:flex;align-items:center;gap:8px;padding:8px;background:rgba(0,0,0,0.2);border-radius:6px;font-size:0.9em}
.tag-opt input{width:auto;margin:0}
footer{text-align:center;padding:20px;color:#666;margin-top:40px;border-top:var(--border)}
</style></head><body>
<nav><a href="index.php" style="font-weight:bold;color:var(--accent)">AniHou</a><div class="nav-links"><a href="index.php">Главная</a><a href="profile.php">Профиль</a><a href="logout.php">Выход</a></div></nav>
<div class="container"><div class="form-box"><h2>Загрузить работу</h2>
<?php if($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data">
<input type="text" name="title" placeholder="Название" required>
<textarea name="description" placeholder="Описание (необязательно)"></textarea>
<label style="font-weight:500;color:var(--accent)">Теги:</label>
<div class="tag-grid"><?php foreach($tags as $t): ?>
<div class="tag-opt"><input type="checkbox" name="tags[]" value="<?= (int)$t['id'] ?>" id="t<?= (int)$t['id'] ?>"><label for="t<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></label></div>
<?php endforeach; ?></div>
<label style="font-weight:500;color:var(--accent);display:block;margin:15px 0 5px">Файл:</label>
<input type="file" name="image" accept="image/*" required>
<button type="submit" style="margin-top:20px;width:100%">Отправить на модерацию</button>
</form></div></div>
<footer>&copy; <?= date('Y') ?> AniHou</footer></body></html><?php }

function include_post_view($p, $tags, $likes, $is_liked, $is_fav, $is_sub) { 
    $is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $p['user_id']);
    $is_main_admin = (isset($_SESSION['email']) && $_SESSION['email'] === 'anihou@vk.com');
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($p['title']) ?> | AniHou</title><style>
:root{--bg:#0f0f13;--card:rgba(30,30,40,0.7);--text:#e0e0e0;--accent:#bb86fc;--danger:#cf6679;--border:1px solid rgba(255,255,255,0.1)}
*{box-sizing:border-box;margin:0;padding:0}body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif}
a{color:inherit;text-decoration:none}.container{max-width:1100px;margin:0 auto;padding:20px}
nav{display:flex;justify-content:space-between;padding:15px 20px;background:var(--card);border-bottom:var(--border);position:sticky;top:0;z-index:100}
.nav-links a{margin-left:20px}.nav-links a:hover{color:var(--accent)}
.post-layout{display:grid;grid-template-columns:1.2fr 1fr;gap:30px;margin:30px 0}
.image-wrapper{background:var(--card);border-radius:15px;border:var(--border);overflow:hidden;text-align:center}
.image-wrapper img{max-width:100%;height:auto;display:block;max-height:80vh}
.info-card{background:var(--card);padding:25px;border-radius:15px;border:var(--border);height:fit-content;position:sticky;top:90px}
.info-card h1{color:var(--accent);margin-bottom:15px;font-size:1.5em;line-height:1.3}
.author-box{display:flex;align-items:center;gap:15px;padding:15px;background:rgba(0,0,0,0.2);border-radius:12px;margin-bottom:20px}
.author-box img{width:50px;height:50px;border-radius:50%;object-fit:cover}
.author-info strong{display:block;color:var(--text)}
.author-info a{font-size:0.9em;color:#888}
.author-info a:hover{color:var(--accent)}
.desc{line-height:1.7;color:#ccc;margin:20px 0;white-space:pre-wrap;word-break:break-word}
.tags{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0}
.tags span{background:rgba(187,134,252,0.15);color:var(--accent);padding:5px 12px;border-radius:20px;font-size:0.85em}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:25px;padding-top:20px;border-top:var(--border)}
.btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-weight:500;display:inline-flex;align-items:center;gap:6px}
.btn-primary{background:var(--accent);color:white}.btn-primary:hover{background:#9955e8}
.btn-outline{background:transparent;border:1px solid #555;color:var(--text)}.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.btn-danger{background:var(--danger);color:white}.btn-danger:hover{background:#b05065}
.meta{color:#666;font-size:0.9em;margin-top:20px}.download-link{display:block;text-align:center;padding:12px;background:rgba(0,0,0,0.3);border-radius:8px;margin-top:15px;color:var(--accent)}
.download-link:hover{text-decoration:underline}
@media(max-width:900px){.post-layout{grid-template-columns:1fr}.info-card{position:static}}
</style></head><body>
<nav><a href="index.php" style="font-weight:bold;color:var(--accent);font-size:1.3em">AniHou</a><div class="nav-links">
<a href="index.php">Главная</a><?php if(isset($_SESSION['user_id'])): ?><a href="profile.php">Профиль</a><a href="logout.php">Выход</a><?php else: ?><a href="login.php">Вход</a><?php endif; ?></div></nav>

<div class="container"><div class="post-layout">
    <div class="image-wrapper">
        <img src="<?= file_exists('uploads/'.$p['image']) ? 'uploads/'.$p['image'] : 'https://via.placeholder.com/800' ?>" alt="<?= htmlspecialchars($p['title']) ?>">
        <a href="<?= 'uploads/'.$p['image'] ?>" download class="download-link">⬇ Скачать оригинал</a>
    </div>
    <div class="info-card">
        <h1><?= htmlspecialchars($p['title']) ?></h1>
        <div class="author-box">
            <a href="profile.php?id=<?= (int)$p['user_id'] ?>"><img src="<?= file_exists('uploads/'.$p['avatar']) ? 'uploads/'.$p['avatar'] : 'https://via.placeholder.com/50' ?>" alt=""></a>
            <div class="author-info">
                <a href="profile.php?id=<?= (int)$p['user_id'] ?>"><strong>@<?= htmlspecialchars($p['username']) ?></strong>Авторство</a>
                <?php if(isset($_SESSION['user_id']) && !$is_owner): ?>
                    <?= $is_sub ? '<span style="color:var(--accent);font-size:0.85em">✓ Подписан</span>' : '<a href="?id='.$p['id'].'&action=sub&target='.$p['user_id'].'" style="font-size:0.85em;color:#888">+ Подписаться</a>' ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="desc"><?= htmlspecialchars($p['description']) ?></div>
		<h3>tags;</h3>
        <?php if(!empty($tags)): ?><div class="tags"><?php foreach($tags as $t): ?><span>#<?= htmlspecialchars($t) ?></span><?php endforeach; ?></div><?php endif; ?>
        <div class="meta">⭐ <?= (int)$likes ?> нравится • <?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></div>
        
        <div class="actions">
            <?php if(isset($_SESSION['user_id'])): ?>
            <a href="?id=<?= $p['id'] ?>&action=like" class="btn <?= $is_liked?'btn-primary':'btn-outline' ?>">⭐ <?= $is_liked?'Нравится':'Лайк' ?></a>
            <a href="?id=<?= $p['id'] ?>&action=fav" class="btn btn-outline"> В избранное</a>
            <?php if($is_owner || $_SESSION['role']==1): ?>
            <form method="POST" action="admin_action.php" style="display:inline" onsubmit="return confirm('Удалить пост?')">
                <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                <button type="submit" name="delete_post" class="btn btn-danger"> Удалить</button>
				<button type="button" onclick="copyFullPageLink()" class="btn btn-primary">Поделится</button>
<script>
    function copyFullPageLink() {
        let baseUrl = window.location.origin; // Получаем текущий домен
        let postId = '<?= (int)$p['id'] ?>';   // Берём ID поста
        let fullUrl = `${baseUrl}/post.php?id=${postId}`;
    
        navigator.clipboard.writeText(fullUrl)
            .then(() => alert('готово! а теперь отправь ссылку своему другу 😁'))
            .catch((err) => console.error('Ошибка'));
    }
</script>
				
            </form>
            <?php endif; ?>
            <?php else: ?>
            <a href="login.php" class="btn btn-outline">⭐ Войти для лайка</a>
            <?php endif; ?>
        </div>
    </div>
</div></div>
<footer style="text-align:center;padding:25px;color:#666;margin-top:40px;border-top:var(--border)">&copy; <?= date('Y') ?> AniHou | Все права защищены.</footer>
</body></html><?php } ?>