<?php
require_once 'functions.php';
if (checkAuth()) redirect('index.php');

$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login']); // Можно вводить email или username
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'];
        redirect('index.php');
    } else {
        $msg = "Неверный логин или пароль";
    }
}
include 'header.php';
?>
<div class="form-box">
    <h2>Вход</h2>
    <?php if($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="login" placeholder="Email или Имя" required>
        <input type="password" name="password" placeholder="Пароль" required>
        <button type="submit">Войти</button>
    </form>
    <p style="text-align:center; margin-top:15px;">
        <a href="register.php" style="color:var(--accent)">Нет аккаунта? Регистрация</a>
    </p>
</div>
<?php include 'footer.php'; ?>