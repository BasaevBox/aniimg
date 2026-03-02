<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniHou </title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Дополнительные стили для поиска в хедере */
        .search-nav { display: flex; gap: 10px; }
        .search-nav input { padding: 5px 10px; margin: 0; width: 200px; }
        .search-nav button { width: auto; padding: 5px 15px; margin: 0; }
        @media (max-width: 768px) { .search-nav { display: none; } }
    </style>
</head>
<body>
<nav>
    <div style="display:flex; align-items:center; gap:20px;">
        <a href="index.php" style="font-size:1.5em; font-weight:bold; color:var(--accent)">AniHou </a>
        <form action="search.php" method="GET" class="search-nav">
            <input type="text" name="q" placeholder="Поиск...">
            <button type="submit">🔍</button>
        </form>
    </div>
    <div class="nav-links">
        <a href="index.php">Главная</a>
        <?php if(checkAuth()): ?>
            <a href="post.php">Загрузить</a>
            <a href="favorites.php">Избранное</a>
            <div class="dropdown">
                <button class="dropbtn">
                    <img src="<?= UPLOAD_DIR . ($_SESSION['avatar'] ?? 'default.png') ?>" 
                         style="width:30px; height:30px; border-radius:50%; vertical-align:middle">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                </button>
                <div class="dropdown-content">
                    <a href="profile.php">Профиль</a>
                    <a href="settings.php">Настройки</a>
                    <?php if(isAdmin()): ?><a href="admin.php">Админка</a><?php endif; ?>
                    <a href="logout.php" style="color:var(--danger)">Выход</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php">Вход</a>
            <a href="register.php" style="background:var(--accent); padding:5px 15px; border-radius:5px;">Регистрация</a>
        <?php endif; ?>
    </div>
</nav>