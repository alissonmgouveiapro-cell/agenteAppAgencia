<?php
/* Arquivo: modules/admin/corrigir_tenants.php */
/* Versão: Caminho Corrigido */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// CORREÇÃO AQUI: Adicionado ../../ para voltar duas pastas
require '../../config/db.php'; 

echo "<h2>Corrigindo Tabela Tenants...</h2>";

try {
    // Adiciona a coluna PLAN
    $pdo->exec("ALTER TABLE tenants ADD COLUMN plan VARCHAR(20) DEFAULT 'pro'");
    echo "<p style='color:green'>✅ Coluna 'plan' criada com sucesso!</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<p style='color:orange'>⚠️ A coluna 'plan' já existia.</p>";
    } else {
        echo "<p style='color:red'>❌ Erro: " . $e->getMessage() . "</p>";
    }
}

echo "<h3>Pronto! Pode apagar este arquivo e tentar criar o usuário novamente.</h3>";
?>