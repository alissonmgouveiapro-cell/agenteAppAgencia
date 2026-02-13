<?php
/* Arquivo: /modules/projects/delete_file.php */
/* Função: Excluir arquivo físico e registro do banco com segurança */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || !isset($_GET['pid'])) {
    header("Location: projetos.php");
    exit;
}

$file_id = $_GET['id'];
$project_id = $_GET['pid']; // Precisamos disso para voltar para a página certa
$tenant_id = $_SESSION['tenant_id'];

try {
    // 1. Busca o arquivo garantindo que é do Tenant logado
    $stmt = $pdo->prepare("SELECT file_path FROM project_files WHERE id = :id AND tenant_id = :tenant_id");
    $stmt->execute(['id' => $file_id, 'tenant_id' => $tenant_id]);
    $arquivo = $stmt->fetch();

    if ($arquivo) {
        // 2. Caminho físico
        $caminho_fisico = '../../uploads/' . $arquivo['file_path'];

        // 3. Remove o arquivo da pasta (se existir)
        if (file_exists($caminho_fisico)) {
            unlink($caminho_fisico);
        }

        // 4. Remove do Banco de Dados
        $stmtDelete = $pdo->prepare("DELETE FROM project_files WHERE id = :id");
        $stmtDelete->execute(['id' => $file_id]);

        // Sucesso
        header("Location: detalhes.php?id=$project_id&msg=arquivo_excluido");
        exit;
    } else {
        die("Arquivo não encontrado ou permissão negada.");
    }

} catch (PDOException $e) {
    die("Erro ao excluir: " . $e->getMessage());
}
?>