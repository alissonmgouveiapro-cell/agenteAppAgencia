<?php
/* Arquivo: /modules/notifications/check_new.php */
header('Content-Type: application/json');
session_start();

// Se não estiver logado, retorna 0
if (!isset($_SESSION['tenant_id'])) {
    echo json_encode(['unread' => 0]);
    exit;
}

require '../../config/db.php';

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE tenant_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['tenant_id']]);
    $count = $stmt->fetchColumn();
    
    echo json_encode(['unread' => (int)$count]);
} catch (Exception $e) {
    echo json_encode(['unread' => 0]);
}
?>