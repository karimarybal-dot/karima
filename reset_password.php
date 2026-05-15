<?php
session_start();
require 'db.php';

// Проверяем токен
$token = $_GET['token'] ?? '';
$message = '';
$error = '';

if (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $token) {
    die("❌ Неверная или устаревшая ссылка. <a href='forgot_password.php'>Попробовать снова</a>");
}

if (strtotime($_SESSION['reset_expires']) < time()) {
    session_unset();
    die("❌ Ссылка истекла. <a href='forgot_password.php'>Запросить новую</a>");
}

$user_id = $_SESSION['reset_user_id'];

// Обработка сброса пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    if (strlen($new_pass) < 6) {
        $error = "Пароль должен быть минимум 6 символов";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Пароли не совпадают";
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        
        if ($stmt->execute([$hash, $user_id])) {
            session_unset();
            header("Location: index.php?reset=success");
            exit;
        } else {
            $error = "Ошибка при обновлении пароля";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сброс пароля</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; display: flex; justify-content: center; align-items: center; }
        .reset-container { background-color: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 100%; max-width: 400px; animation: fadeIn 0.8s ease; }
        .header { text-align: center; margin-bottom: 30px; }
        .header i { font-size: 50px; color: #764ba2; margin-bottom: 15px; }
        .header h2 { color: #333; font-weight: 600; margin-bottom: 10px; }
        .header p { color: #888; font-size: 14px; }
        .input-group { margin-bottom: 20px; position: relative; }
        .input-group label { display: block; margin-bottom: 8px; color: #555; font-size: 14px; font-weight: 500; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .input-wrapper input { width: 100%; padding: 12px 15px 12px 45px; border: 2px solid #eee; border-radius: 10px; outline: none; transition: all 0.3s ease; font-size: 15px; }
        .input-wrapper input:focus { border-color: #764ba2; }
        .btn-reset { width: 100%; padding: 14px; background: linear-gradient(to right, #667eea, #764ba2); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; margin-bottom: 20px; }
        .btn-reset:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; }
        .alert-error { background: #ffe6e6; color: #c00; }
    </style>
</head>
<link rel="stylesheet" href="loader.css">
<body>
    <script>
    // Скрываем loader после полной загрузки страницы
    window.addEventListener('load', function() {
        const loader = document.getElementById('pageLoader');
        
        // Минимальное время показа loader (для красоты)
        setTimeout(() => {
            if (loader) {
                loader.classList.add('hidden');
                
                // Удаляем из DOM после анимации
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 500);
            }
        }, 800); // 800ms минимальное время показа
    });

    // Показываем loader при клике на ссылки внутри сайта
    document.addEventListener('DOMContentLoaded', function() {
        const links = document.querySelectorAll('a[href^="http://localhost"], a[href^="/"], a[href^="./"], a[href^="../"]');
        
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                // Не показываем loader для внешних ссылок и якорей
                if (this.href.includes('#') || this.target === '_blank') {
                    return;
                }
                
                const loader = document.getElementById('pageLoader');
                if (loader) {
                    loader.classList.remove('hidden');
                    loader.style.display = 'flex';
                }
            });
        });
    });
</script>
    <!-- Экран загрузки -->
<div class="loader-overlay" id="pageLoader">
    <div class="loader-content">
        <div class="loader-logo">
            <i class="fa-brands fa-rocketchat"></i>
        </div>
        <div class="loader-spinner"></div>
        <div class="loader-text">Загрузка...</div>
        <div class="loader-progress">
            <div class="loader-progress-bar"></div>
        </div>
    </div>
</div>
    <div class="reset-container">
        <div class="header">
            <i class="fa-solid fa-lock-open"></i>
            <h2>Новый пароль</h2>
            <p>Придумайте новый пароль для вашего аккаунта</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

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

            <button type="submit" class="btn-reset">Сменить пароль</button>
        </form>
    </div>
</body>
</html>