<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Переходим на установку, если отсутствует файл настроек
if (!file_exists('config.php')) { header("Location: install.php"); exit; }
require_once 'config.php';

// Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die("Ошибка подключения к базе данных: " . $e->getMessage()); }

// Начинаем сессию, если она еще не начата
if (session_status() === PHP_SESSION_NONE) session_start();

// Загружаем вспомогательные функции
if (file_exists('functions.php')) {
    require_once 'functions.php';
}

// Функции проверки авторизованности и прав доступа
if (!function_exists('checkAuth')) {
    function checkAuth() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdminOrMod')) {
    function isAdminOrMod() {
        return isset($_SESSION['role']) && ($_SESSION['role'] == 1 || $_SESSION['role'] == 2);
    }
}

// Файлы для хранения загрузок
if (!defined('UPLOAD_DIR')) { define('UPLOAD_DIR', 'uploads/'); }

// Получение списка постов
try {
    // Топовые посты
    $top_posts = $pdo->query("
        SELECT p.*, u.username 
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status='approved' 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ")->fetchAll();

    // Пагинация всех одобренных постов
    $page = max(1, (int)($_GET['page'] ?? 1)); // Текущая страница
    $limit = 12;                               // Количество постов на странице
    $offset = ($page - 1) * $limit;           // Смещение относительно начала выборки

    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.avatar 
        FROM posts p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.status='approved' 
        ORDER BY p.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $all_posts = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Ошибка выполнения запроса: " . $e->getMessage());
}

// Теперь у вас доступна вся информация для отображения на странице
?>

<!DOCTYPE html>
<html lang="ru"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AniHou - public</title>
<style>
:root{--bg:#0f0f13;--card:rgba(30,30,40,0.7);--text:#e0e0e0;--accent:#bb86fc;--danger:#cf6679;--border:1px solid rgba(255,255,255,0.1)}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;line-height:1.6}
a{color:inherit;text-decoration:none}
.container{max-width:1200px;margin:0 auto;padding:20px}
nav{display:flex;justify-content:space-between;align-items:center;padding:15px 20px;background:var(--card);backdrop-filter:blur(10px);position:sticky;top:0;z-index:100;border-bottom:var(--border)}
.nav-links{display:flex;gap:20px;align-items:center}
.nav-links a{font-weight:500;transition:color.2s}
.nav-links a:hover{color:var(--accent)}
.dropdown{position:relative}
.dropbtn{background:none;border:none;color:white;cursor:pointer;font-size:16px;display:flex;align-items:center;gap:8px}
.dropdown-content{display:none;position:absolute;right:0;background:#1e1e28;min-width:160px;border-radius:8px;overflow:hidden;box-shadow:0 8px 16px rgba(0,0,0,0.5);z-index:1}
.dropdown-content a{padding:12px 16px;display:block}
.dropdown-content a:hover{background:var(--accent)}
.dropdown:hover .dropdown-content{display:block}
.search-form{display:flex;gap:10px}
.search-form input{padding:8px 12px;background:rgba(0,0,0,0.3);border:1px solid #333;border-radius:5px;color:white;width:200px}
.search-form button{padding:8px 15px;background:var(--accent);border:none;border-radius:5px;color:white;cursor:pointer}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;margin:20px 0}
.card{background:var(--card);border:var(--border);border-radius:15px;overflow:hidden;transition:transform.2s;backdrop-filter:blur(5px)}
.card:hover{transform:translateY(-5px)}
.card img{width:100%;height:250px;object-fit:cover;display:block}
.card-body{padding:15px}
.card-body h4{margin:0 0 10px;font-size:1.1em}
.card-body small{color:#aaa}
.tags span{background:rgba(187,134,252,0.2);color:var(--accent);padding:2px 8px;border-radius:10px;font-size:0.8em;margin-right:5px}
.btn{display:inline-block;padding:8px 16px;background:var(--accent);color:white;border-radius:5px;font-weight:500;cursor:pointer;border:none}
.btn:hover{background:#9955e8}
.btn-sm{padding:5px 10px;font-size:0.9em}
.btn-danger{background:var(--danger)}
.btn-danger:hover{background:#b05065}
.avatar{width:30px;height:30px;border-radius:50%;object-fit:cover;vertical-align:middle}
section{margin:40px 0}
h2{color:var(--accent);margin-bottom:20px;border-bottom:var(--border);padding-bottom:10px}
footer{text-align:center;padding:20px;color:#666;font-size:0.9em;margin-top:40px;border-top:var(--border)}
.empty{text-align:center;padding:40px;color:#888}
.pagination{text-align:center;margin-top:20px;display:flex;gap:10px;justify-content:center}
@media(max-width:768px){
    nav{flex-direction:column;gap:15px}
    .nav-links{flex-wrap:wrap;justify-content:center}
    .search-form{display:none}
    .grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
.fixed-gif {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1000;
}

.center-gif {
    width: 50px;
    height: 50px;
}
</style>
</head><body>


<nav>
    <a href="index.php" style="font-size:1.4em;font-weight:bold;color:var(--accent)">AniHou</a>
    
    <form action="search.php" method="GET" class="search-form">
        <input type="text" name="q" placeholder="Поиск...">
        <button type="submit">поиск</button>
    </form>
    
    <div class="nav-links">
        <a href="index.php">Главная</a>
        <?php if(checkAuth()): ?>
            <a href="post.php">Загрузить</a>
            <a href="favorites.php">Избранное</a>
            <div class="dropdown">
                <button class="dropbtn">
                    <img class="avatar" src="<?= file_exists(UPLOAD_DIR.($_SESSION['avatar']??'default.png')) ? UPLOAD_DIR.($_SESSION['avatar']??'default.png') : 'https://via.placeholder.com/30' ?>" alt="">
                    <?= htmlspecialchars($_SESSION['username'] ?? 'User')  ?>
                </button>
                <div class="dropdown-content">
    <a href="profile.php">Профиль</a>
    <a href="settings.php">Настройки</a>
    <!-- Изменили условие -->
    <?php if(isAdminOrMod()): ?> 
        <a href="admin.php">Админка</a>
    <?php endif; ?>
    <a href="logout.php" style="color:var(--danger)">Выход</a>
</div>

            </div>
        <?php else: ?>
            <a href="login.php">Вход</a>
            <a href="register.php" class="btn btn-sm">Регистрация</a>
        <?php endif; ?>
    </div>
</nav>
<div class="container">


    <section>
        <h2> Свежие работы</h2>
        <div class="grid">
            <?php if(empty($top_posts)): ?>
                <div class="empty">Работ пока нет. Будьте первыми!</div>
            <?php else: ?>
                <?php foreach($top_posts as $p): ?>
                <div class="card">
                    <a href="post.php?id=<?= (int)$p['id'] ?>">
                        <img src="<?= UPLOAD_DIR.htmlspecialchars($p['image']) ?>" onerror="this.src='https://via.placeholder.com/300?text=No+Image'" alt="<?= htmlspecialchars($p['title']) ?>">
                    </a>                    <div class="card-body">
                        <small>@<?= htmlspecialchars($p['username']) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>


    <section>
        <h2> Все работы</h2>
        <div class="grid">
            <?php if(empty($all_posts)): ?>
                <div class="empty">Ничего не найдено</div>
            <?php else: ?>
                <?php foreach($all_posts as $p): 
                    $likes = $pdo->query("SELECT COUNT(*) FROM likes WHERE post_id=".(int)$p['id'])->fetchColumn();
                ?>
                <div class="card">
                    <a href="post.php?id=<?= (int)$p['id'] ?>">
                        <img src="<?= UPLOAD_DIR.htmlspecialchars($p['image']) ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/300?text=No+Image'" alt="<?= htmlspecialchars($p['title']) ?>">
                    </a>
                    <div class="card-body">
                        <h4><?= htmlspecialchars($p['title']) ?></h4>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                            <span>⭐ <?= (int)$likes ?></span>
                            <a href="profile.php?id=<?= (int)$p['user_id'] ?>" style="font-size:0.85em;color:#aaa">@<?= htmlspecialchars($p['username']) ?></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        

        <?php if(count($all_posts) >= 12): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?= $page-1 ?>" class="btn btn-sm">← Назад</a>
            <?php endif; ?>
            <span style="padding:8px;color:#888">Страница <?= $page ?></span>
            <a href="?page=<?= $page+1 ?>" class="btn btn-sm">Вперёд →</a>
        </div>
        <?php endif; ?>
    </section>


</div>

<footer>
    &copy; <?= date('Y') ?> AniHou | Все права защищены.
</footer>
</body>
</html>