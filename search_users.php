<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$search_query = $_GET['q'] ?? '';
$message = '';

// Получаем текущие связи для проверки статуса
$stmt = $pdo->prepare("SELECT friend_id, status FROM friends WHERE user_id = ?");
$stmt->execute([$user_id]);
$sent_relations = [];
foreach ($stmt->fetchAll() as $row) $sent_relations[$row['friend_id']] = $row['status'];

$stmt = $pdo->prepare("SELECT user_id, status FROM friends WHERE friend_id = ?");
$stmt->execute([$user_id]);
$received_relations = [];
foreach ($stmt->fetchAll() as $row) $received_relations[$row['user_id']] = $row['status'];

// Обработка отправки заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_friend'])) {
    $target_id = (int)$_POST['target_id'];
    
    if (!isset($sent_relations[$target_id]) && !isset($received_relations[$target_id])) {
        $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
        if ($stmt->execute([$user_id, $target_id])) {
            $message = "✅ Заявка отправлена!";
            $sent_relations[$target_id] = 'pending';
        }
    } else {
        $message = "⚠️ Заявка уже существует или вы уже друзья.";
    }
}

// Поиск пользователей
$users = [];
if ($search_query) {
    $stmt = $pdo->prepare("SELECT id, username, full_name, avatar FROM users WHERE (username LIKE ? OR full_name LIKE ?) AND id != ? LIMIT 20");
    $stmt->execute(["%$search_query%", "%$search_query%", $user_id]);
    $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск пользователей</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .search-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .search-box-large {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .search-input-large {
            width: 100%;
            padding: 16px 24px;
            background: var(--bg-input);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            color: var(--text-primary);
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }
        
        .search-input-large:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px rgba(88, 166, 255, 0.1);
        }
        
        .search-input-large::placeholder {
            color: var(--text-secondary);
        }
        
        .results-section {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: var(--bg-input);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        
        .user-card:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-sm);
        }
        
        .user-card-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        
        .user-card-info {
            flex: 1;
        }
        
        .user-card-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .user-card-username {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .status-friends {
            background: rgba(35, 134, 54, 0.1);
            color: var(--success);
        }
        
        .btn-add-friend {
            padding: 10px 20px;
            background: var(--accent-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-add-friend:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-add-friend:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 64px;
            opacity: 0.2;
            margin-bottom: 20px;
            display: block;
        }
        
        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="header-nav">
        <h1><i class="fa-brands fa-rocketchat"></i></h1>
        <div>
            <a href="messenger.php"><i class="fa-solid fa-comments"></i> Чаты</a>
            <a href="friend_requests.php"><i class="fa-solid fa-user-check"></i> Заявки</a>
            <a href="profile.php"><i class="fa-solid fa-user"></i> Профиль</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Выйти</a>
        </div>
    </div>

    <div class="search-container">
        <?php if ($message): ?>
            <div class="alert <?=strpos($message,'✅')!==false?'alert-success':'alert-error'?>">
                <?=htmlspecialchars($message)?>
            </div>
        <?php endif; ?>

        <div class="search-box-large">
            <form method="GET">
                <input type="text" name="q" class="search-input-large" 
                       placeholder="🔍 Поиск по имени или username..." 
                       value="<?=htmlspecialchars($search_query)?>" autofocus>
            </form>
        </div>

        <?php if ($search_query): ?>
            <div class="results-section">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fa-solid fa-users" style="color: var(--accent-color);"></i>
                        Результаты поиска
                    </div>
                    <div style="color: var(--text-secondary); font-size: 14px;">
                        Найдено: <?=count($users)?>
                    </div>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-user-slash"></i>
                        <h3>Никого не найдено</h3>
                        <p>Попробуйте изменить поисковый запрос</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($users as $u): 
                        $initial = strtoupper(substr($u['username'], 0, 1));
                        $status = '';
                        $button = '';
                        
                        if (isset($received_relations[$u['id']]) && $received_relations[$u['id']] === 'pending') {
                            $status = '<span class="status-badge status-pending"><i class="fa-solid fa-clock"></i> Заявка входящая</span>';
                        } elseif (isset($sent_relations[$u['id']]) && $sent_relations[$u['id']] === 'pending') {
                            $status = '<span class="status-badge status-pending"><i class="fa-solid fa-clock"></i> Заявка отправлена</span>';
                        } elseif (isset($sent_relations[$u['id']]) && $sent_relations[$u['id']] === 'accepted') {
                            $status = '<span class="status-badge status-friends"><i class="fa-solid fa-check"></i> Уже в друзьях</span>';
                            $button = '<a href="messenger.php?chat='.$u['id'].'" class="btn-add-friend" style="background: var(--success);"><i class="fa-solid fa-comment"></i> Написать</a>';
                        } else {
                            $button = '
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="target_id" value="'.$u['id'].'">
                                    <button type="submit" name="add_friend" class="btn-add-friend">
                                        <i class="fa-solid fa-user-plus"></i> Добавить
                                    </button>
                                </form>';
                        }
                    ?>
                        <div class="user-card">
                            <div class="user-card-avatar" style="background-image:url('uploads/avatars/<?=htmlspecialchars($u['avatar']??'')?>'); background-size:cover; background-position:center;">
                                <?=empty($u['avatar'])||$u['avatar']=='default.png'?$initial:''?>
                            </div>
                            <div class="user-card-info">
                                <div class="user-card-name"><?=htmlspecialchars($u['full_name'] ?: $u['username'])?></div>
                                <div class="user-card-username">@<?=htmlspecialchars($u['username'])?></div>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <?=$status?>
                                <?=$button?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="results-section">
                <div class="empty-state">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <h3>Начните поиск</h3>
                    <p>Введите имя или username пользователя в поле выше</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>