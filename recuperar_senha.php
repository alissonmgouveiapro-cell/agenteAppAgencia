<?php
/* Arquivo: recuperar_senha.php */
/* Versão: Com envio REAL de E-mail via SMTP */

session_start();
require 'config/db.php';

// IMPORTANTE: Incluir o disparador de e-mail que criamos
// Se o arquivo estiver em outra pasta, ajuste o caminho.
require 'includes/mailer.php'; 

$msg = '';
$msgType = ''; // success ou error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Verifica se email existe
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Gera um token único e validade de 1 hora
        $token = bin2hex(random_bytes(50));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Salva no banco
        $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?")->execute([$token, $expires, $email]);

        // Link de recuperação
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $link = $base_url . "/nova_senha.php?token=" . $token;

        // --- MONTAGEM DO E-MAIL (TEMPLATE HTML) ---
        $assunto = "Redefinir Senha - Bliss OS";
        $corpoEmail = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; color: #333;'>
            <h2 style='color: #4338ca;'>Recuperação de Senha</h2>
            <p>Olá, <strong>{$user['name']}</strong>.</p>
            <p>Recebemos uma solicitação para redefinir sua senha.</p>
            <p>Clique no botão abaixo para criar uma nova senha:</p>
            <br>
            <a href='$link' style='background: #4338ca; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>Redefinir Minha Senha</a>
            <br><br>
            <p style='font-size: 12px; color: #666;'>Se você não solicitou isso, ignore este e-mail. O link expira em 1 hora.</p>
        </div>
        ";

        // --- ENVIO REAL ---
        $envio = enviarEmail($email, $user['name'], $assunto, $corpoEmail);

        if ($envio['status']) {
            $msg = "✅ E-mail enviado! Verifique sua caixa de entrada (e spam).";
            $msgType = 'success';
        } else {
            $msg = "❌ Erro ao enviar: " . $envio['error'];
            $msgType = 'error';
        }

    } else {
        // Por segurança, mostramos msg genérica ou de erro (aqui mantive a sua lógica)
        $msg = "E-mail não encontrado no sistema.";
        $msgType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Senha</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Inter', sans-serif; color: #334155; }
        .card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; border: 1px solid #e2e8f0; }
        .title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 10px; text-align: center; }
        .text { font-size: 0.9rem; color: #64748b; margin-bottom: 25px; text-align: center; }
        .input-field { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; background: #f8fafc; display: block; margin-bottom: 20px; box-sizing: border-box; }
        .btn { width: 100%; padding: 14px; background: #4338ca; color: white; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #3730a3; }
        .alert { padding: 15px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 20px; word-break: break-all; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #64748b; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { color: #4338ca; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">🔐 Recuperar Senha</div>
        <p class="text">Digite seu e-mail para receber o link de redefinição.</p>

        <?php if($msg): ?>
            <div class="alert <?php echo $msgType; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <form method="POST">
            <label style="font-size:0.85rem; font-weight:600; margin-bottom:5px; display:block;">E-mail</label>
            <input type="email" name="email" class="input-field" required placeholder="seu@email.com">
            <button type="submit" class="btn">Enviar Link</button>
        </form>

        <a href="login.php" class="back-link">&larr; Voltar para Login</a>
    </div>
</body>
</html>