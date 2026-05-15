<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req_id = (int)$_POST['req_id'];
    $action = $_POST['action'];
    
    if ($action === 'accept') {
        $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE id = ? AND friend_id = ?");
        $stmt->execute([$req_id, $user_id]);
    } elseif ($action === 'decline' || $action === 'cancel') {
        $stmt = $pdo->prepare("DELETE FROM friends WHERE id = ? AND (user_id = ? OR friend_id = ?)");
        $stmt->execute([$req_id, $user_id, $user_id]);
    }
    header("Location: friend_requests.php");
    exit;
}

// Входящие заявки
$stmt = $pdo->prepare("
    SELECT f.id, u.id as user_id, u.username, u.full_name, u.avatar, f.created_at
    FROM friends f JOIN users u ON f.user_id = u.id 
    WHERE f.friend_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$incoming = $stmt->fetchAll();

// Исходящие заявки
$stmt = $pdo->prepare("
    SELECT f.id, u.id as user_id, u.username, u.full_name, u.avatar, f.created_at
    FROM friends f JOIN users u ON f.friend_id = u.id 
    WHERE f.user_id = ? AND f.status = 'pending'
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$outgoing = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки в друзья</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .requests-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .section-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .request-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px;
            background: var(--bg-input);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        
        .request-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-sm);
        }
        
        .request-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        
        .request-info {
            flex: 1;
        }
        
        .request-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 15px;
            margin-bottom: 4px;
        }
        
        .request-date {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .request-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s;
        }
        
        .btn-accept {
            background: rgba(35, 134, 54, 0.1);
            color: var(--success);
        }
        
        .btn-accept:hover {
            background: var(--success);
            color: white;
        }
        
        .btn-decline {
            background: rgba(248, 81, 73, 0.1);
            color: var(--danger);
        }
        
        .btn-decline:hover {
            background: var(--danger);
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 48px;
            opacity: 0.3;
            margin-bottom: 15px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="header-nav">
        <h1><i class="fa-brands fa-rocketchat"></i></h1>
        <div>
            <a href="messenger.php"><i class="fa-solid fa-comments"></i> Чаты</a>
            <a href="search_users.php"><i class="fa-solid fa-search"></i> Поиск</a>
            <a href="profile.php"><i class="fa-solid fa-user"></i> Профиль</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Выйти</a>
        </div>
    </div>

    <div class="requests-container">
        <!-- Входящие заявки -->
        <div class="section-card">
            <div class="section-title">
                <i class="fa-solid fa-inbox" style="color: var(--accent-color);"></i>
                Входящие заявки
            </div>
            
            <?php if (empty($incoming)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-envelope-open"></i>
                    <p>Нет новых заявок</p>
                </div>
            <?php else: ?>
                <?php foreach ($incoming as $req): 
                    $initial = strtoupper(substr($req['username'], 0, 1));
                ?>
                    <div class="request-item">
                        <div class="request-avatar" style="background-image:url('uploads/avatars/<?=htmlspecialchars($req['avatar']??'')?>'); background-size:cover; background-position:center;">
                            <?=empty($req['avatar'])||$req['avatar']=='default.png'?$initial:''?>
                        </div>
                        <div class="request-info">
                            <div class="request-name"><?=htmlspecialchars($req['full_name'] ?: $req['username'])?></div>
                            <div class="request-date">Отправлено <?=date('d.m.Y H:i', strtotime($req['created_at']))?></div>
                        </div>
                        <div class="request-actions">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="req_id" value="<?=$req['id']?>">
                                <button type="submit" name="action" value="accept" class="btn-icon btn-accept" title="Принять">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="req_id" value="<?=$req['id']?>">
                                <button type="submit" name="action" value="decline" class="btn-icon btn-decline" title="Отклонить">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Исходящие заявки -->
        <div class="section-card">
            <div class="section-title">
                <i class="fa-solid fa-paper-plane" style="color: var(--accent-color);"></i>
                Исходящие заявки
            </div>
            
            <?php if (empty($outgoing)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-paper-plane"></i>
                    <p>Вы не отправляли заявок</p>
                </div>
            <?php else: ?>
                <?php foreach ($outgoing as $req): 
                    $initial = strtoupper(substr($req['username'], 0, 1));
                ?>
                    <div class="request-item">
                        <div class="request-avatar" style="background-image:url('uploads/avatars/<?=htmlspecialchars($req['avatar']??'')?>'); background-size:cover; background-position:center;">
                            <?=empty($req['avatar'])||$req['avatar']=='default.png'?$initial:''?>
                        </div>
                        <div class="request-info">
                            <div class="request-name"><?=htmlspecialchars($req['full_name'] ?: $req['username'])?></div>
                            <div class="request-date">Отправлено <?=date('d.m.Y H:i', strtotime($req['created_at']))?></div>
                        </div>
                        <div class="request-actions">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="req_id" value="<?=$req['id']?>">
                                <button type="submit" name="action" value="cancel" class="btn-icon btn-decline" title="Отменить">
                                    <i class="fa-solid fa-ban"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>