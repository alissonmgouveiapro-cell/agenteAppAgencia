<?php
/* Arquivo: /modules/notifications/api.php */
header('Content-Type: application/json');
session_start();
require '../../config/db.php'; // Ajuste o caminho se necessário

if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$action = $_GET['action'] ?? '';

// 1. CHECAR CONTAGEM (Para o Menu)
if ($action === 'count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE tenant_id = ? AND is_read = 0");
    $stmt->execute([$tenant_id]);
    $count = $stmt->fetchColumn();
    echo json_encode(['count' => $count]);
    exit;
}

// 2. MARCAR UMA COMO LIDA
if ($action === 'read' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    echo json_encode(['success' => true]);
    exit;
}

// 3. MARCAR TODAS COMO LIDAS
if ($action === 'clear_all') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    echo json_encode(['success' => true]);
    exit;
}

// 4. EXCLUIR HISTÓRICO (O QUE ESTAVA FALTANDO)
if ($action === 'delete_history') {
    // Apaga apenas as notificações Lidas (is_read = 1)
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE tenant_id = ? AND is_read = 1");
    $stmt->execute([$tenant_id]);
    echo json_encode(['success' => true]);
    exit;
}
?>