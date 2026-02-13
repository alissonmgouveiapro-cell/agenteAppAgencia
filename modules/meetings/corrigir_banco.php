<?php
/* Arquivo: modules/meetings/corrigir_banco.php */
/* Rode este arquivo uma vez para criar as colunas que faltam */

ini_set('display_errors', 1);
error_reporting(E_ALL);
require '../../config/db.php';

echo "<h2>Iniciando correção do banco de dados...</h2>";

try {
    // 1. Tenta adicionar scheduled_at
    $pdo->exec("ALTER TABLE meetings ADD COLUMN scheduled_at DATETIME DEFAULT NULL");
    echo "<p style='color:green'>✅ Coluna 'scheduled_at' criada com sucesso!</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<p style='color:orange'>⚠️ Coluna 'scheduled_at' já existia.</p>";
    } else {
        echo "<p style='color:red'>❌ Erro ao criar 'scheduled_at': " . $e->getMessage() . "</p>";
    }
}

try {
    // 2. Tenta adicionar last_ping
    $pdo->exec("ALTER TABLE meetings ADD COLUMN last_ping DATETIME DEFAULT NULL");
    echo "<p style='color:green'>✅ Coluna 'last_ping' criada com sucesso!</p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<p style='color:orange'>⚠️ Coluna 'last_ping' já existia.</p>";
    } else {
        echo "<p style='color:red'>❌ Erro ao criar 'last_ping': " . $e->getMessage() . "</p>";
    }
}

echo "<h3>Concluído. Tente acessar a página de reuniões novamente.</h3>";
echo "<a href='reuniao.php'>Voltar para Reuniões</a>";
?>