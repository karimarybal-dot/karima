<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$user_id = $_SESSION['user_id'];
$active_chat_id = intval($_GET['chat'] ?? 0);

// Данные текущего пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();

// Список друзей
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.username, u.full_name, u.avatar,
           (SELECT message FROM messages WHERE (sender_id=? AND receiver_id=u.id) OR (sender_id=u.id AND receiver_id=?) ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE (sender_id=? AND receiver_id=u.id) OR (sender_id=u.id AND receiver_id=?) ORDER BY created_at DESC LIMIT 1) as last_time,
           (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=? AND is_read=0) as unread_count
    FROM friends f JOIN users u ON (f.friend_id=u.id AND f.user_id=?) OR (f.user_id=u.id AND f.friend_id=?)
    WHERE f.status='accepted' ORDER BY last_time DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$friends = $stmt->fetchAll();

// Чат
$chat_messages = [];
$chat_user = null;
if ($active_chat_id > 0) {
    $stmt = $pdo->prepare("SELECT u.* FROM friends f JOIN users u ON u.id=f.friend_id WHERE f.user_id=? AND u.id=? AND f.status='accepted' UNION SELECT u.* FROM friends f JOIN users u ON u.id=f.user_id WHERE f.friend_id=? AND u.id=? AND f.status='accepted'");
    $stmt->execute([$user_id, $active_chat_id, $user_id, $active_chat_id]);
    $chat_user = $stmt->fetch();
    
    if ($chat_user) {
        $stmt = $pdo->prepare("SELECT m.*, u.username as sender_username FROM messages m JOIN users u ON m.sender_id=u.id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.created_at ASC");
        $stmt->execute([$user_id, $active_chat_id, $active_chat_id, $user_id]);
        $chat_messages = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0");
        $stmt->execute([$active_chat_id, $user_id]);
    }
}

