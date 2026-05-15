<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$user_id]);
$u = $stmt->fetch();

$msg = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])) {
    $name = trim($_POST['full_name']);
    $bio = trim($_POST['bio']);
    $avatar = $u['avatar'];
    
    if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error']===0) {
        $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif'])) {
            $dir = 'uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $newName = uniqid().'.'.$ext;
            if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $dir.$newName)) {
                $avatar = $newName;
            }
        }
    }
    
    $stmt = $pdo->prepare("UPDATE users SET full_name=?, bio=?, avatar=? WHERE id=?");
    if ($stmt->execute([$name, $bio, $avatar, $user_id])) {
        $msg = "✅ Профиль обновлен";
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch();
    } else {
        $msg = "❌ Ошибка сохранения";
    }
}
$init = strtoupper(substr($u['username'],0,1));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="header-nav">
        <h1><i class="fa-brands fa-rocketchat"></i></h1>
        <div>
            <a href="messenger.php"><i class="fa-solid fa-comments"></i> Чаты</a>
            <a href="profile.php" style="color:var(--accent-color);"><i class="fa-solid fa-user"></i> Профиль</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Выйти</a>
        </div>
    </div>

    <div class="auth-container">
        <div class="card" style="max-width:500px;">
            <?php if($msg): ?><div class="alert <?=strpos($msg,'✅')!==false?'alert-success':'alert-error'?>"><?=$msg?></div><?php endif; ?>
            
            <div style="text-align:center; margin-bottom:20px;">
                <div class="avatar avatar-lg" style="margin:0 auto 15px; background-image:url('uploads/avatars/<?=htmlspecialchars($u['avatar']??'')?>'); background-size:cover; background-position:center;">
                    <?=empty($u['avatar'])||$u['avatar']=='default.png'?$init:''?>
                </div>
                <h2>Мой профиль</h2>
                <p class="subtitle">Управляйте своими данными</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="input-group">
                    <label>Аватар</label>
                    <input type="file" name="avatar_file" accept="image/*" style="width:100%; padding:10px; background:var(--bg-input); border:1px solid var(--border-color); border-radius:var(--radius-md); color:var(--text-primary);">
                </div>
                <div class="input-group">
                    <label>Имя пользователя</label>
                    <div class="input-wrapper"><i class="fa-solid fa-user"></i><input type="text" value="<?=htmlspecialchars($u['username'])?>" disabled></div>
                </div>
                <div class="input-group">
                    <label>Полное имя</label>
                    <div class="input-wrapper"><i class="fa-solid fa-id-card"></i><input type="text" name="full_name" value="<?=htmlspecialchars($u['full_name']??'')?>" placeholder="Ваше имя"></div>
                </div>
                <div class="input-group">
                    <label>Email</label>
                    <div class="input-wrapper"><i class="fa-solid fa-envelope"></i><input type="email" value="<?=htmlspecialchars($u['email'])?>" disabled></div>
                </div>
                <div class="input-group">
                    <label>О себе</label>
                    <div class="input-wrapper"><i class="fa-solid fa-pen"></i><textarea name="bio" rows="4" style="padding:12px 15px 12px 45px;"><?=htmlspecialchars($u['bio']??'')?></textarea></div>
                </div>
                <button type="submit" name="save" class="btn btn-primary"><i class="fa-solid fa-save"></i> Сохранить</button>
            </form>
        </div>
    </div>
</body>
</html>