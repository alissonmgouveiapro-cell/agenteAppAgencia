<?php
/* Arquivo: /modules/projects/toggle_task.php */
/* Função: Alternar status da tarefa (Feito / Pendente) */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || !isset($_GET['pid'])) {
    header("Location: projetos.php"); exit;
}

$task_id = $_GET['id'];
$project_id = $_GET['pid'];
$tenant_id = $_SESSION['tenant_id'];

try {
    // 1. Descobre o status atual
    $stmt = $pdo->prepare("SELECT is_completed FROM tasks WHERE id = :id AND tenant_id = :t");
    $stmt->execute(['id' => $task_id, 't' => $tenant_id]);
    $task = $stmt->fetch();

    if ($task) {
        // 2. Inverte o status (Se é 0 vira 1, se é 1 vira 0)
        $novo_status = $task['is_completed'] == 0 ? 1 : 0;

        $stmtUpdate = $pdo->prepare("UPDATE tasks SET is_completed = :s WHERE id = :id");
        $stmtUpdate->execute(['s' => $novo_status, 'id' => $task_id]);
    }

    // Voltar para o projeto
    header("Location: detalhes.php?id=$project_id");
    exit;

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>