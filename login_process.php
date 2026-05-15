<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $pass = $_POST['password'];

    // Админ
    if ($login === 'adm' && $pass === 'adm') {
        $_SESSION['user_id'] = 0;
        $_SESSION['username'] = 'Администратор';
        header("Location: messenger.php");
        exit;
    }

    // Пользователь из БД
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE email = ? OR username = ?");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: messenger.php");
        exit;
    } else {
        header("Location: index.php?error=1");
        exit;
    }
}

header("Location: index.php");
exit;
?>