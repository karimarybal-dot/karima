<?php
session_start();
require 'db.php';
require 'send_email.php';

$message = '';
$error = '';
$step = $_SESSION['reset_step'] ?? 'request';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_code'])) {
    $login = trim($_POST['login']);
    
    if (empty($login)) {
        $error = "Введите email";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        
        if ($user) {
            $code = rand(100000, 999999);
            $_SESSION['reset_data'] = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'code' => $code,
                'expires' => time() + 600
            ];
            
            sendVerificationCode($user['email'], $code);
            $_SESSION['reset_step'] = 'verify';
            $step = 'verify';
            $message = "Код подтверждения: <strong>$code</strong>";
        } else {
            $error = "Пользователь не найден";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $input_code = trim($_POST['verify_code']);
    
    if (!isset($_SESSION['reset_data'])) {
        header("Location: forgot_password.php");
        exit;
    }
    
    $data = $_SESSION['reset_data'];
    
    if (time() > $data['expires']) {
        $error = "Код истек";
        unset($_SESSION['reset_data']);
        unset($_SESSION['reset_step']);
        $step = 'request';
    } elseif ($input_code == $data['code']) {
        $_SESSION['reset_step'] = 'new_password';
        $step = 'new_password';
        $message = "Код подтвержден! Введите новый пароль";
    } else {
        $error = "Неверный код";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    if (!isset($_SESSION['reset_data'])) {
        header("Location: forgot_password.php");
        exit;
    }
    
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if (strlen($new_pass) < 6) {
        $error = "Пароль минимум 6 символов";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Пароли не совпадают";
    } else {
        $data = $_SESSION['reset_data'];
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        
        if ($stmt->execute([$hash, $data['user_id']])) {
            unset($_SESSION['reset_data']);
            unset($_SESSION['reset_step']);
            header("Location: index.php?reset=success");
            exit;
        } else {
            $error = "Ошибка";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="card">
            <div style="text-align: center; margin-bottom: 10px;">
                <i class="fa-solid fa-key" style="font-size: 48px; color: var(--accent-color);"></i>
            </div>
            <h2><?= $step === 'request' ? 'Забыли пароль?' : ($step === 'verify' ? 'Код подтверждения' : 'Новый пароль') ?></h2>
            <p class="subtitle"><?= $step === 'request' ? 'Введите email' : ($step === 'verify' ? 'Введите код из письма' : 'Придумайте новый пароль') ?></p>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($step === 'request'): ?>
                <form method="POST">
                    <div class="input-group">
                        <label>Email или логин</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="text" name="login" placeholder="example@mail.com" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Отправить код
                    </button>
                </form>
            <?php elseif ($step === 'verify'): ?>
                <form method="POST">
                    <div class="input-group">
                        <label>Код</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key"></i>
                            <input type="text" name="verify_code" placeholder="123456" maxlength="6" style="text-align: center; font-size: 24px; letter-spacing: 8px;" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> Подтвердить
                    </button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <div class="input-group">
                        <label>Новый пароль</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="new_password" placeholder="Минимум 6 символов" required>
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
                        <i class="fa-solid fa-check"></i> Сменить пароль
                    </button>
                </form>
            <?php endif; ?>

            <a href="index.php" class="link-center"><i class="fa-solid fa-arrow-left"></i> Назад ко входу</a>
        </div>
    </div>
</body>
</html>