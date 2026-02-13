<?php
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: projetos.php");
    exit;
}

$id = $_GET['id'];
$tenant_id = $_SESSION['tenant_id'];

try {
    $pdo->beginTransaction();

    // 1. Opcional: Buscar caminhos de arquivos para deletar do servidor
    $stmtFiles = $pdo->prepare("SELECT file_path FROM project_files WHERE project_id = ?");
    $stmtFiles->execute([$id]);
    $files = $stmtFiles->fetchAll();
    foreach ($files as $file) {
        $path = "../../uploads/" . $file['file_path'];
        if (file_exists($path)) unlink($path);
    }

    // 2. Deletar tudo em cascata (Arquivos, Posts, Tarefas, Projeto)
    // Nota: Se você configurou "ON DELETE CASCADE" no banco, basta deletar o projeto.
    // Caso contrário, deletamos manualmente:
    $pdo->prepare("DELETE FROM project_list_items WHERE list_id IN (SELECT id FROM project_lists WHERE project_id = ?)")->execute([$id]);
    $pdo->prepare("DELETE FROM project_lists WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM tasks WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM project_files WHERE project_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM project_deliverables WHERE project_id = ?")->execute([$id]);
    
    // 3. Deleta o projeto principal
    $stmtProj = $pdo->prepare("DELETE FROM projects WHERE id = ? AND tenant_id = ?");
    $stmtProj->execute([$id, $tenant_id]);

    $pdo->commit();
    header("Location: projetos.php?msg=excluido");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Erro ao excluir projeto: " . $e->getMessage());
}