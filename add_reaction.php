<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['message_id'], $_POST['reaction'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$message_id = (int)$_POST['message_id'];
$reaction = $_POST['reaction'];

try {
    // Проверяем, есть ли уже такая реакция
    $stmt = $pdo->prepare("SELECT id FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction = ?");
    $stmt->execute([$message_id, $user_id, $reaction]);
    
    if ($stmt->fetch()) {
        // Удаляем реакцию (если уже есть)
        $stmt = $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ? AND reaction = ?");
        $stmt->execute([$message_id, $user_id, $reaction]);
    } else {
        // Добавляем реакцию
        $stmt = $pdo->prepare("INSERT INTO message_reactions (message_id, user_id, reaction) VALUES (?, ?, ?)");
        $stmt->execute([$message_id, $user_id, $reaction]);
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>