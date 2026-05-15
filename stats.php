<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$uid = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(sender_id=?) as sent, SUM(receiver_id=?) as recv FROM messages WHERE sender_id=? OR receiver_id=?");
$stmt->execute([$uid, $uid, $uid, $uid]);
$s = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) as friends FROM friends WHERE (user_id=? OR friend_id=?) AND status='accepted'");
$stmt->execute([$uid, $uid]);
$f = $stmt->fetch();

$stmt = $pdo->prepare("SELECT DATE(created_at) as d, COUNT(*) as c FROM messages WHERE (sender_id=? OR receiver_id=?) AND created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY d ORDER BY d");
$stmt->execute([$uid, $uid]);
$act = $stmt->fetchAll();
$max = max(array_column($act, 'c')?:[1]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>.grid{display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px;} .stat-card{background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:24px; text-align:center;} .stat-val{font-size:36px; font-weight:700; color:var(--accent-color); margin:10px 0;} .chart{display:flex; align-items:flex-end; gap:10px; height:150px; padding:20px 0;} .bar{flex:1; background:var(--accent-gradient); border-radius:6px 6px 0 0; transition:0.3s; position:relative;} .bar:hover{opacity:0.8;} .bar span{position:absolute; bottom:-25px; left:50%; transform:translateX(-50%); font-size:11px; color:var(--text-secondary);}</style>
</head>
<body>
    <div class="header-nav">
        <h1><i class="fa-brands fa-rocketchat"></i> Мессенджер</h1>
        <div>
            <a href="messenger.php"><i class="fa-solid fa-comments"></i> Чаты</a>
            <a href="profile.php"><i class="fa-solid fa-user"></i> Профиль</a>
            <a href="stats.php" style="color:var(--accent-color);"><i class="fa-solid fa-chart-bar"></i> Статистика</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Выйти</a>
        </div>
    </div>

    <div style="max-width:900px; margin:40px auto; padding:0 20px;">
        <h2 style="text-align:left; margin-bottom:20px;"><i class="fa-solid fa-chart-pie"></i> Ваша активность</h2>
        <div class="grid">
            <div class="stat-card"><i class="fa-solid fa-paper-plane" style="font-size:24px; color:var(--accent-color);"></i><div class="stat-val"><?=$s['sent']?></div><div style="color:var(--text-secondary);">Отправлено</div></div>
            <div class="stat-card"><i class="fa-solid fa-inbox" style="font-size:24px; color:var(--success);"></i><div class="stat-val"><?=$s['recv']?></div><div style="color:var(--text-secondary);">Получено</div></div>
            <div class="stat-card"><i class="fa-solid fa-comments" style="font-size:24px; color:#f59e0b;"></i><div class="stat-val"><?=$s['total']?></div><div style="color:var(--text-secondary);">Всего</div></div>
            <div class="stat-card"><i class="fa-solid fa-users" style="font-size:24px; color:#ec4899;"></i><div class="stat-val"><?=$f['friends']?></div><div style="color:var(--text-secondary);">Друзей</div></div>
        </div>

        <div class="card" style="max-width:100%; margin-bottom:30px;">
            <h3 style="margin-bottom:15px;"><i class="fa-solid fa-chart-line"></i> Активность за 7 дней</h3>
            <div class="chart">
                <?php foreach($act as $d): $h=($d['c']/$max)*100; ?>
                    <div class="bar" style="height:<?=$h?>%;"><span><?=date('D',strtotime($d['d']))?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>