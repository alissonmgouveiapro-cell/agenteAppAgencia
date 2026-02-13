<?php
/* Arquivo: nova_senha.php */
require 'config/db.php';

$token = $_GET['token'] ?? '';
$msg = '';
$validToken = false;

// 1. Validar Token ao abrir a página
if ($token) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $validToken = true;
    } else {
        $msg = "Este link é inválido ou expirou.";
    }
} else {
    header("Location: login.php"); exit;
}

// 2. Processar Nova Senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $new_pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $msg = "A senha deve ter pelo menos 6 caracteres.";
    } elseif ($new_pass !== $confirm_pass) {
        $msg = "As senhas não coincidem.";
    } else {
        // Atualiza senha e limpa o token
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
            ->execute([$hash, $user['id']]);
        
        $msg = "✅ Senha alterada com sucesso! <a href='login.php'>Fazer Login</a>";
        $validToken = false; // Esconde o formulário
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Nova Senha</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Inter', sans-serif; color: #334155; }
        .card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; border: 1px solid #e2e8f0; }
        .title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 20px; text-align: center; }
        .input-field { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; background: #f8fafc; margin-bottom: 15px; }
        .btn { width: 100%; padding: 14px; background: #4338ca; color: white; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn:hover { background: #3730a3; }
        .alert { padding: 15px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; text-align: center; }
        .alert a { color: #166534; font-weight: 700; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">🔑 Definir Nova Senha</div>

        <?php if($msg): ?>
            <div class="alert" style="<?php echo strpos($msg, 'sucesso') ? 'background:#dcfce7; color:#166534; border-color:#bbf7d0;' : ''; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if($validToken): ?>
            <form method="POST">
                <label style="font-size:0.85rem; font-weight:600; margin-bottom:5px; display:block;">Nova Senha</label>
                <input type="password" name="password" class="input-field" placeholder="No mínimo 6 caracteres" required>
                
                <label style="font-size:0.85rem; font-weight:600; margin-bottom:5px; display:block;">Confirmar Senha</label>
                <input type="password" name="confirm_password" class="input-field" placeholder="Repita a senha" required>
                
                <button type="submit" class="btn">Alterar Senha</button>
            </form>
        <?php elseif(!$msg): ?>
            <div class="alert">Token inválido. <a href="recuperar_senha.php">Tentar novamente</a></div>
        <?php endif; ?>
    </div>
</body>
</html>