// Отправка сообщения
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['send_message']) && $active_chat_id>0) {
    $msg = trim($_POST['message']);
    if ($msg && $chat_user) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $active_chat_id, $msg]);
        header("Location: messenger.php?chat=$active_chat_id");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM friends WHERE friend_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_count = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мессенджер</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="loader.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Сброс базовых стилей */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', sans-serif;
            background: var(--bg-body, #0d1117);
            color: var(--text-primary, #e6edf3);
        }
        
        .messenger-layout { 
            display: flex; 
            height: 100vh; 
            overflow: hidden; 
        }
        
        /* Sidebar */
        .sidebar { 
            width: 340px; 
            background: var(--bg-card, #161b22);
            border-right: 1px solid var(--border-color, #30363d);
            display: flex; 
            flex-direction: column; 
            flex-shrink: 0; 
        }
        
        /* ИСПРАВЛЕННАЯ ШАПКА */
        .sidebar-header { 
            padding: 16px 20px; 
            border-bottom: 1px solid var(--border-color, #30363d);
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            flex-wrap: nowrap;
            white-space: nowrap;
        }
        
        .user-profile { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            cursor: pointer; 
            padding: 6px 10px; 
            border-radius: var(--radius-md, 12px); 
            transition: 0.2s;
            flex-shrink: 0;
        }
        
        .user-profile:hover { 
            background: var(--bg-input, #0d1117); 
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        
        .user-name { 
            font-weight: 600; 
            font-size: 14px; 
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-status { 
            font-size: 11px; 
            color: var(--text-secondary, #8b949e); 
            white-space: nowrap;
        }
        
        /* ИСПРАВЛЕННЫЕ КНОПКИ НАВИГАЦИИ */
        .nav-buttons { 
            display: flex; 
            align-items: center; 
            gap: 4px;
            flex-shrink: 0;
        }
        
        .nav-buttons a, 
        .nav-buttons button { 
            color: var(--text-secondary, #8b949e); 
            font-size: 16px; 
            padding: 8px; 
            border-radius: var(--radius-sm, 8px); 
            background: none; 
            border: none; 
            cursor: pointer; 
            transition: 0.2s; 
            position: relative; 
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            text-decoration: none;
        }
        
        .nav-buttons a:hover, 
        .nav-buttons button:hover { 
            color: var(--accent-color, #58a6ff); 
            background: var(--bg-input, #0d1117); 
        }
        
        .search-box { 
            padding: 12px 20px; 
            border-bottom: 1px solid var(--border-color, #30363d); 
        }
        
        .search-box input { 
            width: 100%; 
            padding: 10px 14px; 
            background: var(--bg-input, #0d1117); 
            border: 1px solid var(--border-color, #30363d); 
            border-radius: var(--radius-md, 12px); 
            color: var(--text-primary, #e6edf3); 
            outline: none; 
        }
        
        .chat-list { 
            flex: 1; 
            overflow-y: auto; 
        }
        
        .chat-item { 
            padding: 12px 20px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            cursor: pointer; 
            transition: 0.2s; 
            border-bottom: 1px solid transparent; 
            text-decoration: none;
            color: inherit;
        }
        
        .chat-item:hover { 
            background: var(--bg-input, #0d1117); 
        }
        
        .chat-item.active { 
            background: var(--bg-input, #0d1117); 
            border-left: 3px solid var(--accent-color, #58a6ff); 
        }
        
        .chat-info { 
            flex: 1; 
            min-width: 0; 
        }
        
        .chat-header { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 4px; 
        }
        
        .chat-name { 
            font-weight: 600; 
            font-size: 14px; 
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-time { 
            font-size: 11px; 
            color: var(--text-secondary, #8b949e); 
            flex-shrink: 0;
            margin-left: 8px;
        }
        
        .chat-preview { 
            font-size: 13px; 
            color: var(--text-secondary, #8b949e); 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }
        
        .unread-badge { 
            background: var(--accent-color, #58a6ff); 
            color: white; 
            font-size: 11px; 
            min-width: 20px; 
            height: 20px; 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 0 6px;
            flex-shrink: 0;
        }
        
        /* Chat Area */
        .chat-area { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            background: var(--bg-body, #0d1117); 
            min-width: 0; 
            position: relative; 
        }
        
        .chat-header-bar { 
            padding: 16px 24px; 
            background: var(--bg-card, #161b22); 
            border-bottom: 1px solid var(--border-color, #30363d); 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }
        
        .chat-user-info { 
            flex: 1; 
            cursor: pointer; 
        }
        
        .chat-user-info h3 { 
            font-size: 16px; 
            font-weight: 600; 
        }
        
        .status-text { 
            font-size: 12px; 
            color: var(--success, #238636); 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }
        
        .status-text .dot { 
            width: 8px; 
            height: 8px; 
            background: var(--success, #238636); 
            border-radius: 50%; 
            display: inline-block; 
        }
        
        .header-actions { 
            display: flex; 
            gap: 8px; 
        }
        
        .header-actions button { 
            background: var(--bg-input, #0d1117); 
            border: 1px solid var(--border-color, #30363d); 
            color: var(--text-secondary, #8b949e); 
            width: 36px; 
            height: 36px; 
            border-radius: var(--radius-sm, 8px); 
            cursor: pointer; 
            transition: 0.2s; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        
        .header-actions button:hover { 
            color: var(--accent-color, #58a6ff); 
            border-color: var(--accent-color, #58a6ff); 
        }
        
        .messages-container { 
            flex: 1; 
            padding: 24px; 
            overflow-y: auto; 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
        }
        
        .message { 
            max-width: 70%; 
            padding: 10px 14px; 
            border-radius: 16px; 
            font-size: 14px; 
            line-height: 1.5; 
            animation: msgIn 0.3s ease; 
        }
        
        @keyframes msgIn { 
            from { opacity: 0; transform: translateY(10px) scale(0.95); } 
            to { opacity: 1; transform: translateY(0) scale(1); } 
        }
        
        .message.sent { 
            background: var(--accent-color, #58a6ff); 
            color: white; 
            align-self: flex-end; 
            border-bottom-right-radius: 6px; 
        }
        
        .message.received { 
            background: var(--bg-card, #161b22); 
            color: var(--text-primary, #e6edf3); 
            align-self: flex-start; 
            border: 1px solid var(--border-color, #30363d); 
            border-bottom-left-radius: 6px; 
        }
        
        .message-time { 
            font-size: 10px; 
            opacity: 0.7; 
            margin-top: 4px; 
            text-align: right; 
        }
        
        .message-input-area { 
            padding: 16px 24px; 
            background: var(--bg-card, #161b22); 
            border-top: 1px solid var(--border-color, #30363d); 
            display: flex; 
            gap: 12px; 
        }
        
        .message-input { 
            flex: 1; 
            padding: 12px 18px; 
            border: 1px solid var(--border-color, #30363d); 
            border-radius: 24px; 
            outline: none; 
            background: var(--bg-input, #0d1117); 
            color: var(--text-primary, #e6edf3); 
            font-size: 14px; 
        }
        
        .message-input:focus { 
            border-color: var(--accent-color, #58a6ff); 
        }
        
        .send-button { 
            background: var(--accent-gradient, linear-gradient(135deg, #667eea 0%, #764ba2 100%)); 
            color: white; 
            border: none; 
            width: 44px; 
            height: 44px; 
            border-radius: 50%; 
            cursor: pointer; 
            transition: 0.2s; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        
        .send-button:hover { 
            transform: scale(1.05); 
        }
        
        .no-chat-selected { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            color: var(--text-secondary, #8b949e); 
        }
        
        .welcome-icon { 
            width: 100px; 
            height: 100px; 
            background: var(--bg-card, #161b22); 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 40px; 
            margin-bottom: 20px; 
            animation: float 3s ease-in-out infinite; 
            border: 1px solid var(--border-color, #30363d); 
        }
        
        @keyframes float { 
            0%, 100% { transform: translateY(0); } 
            50% { transform: translateY(-8px); } 
        }
        
        .focus-mode .sidebar { 
            display: none; 
        }
        
        .focus-active-indicator { 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            background: var(--accent-gradient, linear-gradient(135deg, #667eea 0%, #764ba2 100%)); 
            color: white; 
            padding: 10px 16px; 
            border-radius: 20px; 
            font-size: 13px; 
            font-weight: 600; 
            z-index: 1000; 
            display: none; 
            align-items: center; 
            gap: 8px; 
        }
        
        .focus-mode .focus-active-indicator { 
            display: flex; 
        }
        
        /* Profile Panel */
        .profile-panel { 
            position: fixed; 
            top: 0; 
            right: -400px; 
            width: 380px; 
            height: 100vh; 
            background: var(--bg-card, #161b22); 
            border-left: 1px solid var(--border-color, #30363d); 
            z-index: 1000; 
            transition: 0.3s; 
            overflow-y: auto; 
        }
        
        .profile-panel.open { 
            right: 0; 
        }
        
        .profile-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 999; 
            opacity: 0; 
            visibility: hidden; 
            transition: 0.3s; 
        }
        
        .profile-overlay.show { 
            opacity: 1; 
            visibility: visible; 
        }
        
        .profile-header { 
            padding: 30px; 
            text-align: center; 
            border-bottom: 1px solid var(--border-color, #30363d); 
        }
        
        .profile-body { 
            padding: 24px; 
        }
        
        .info-row { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 12px; 
            background: var(--bg-input, #0d1117); 
            border-radius: var(--radius-md, 12px); 
            margin-bottom: 10px; 
        }
        
        .info-row i { 
            color: var(--accent-color, #58a6ff); 
            width: 20px; 
            text-align: center; 
        }
        
        .info-label { 
            font-size: 12px; 
            color: var(--text-secondary, #8b949e); 
        }
        
        .info-value { 
            font-size: 14px; 
            font-weight: 500; 
        }
        
        /* Avatar styles */
        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--accent-gradient, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            border: 2px solid var(--border-color, #30363d);
        }
        
        .avatar-lg {
            width: 100px;
            height: 100px;
            font-size: 36px;
            border-width: 3px;
            border-color: var(--accent-color, #58a6ff);
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-color, #30363d); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary, #8b949e); }
    </style>
</head>
<body>
    <div class="loader-overlay" id="loader">
        <div class="loader-logo"><i class="fa-brands fa-rocketchat"></i></div>
        <div class="loader-spinner"></div>
        <div class="loader-text">Загрузка...</div>
    </div>

    <div class="profile-overlay" id="profileOverlay" onclick="closeProfile()"></div>
    <div class="profile-panel" id="profilePanel">
        <div class="profile-header">
            <button onclick="closeProfile()" style="position:absolute; top:15px; left:15px; background:none; border:none; color:var(--text-secondary, #8b949e); cursor:pointer; font-size:18px;"><i class="fa-solid fa-xmark"></i></button>
            <div class="avatar avatar-lg" id="pAvatar" style="margin:0 auto 15px;"></div>
            <h3 id="pName" style="margin-bottom:4px;"></h3>
            <div style="color:var(--accent-color, #58a6ff); font-size:13px;" id="pUsername"></div>
        </div>
        <div class="profile-body">
            <div style="font-size:12px; font-weight:600; text-transform:uppercase; color:var(--text-secondary, #8b949e); margin-bottom:12px;">Информация</div>
            <div class="info-row"><i class="fa-solid fa-envelope"></i><div><div class="info-label">Email</div><div class="info-value" id="pEmail">-</div></div></div>
            <div class="info-row"><i class="fa-solid fa-calendar"></i><div><div class="info-label">Регистрация</div><div class="info-value" id="pJoined">-</div></div></div>
            <div class="info-row"><i class="fa-solid fa-comment"></i><div><div class="info-label">Сообщений</div><div class="info-value" id="pCount">0</div></div></div>
            <div style="margin-top:20px;">
                <button class="btn btn-primary" onclick="closeProfile()"><i class="fa-solid fa-paper-plane"></i> Написать</button>
            </div>
        </div>
    </div>

    <div class="messenger-layout">
        <aside class="sidebar">
            <!-- ИСПРАВЛЕННАЯ ШАПКА - ВСЕ В ОДНОМ КОНТЕЙНЕРЕ -->
            <div class="sidebar-header">
                <div class="user-profile" onclick="location.href='profile.php'">
                    <?php $init = strtoupper(substr($current_user['username'], 0, 1)); ?>
                    <div class="avatar" style="background-image:url('uploads/avatars/<?=htmlspecialchars($current_user['avatar']??'')?>'); background-size:cover; background-position:center;"><?=empty($current_user['avatar'])||$current_user['avatar']=='default.png'?$init:''?></div>
                    <div class="user-info">
                        <div class="user-name"><?=htmlspecialchars($current_user['username'])?></div>
                        <div class="user-status">В сети</div>
                    </div>
                </div>
                
                <!-- ВСЕ КНОПКИ В ОДНОМ КОНТЕЙНЕРЕ -->
                <div class="nav-buttons">
                    <a href="friend_requests.php" title="Заявки в друзья" style="position:relative;">
                        <i class="fa-solid fa-user-check"></i>
                        <?php if($pending_count > 0): ?>
                            <span style="position:absolute; top:-2px; right:-2px; background:var(--danger, #f85149); color:white; font-size:9px; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center;"><?=$pending_count?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="search_users.php" title="Поиск друзей">
                        <i class="fa-solid fa-user-plus"></i>
                    </a>
                    
                    <button onclick="toggleTheme()" title="Сменить тему">
                        <i class="fa-solid fa-palette"></i>
                    </button>
                    
                    <a href="stats.php" title="Статистика">
                        <i class="fa-solid fa-chart-bar"></i>
                    </a>
                    
                    <a href="logout.php" title="Выйти из аккаунта">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                </div>
            </div>
            
            <div class="search-box"><input type="text" placeholder="🔍 Поиск чата..." onkeyup="filterChats(this.value)"></div>
            <div class="chat-list">
                <?php if(empty($friends)): ?>
                    <div style="padding:40px 20px; text-align:center; color:var(--text-secondary, #8b949e);">
                        <i class="fa-solid fa-users" style="font-size:40px; opacity:0.2; margin-bottom:10px; display:block;"></i>
                        <p>Нет друзей</p>
                        <a href="search_users.php" style="font-size:13px; margin-top:5px; display:inline-block;">Найти друзей →</a>
                    </div>
                <?php else: foreach($friends as $f): $fi=strtoupper(substr($f['username'],0,1)); ?>
                    <a href="messenger.php?chat=<?=$f['id']?>" class="chat-item <?=$active_chat_id==$f['id']?'active':''?>" data-name="<?=htmlspecialchars(strtolower(($f['full_name']??'').' '.$f['username']))?>">
                        <div class="avatar" style="background-image:url('uploads/avatars/<?=htmlspecialchars($f['avatar']??'')?>'); background-size:cover; background-position:center;"><?=empty($f['avatar'])||$f['avatar']=='default.png'?$fi:''?></div>
                        <div class="chat-info">
                            <div class="chat-header">
                                <div class="chat-name"><?=htmlspecialchars($f['full_name']?:$f['username'])?></div>
                                <div class="chat-time"><?=!empty($f['last_time'])?date('H:i',strtotime($f['last_time'])):''?></div>
                            </div>
                            <div class="chat-preview"><?=htmlspecialchars($f['last_message']?:'Нет сообщений')?></div>
                        </div>
                        <?php if(!empty($f['unread_count'])): ?><div class="unread-badge"><?=$f['unread_count']?></div><?php endif; ?>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </aside>

        <main class="chat-area">
            <?php if($active_chat_id>0 && $chat_user): 
                $ci=strtoupper(substr($chat_user['username'],0,1));
            ?>
                <div class="chat-header-bar">
                    <div class="avatar" style="background-image:url('uploads/avatars/<?=htmlspecialchars($chat_user['avatar']??'')?>'); background-size:cover; background-position:center; cursor:pointer;" onclick="openProfile(<?=$chat_user['id']?>, '<?=htmlspecialchars(addslashes($chat_user['username']))?>', '<?=htmlspecialchars(addslashes($chat_user['full_name']?:$chat_user['username']))?>', '<?=htmlspecialchars(addslashes($chat_user['email']))?>', '<?=htmlspecialchars(addslashes($chat_user['created_at']))?>', '<?=htmlspecialchars(addslashes($chat_user['avatar']))?>')"><?=empty($chat_user['avatar'])||$chat_user['avatar']=='default.png'?$ci:''?></div>
                    <div class="chat-user-info" onclick="openProfile(<?=$chat_user['id']?>, '<?=htmlspecialchars(addslashes($chat_user['username']))?>', '<?=htmlspecialchars(addslashes($chat_user['full_name']?:$chat_user['username']))?>', '<?=htmlspecialchars(addslashes($chat_user['email']))?>', '<?=htmlspecialchars(addslashes($chat_user['created_at']))?>', '<?=htmlspecialchars(addslashes($chat_user['avatar']))?>')">
                        <h3><?=htmlspecialchars($chat_user['full_name']?:$chat_user['username'])?></h3>
                        <div class="status-text"><span class="dot"></span> В сети</div>
                    </div>
                    <div class="header-actions">
                        <button onclick="toggleFocus()" title="Режим фокусировки"><i class="fa-solid fa-expand"></i></button>
                    </div>
                </div>
                <div class="messages-container" id="msgContainer">
                    <?php if(empty($chat_messages)): ?>
                        <div style="text-align:center; color:var(--text-secondary, #8b949e); margin:auto;">
                            <div style="font-size:40px; margin-bottom:10px;">👋</div>
                            <p>Начните общение</p>
                        </div>
                    <?php else: foreach($chat_messages as $m): if(!$m) continue; ?>
                        <div class="message <?=($m['sender_id']==$user_id)?'sent':'received'?>">
                            <?=nl2br(htmlspecialchars($m['message']))?>
                            <div class="message-time"><?=date('H:i',strtotime($m['created_at']))?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <form method="POST" class="message-input-area">
                    <input type="text" name="message" class="message-input" placeholder="Напишите сообщение..." required autocomplete="off">
                    <button type="submit" name="send_message" class="send-button"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
                <div class="focus-active-indicator" id="focusInd"><i class="fa-solid fa-eye-slash"></i> Режим фокусировки <button onclick="toggleFocus()" style="background:none; border:none; color:white; margin-left:8px; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button></div>
            <?php else: ?>
                <div class="no-chat-selected">
                    <div class="welcome-icon">💬</div>
                    <h2>Добро пожаловать!</h2>
                    <p>Выберите чат слева или найдите новых друзей</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        window.addEventListener('load', ()=>{ setTimeout(()=>{ document.getElementById('loader').classList.add('hidden'); setTimeout(()=>document.getElementById('loader').style.display='none',500); },500); });
        function filterChats(t){ document.querySelectorAll('.chat-item').forEach(i=>i.style.display=i.dataset.name.includes(t.toLowerCase())?'flex':'none'); }
        function toggleFocus(){ document.body.classList.toggle('focus-mode'); localStorage.setItem('focus',document.body.classList.contains('focus-mode')); }
       function toggleTheme(){ 
    const b = document.body; 
    if(b.classList.contains('theme-light')){ 
        b.classList.remove('theme-light'); 
        localStorage.setItem('theme','dark'); 
    } else { 
        b.classList.add('theme-light'); 
        localStorage.setItem('theme','light'); 
    } 
}
        function openProfile(id,name,email,created,avatar){
            document.getElementById('pName').textContent=name;
            document.getElementById('pUsername').textContent='@'+name;
            document.getElementById('pEmail').textContent=email||'Не указан';
            document.getElementById('pJoined').textContent=created?new Date(created).toLocaleDateString('ru-RU'):'-';
            document.getElementById('pCount').textContent=document.querySelectorAll('.message').length;
            const av=document.getElementById('pAvatar');
            if(avatar && avatar!=='default.png'){ av.style.backgroundImage=`url('uploads/avatars/${avatar}')`; av.textContent=''; } else { av.style.backgroundImage=''; av.textContent=name.charAt(0).toUpperCase(); }
            document.getElementById('profilePanel').classList.add('open');
            document.getElementById('profileOverlay').classList.add('show');
        }
        function closeProfile(){ document.getElementById('profilePanel').classList.remove('open'); document.getElementById('profileOverlay').classList.remove('show'); }
        document.addEventListener('DOMContentLoaded',()=>{
            const t=localStorage.getItem('theme'); if(t==='light') document.body.className='theme-light'; if(t==='purple') document.body.className='theme-purple';
            if(localStorage.getItem('focus')==='true') document.body.classList.add('focus-mode');
            const c=document.getElementById('msgContainer'); if(c) c.scrollTop=c.scrollHeight;
        });
    </script>
</body>
</html>