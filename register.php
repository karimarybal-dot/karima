<?php
session_start();
require 'db.php';
require 'send_email.php';

$message = '';
$error = '';
$step = $_SESSION['reg_step'] ?? 'register';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_code'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $passConf = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($pass)) {
        $error = "Заполните все поля";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Некорректный Email";
    } elseif (strlen($pass) < 6) {
        $error = "Пароль минимум 6 символов";
    } elseif ($pass !== $passConf) {
        $error = "Пароли не совпадают";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->fetch()) {
            $error = "Пользователь уже существует";
        } else {
            $code = rand(100000, 999999);
            $_SESSION['temp_user'] = [
                'username' => $username,
                'email' => $email,
                'password' => password_hash($pass, PASSWORD_DEFAULT),
                'code' => $code,
                'expires' => time() + 600
            ];

            sendVerificationCode($email, $code);
            $_SESSION['reg_step'] = 'verify';
            $step = 'verify';
            $message = "Код подтверждения: <strong>$code</strong>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $input_code = trim($_POST['verify_code']);
    
    if (!isset($_SESSION['temp_user'])) {
        header("Location: register.php");
        exit;
    }

    $temp_data = $_SESSION['temp_user'];

    if (time() > $temp_data['expires']) {
        $error = "Код истек";
        unset($_SESSION['temp_user']);
        unset($_SESSION['reg_step']);
        $step = 'register';
    } elseif ($input_code == $temp_data['code']) {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        
        if ($stmt->execute([$temp_data['username'], $temp_data['email'], $temp_data['password']])) {
            unset($_SESSION['temp_user']);
            unset($_SESSION['reg_step']);
            header("Location: index.php?reg=success");
            exit;
        } else {
            $error = "Ошибка БД";
        }
    } else {
        $error = "Неверный код";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | Мессенджер</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="card">
            <div style="text-align: center; margin-bottom: 10px;">
                <i class="fa-solid fa-user-plus" style="font-size: 48px; color: var(--accent-color);"></i>
            </div>
            <h2><?= $step === 'verify' ? 'Подтверждение' : 'Создать аккаунт' ?></h2>
            <p class="subtitle"><?= $step === 'verify' ? 'Введите код из письма' : 'Присоединяйтесь к нам' ?></p>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($step === 'register'): ?>
                <form method="POST">
                    <div class="input-group">
                        <label>Имя пользователя</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="username" placeholder="Придумайте логин" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Email</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="email" placeholder="example@mail.com" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Пароль</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" placeholder="Минимум 6 символов" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Подтвердите пароль</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-check-double"></i>
                            <input type="password" name="confirm_password" placeholder="Повторите пароль" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus"></i> Зарегистрироваться
                    </button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <div class="input-group">
                        <label>Код подтверждения</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key"></i>
                            <input type="text" name="verify_code" placeholder="123456" maxlength="6" style="text-align: center; font-size: 24px; letter-spacing: 8px;" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> Подтвердить
                    </button>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="register.php" style="font-size: 13px;">Изменить email?</a>
                    </div>
                </form>
            <?php endif; ?>

            <a href="index.php" class="link-center">Уже есть аккаунт? <strong>Войти</strong></a>
        </div>
    </div>
</body>
</html>