Arquivo: reset_senhas.php
Função: Forçar a atualização das senhas dos usuários de teste para '123456' com um hash válido gerado na hora.

<?php
require 'config/db.php';

// A senha que queremos usar
$senha_plana = '123456';

// Gerar o hash seguro usando o PHP do seu servidor atual
$novo_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

echo "<h3>Ferramenta de Reset de Senhas</h3>";
echo "Gerando novo hash para a senha: <strong>123456</strong><br>";
echo "Hash gerado: <small>$novo_hash</small><br><br>";

try {
    // Atualiza o Dono da Fox
    $stmt1 = $pdo->prepare("UPDATE users SET password = :pass WHERE email = 'dono@fox.com'");
    $stmt1->execute(['pass' => $novo_hash]);
    echo "✅ Usuário <strong>dono@fox.com</strong> atualizado.<br>";

    // Atualiza o Dono da Brasil
    $stmt2 = $pdo->prepare("UPDATE users SET password = :pass WHERE email = 'dono@brasil.com'");
    $stmt2->execute(['pass' => $novo_hash]);
    echo "✅ Usuário <strong>dono@brasil.com</strong> atualizado.<br>";

    echo "<hr><h3>Tudo pronto!</h3>";
    echo "<a href='login.php'>Voltar para Login</a>";

} catch (PDOException $e) {
    echo "❌ Erro ao atualizar: " . $e->getMessage();
}
?>