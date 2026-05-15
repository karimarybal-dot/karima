<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Мессенджер</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="card">
            <div style="text-align: center; margin-bottom: 10px;">
                <i class="fa-brands fa-rocketchat" style="font-size: 48px; color: var(--accent-color);"></i>
            </div>
            <h2>С возвращением!</h2>
            <p class="subtitle">Войдите, чтобы продолжить общение</p>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Неверный логин или пароль</div>
            <?php endif; ?>
            <?php if (isset($_GET['reg'])): ?>
                <div class="alert alert-success"><i class="fa-solid fa-check"></i> Регистрация успешна! Теперь войдите.</div>
            <?php endif; ?>
            <?php if (isset($_GET['reset'])): ?>
                <div class="alert alert-success"><i class="fa-solid fa-check"></i> Пароль изменен! Войдите с новым паролем.</div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">
                <div class="input-group">
                    <label>Логин или Email</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="login" placeholder="Введите логин" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Пароль</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-size: 13px;">
                    <label style="display: flex; align-items: center; gap: 6px; color: var(--text-secondary); cursor: pointer;">
                        <input type="checkbox" style="width: auto; margin: 0;"> Запомнить меня
                    </label>
                    <a href="forgot_password.php" style="font-size: 13px;">Забыли пароль?</a>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-right-to-bracket"></i> Войти
                </button>
            </form>

            <a href="register.php" class="link-center">Нет аккаунта? <strong>Зарегистрироваться</strong></a>
        </div>
    </div>
</body>
</html>