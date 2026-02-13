<?php
/* Arquivo: modules/admin/acesso.php */
/* Versão: Gatekeeper Dinâmico (Banco de Dados) */

session_start();
require '../../config/db.php';

// 1. Verifica se está logado no sistema
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

// --- AUTO-MIGRAÇÃO (Cria tabela de Admins) ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS super_admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL UNIQUE,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { }

// Busca e-mail atual do usuário logado
if (!isset($_SESSION['user_email'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['user_email'] = $stmt->fetchColumn();
}
$my_email = $_SESSION['user_email'];

// --- VERIFICAÇÃO DE SEGURANÇA ---

// A. Verifica se existe no banco
$stmtCheck = $pdo->prepare("SELECT id FROM super_admins WHERE email = ?");
$stmtCheck->execute([$my_email]);
$is_allowed = $stmtCheck->rowCount() > 0;

// B. FALLBACK DE SEGURANÇA (Primeiro Acesso)
// Se a tabela estiver vazia, libera o seu e-mail principal e cadastra ele.
if (!$is_allowed) {
    $count = $pdo->query("SELECT COUNT(*) FROM super_admins")->fetchColumn();
    // Coloque aqui SEU E-MAIL PRINCIPAL para garantir que você nunca fique trancado fora
    $master_email = 'alissonmgouveia.pro@gmail.com'; 
    
    if ($count == 0 && $my_email == $master_email) {
        $pdo->prepare("INSERT INTO super_admins (email) VALUES (?)")->execute([$my_email]);
        $is_allowed = true;
    }
}

if (!$is_allowed) {
    die("⛔ <b>Acesso Negado.</b> Seu e-mail ($my_email) não é um Super Admin.");
}

$erro = "";

// 3. Processa a Senha Mestra
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['admin_pin'];
    $master_pass = "BLISS2024"; // Sua Senha Mestra Continua Aqui

    if ($pin === $master_pass) {
        $_SESSION['is_super_admin'] = true;
        header("Location: convites.php");
        exit;
    } else {
        $erro = "Senha Mestra Incorreta.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Acesso Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background-color: #000; color: #fff; font-family: 'Manrope', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; background-image: radial-gradient(circle at center, #1a1a1a 0%, #000 100%); }
        .lock-box { text-align: center; width: 100%; max-width: 350px; padding: 40px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; backdrop-filter: blur(10px); box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .avatar { width: 80px; height: 80px; margin: 0 auto 20px; border-radius: 50%; background: #d4af37; color: #000; font-size: 2rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(212, 175, 55, 0.4); }
        h2 { margin: 0 0 5px 0; color: #fff; font-weight: 700; }
        p { color: #888; margin: 0 0 30px 0; font-size: 0.9rem; }
        input { width: 100%; padding: 15px; text-align: center; font-size: 1.2rem; letter-spacing: 5px; background: #111; border: 1px solid #333; color: #d4af37; border-radius: 8px; outline: none; transition: 0.3s; margin-bottom: 20px; box-sizing: border-box; }
        input:focus { border-color: #d4af37; box-shadow: 0 0 10px rgba(212, 175, 55, 0.2); }
        button { width: 100%; padding: 15px; background: #d4af37; color: #000; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s; }
        button:hover { background: #b5952f; transform: scale(1.02); }
        .back-link { display: block; margin-top: 20px; color: #666; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { color: #fff; }
        .error { color: #ef4444; margin-bottom: 15px; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="lock-box">
    <div class="avatar">👑</div>
    <h2>Área Restrita</h2>
    <p>Olá, <?php echo explode(' ', $_SESSION['user_name'] ?? 'Admin')[0]; ?>. Confirme sua identidade.</p>
    <?php if($erro): ?><div class="error"><?php echo $erro; ?></div><?php endif; ?>
    <form method="POST">
        <input type="password" name="admin_pin" placeholder="PIN MESTRE" required autofocus autocomplete="off">
        <button type="submit">Desbloquear</button>
    </form>
    <a href="../../index.php" class="back-link">← Voltar ao Sistema</a>
</div>
</body>
</html>