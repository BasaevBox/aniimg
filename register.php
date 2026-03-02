<?php
require_once 'functions.php';
if (checkAuth()) redirect('index.php');

$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if (strlen($username) < 3) $msg = "Имя слишком короткое";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $msg = "Некорректный email";
    elseif ($password !== $confirm) $msg = "Пароли не совпадают";
    elseif (strlen($password) < 6) $msg = "Пароль должен быть не менее 6 символов";
    else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $check->execute([$username, $email]);
        if ($check->rowCount() > 0) {
            $msg = "Пользователь с таким именем или email уже существует";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, avatar) VALUES (?, ?, ?, 'default.png')");
            if ($stmt->execute([$username, $email, $hash])) {
                redirect('login.php');
            } else {
                $msg = "Ошибка регистрации";
            }
        }
    }
}
include 'header.php';
?>			
<div class="form-box">
	<div style="display:flex; align-items:center; gap:10px;">
   <h2>Регистрация</h2>
   <img src="hatsune-miku-dance.gif" width="80" height="80" alt="Описание GIF">
</div>
    <?php if($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Имя пользователя" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <input type="password" name="password" placeholder="Пароль" required>
		<input type="password" name="confirm" placeholder="Подтвердите пароль" required>
		<section>
		<div class="social-links">
		        <a href="info.php" style="color:var(--accent)">✅ Я принимаю политику конфиденциальности и cookie</a>
            </div>
		</section>
    
        
        <button type="submit">Зарегистрироваться</button>
    </form>
    <p style="text-align:center; margin-top:15px;">
        <a href="login.php" style="color:var(--accent)">Уже есть аккаунт? Войти</a>
    </p>
</div>
<?php include 'footer.php'; ?>