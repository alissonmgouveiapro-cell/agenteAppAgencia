<?php
/* Arquivo: /modules/projects/ajax_update_status.php */
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;
$tenant_id = $_SESSION['tenant_id'];

if ($id && $status) {
    try {
        // Atualiza apenas se o projeto pertencer ao tenant do usuário (Segurança)
        $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$status, $id, $tenant_id]);
        echo "Sucesso";
    } catch (Exception $e) {
        http_response_code(500);
    }
}
?>