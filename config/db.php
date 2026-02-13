<?php
// Configurações Padrão do XAMPP
$host = 'localhost';
$dbname = 'app_agencia_db';
$username = 'root'; // Usuário padrão do XAMPP
$password = '';     // Senha padrão do XAMPP é vazia



try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Linha de teste (Comente ou remova após validar que a tela não ficou branca)
    // echo "Conexão com banco realizada com sucesso!"; 

} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

?>