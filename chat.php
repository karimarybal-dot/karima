<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$friend_id = intval($_GET['user_id'] ?? 0);

// Проверяем, являются ли они друзьями
$stmt = $pdo->prepare("SELECT id FROM friends WHERE ((user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)) AND status = 'accepted'");
$stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
if (!$stmt->fetch()) {
    die("❌ Вы не можете писать этому пользователю. Сначала добавьте его в друзья.");
}

// Получаем данные друга
$stmt = $pdo->prepare("SELECT username, full_name, avatar FROM users WHERE id = ?");
$stmt->execute([$friend_id]);
$friend = $stmt->fetch();

// Отправка сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_text = trim($_POST['message']);
    if ($message_text) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $friend_id, $message_text]);
        header("Location: chat.php?user_id=$friend_id");
        exit;
    }
}

// Получаем историю сообщений
$stmt = $pdo->prepare("
    SELECT m.*, u.username as sender_username 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.created_at ASC
");
$stmt->execute([$user_id, $friend_id, $friend_id, $user_id]);
$messages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Чат с <?= htmlspecialchars($friend['full_name'] ?: $friend['username']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background: #f4f6f8; }
        .sidebar { width: 300px; background: white; border-right: 1px solid #e0e0e0; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .back-btn { color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 10px; }
        .chat-header-info { display: flex; align-items: center; gap: 10px; }
        .chat-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .main-chat { flex: 1; display: flex; flex-direction: column; }
        .chat-header { padding: 15px 25px; border-bottom: 1px solid #e0e0e0; background: white; font-weight: 600; }
        .messages-area { flex: 1; padding: 20px; overflow-y: auto; background: #f4f6f8; }
        .message { max-width: 70%; padding: 12px 15px; border-radius: 15px; margin-bottom: 10px; font-size: 14px; line-height: 1.5; word-wrap: break-word; }
        .message.sent { background: #667eea; color: white; margin-left: auto; border-bottom-right-radius: 5px; }
        .message.received { background: white; color: #333; border-bottom-left-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .message-meta { font-size: 11px; opacity: 0.7; margin-top: 5px; }
        .input-area { padding: 20px; background: white; border-top: 1px solid #e0e0e0; display: flex; gap: 10px; }
        .input-area input { flex: 1; padding: 12px 20px; border: 2px solid #eee; border-radius: 25px; outline: none; }
        .input-area input:focus { border-color: #667eea; }
        .send-btn { background: #667eea; color: white; border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 18px; }
        .send-btn:hover { background: #764ba2; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="search_users.php" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Назад к поиску
            </a>
            <div class="chat-header-info">
                <img src="uploads/avatars/<?= htmlspecialchars($friend['avatar']) ?>" alt="Avatar" class="chat-avatar" onerror="this.src='https://via.placeholder.com/40'">
                <div>
                    <div><?= htmlspecialchars($friend['full_name'] ?: $friend['username']) ?></div>
                    <small>@<?= htmlspecialchars($friend['username']) ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="main-chat">
        <div class="chat-header">
            💬 Переписка с <?= htmlspecialchars($friend['full_name'] ?: $friend['username']) ?>
        </div>
        
        <div class="messages-area">
            <?php if (empty($messages)): ?>
                <p style="text-align:center; color:#888; margin-top: 50px;">Начните общение первым! 👋</p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?= $msg['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                        <div class="message-meta">
                            <?= date('H:i', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form method="POST" class="input-area">
            <input type="text" name="message" placeholder="Напишите сообщение..." required autofocus>
            <button type="submit" name="send_message" class="send-btn">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </form>
    </div>
</body>
</html>