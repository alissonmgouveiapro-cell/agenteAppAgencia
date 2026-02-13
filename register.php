<?php
/* Arquivo: register.php */
/* Versão: Correção de Slug (Duplicate Entry Fix) */
session_start();
require 'config/db.php';

$msg = "";

// Função simples para criar slug (Ex: "Minha Agência" vira "minha-agencia-123456")
function generateSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    // Adiciona timestamp para garantir que nunca dê erro de duplicidade
    return $string . '-' . time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name']);
    $user_name = trim($_POST['user_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $invite_code = trim($_POST['invite_code']);

    // 1. VERIFICA O CÓDIGO NO BANCO
    $stmtCode = $pdo->prepare("SELECT id FROM invite_codes WHERE code = ? AND status = 'active'");
    $stmtCode->execute([$invite_code]);
    $codeData = $stmtCode->fetch();

    if (!$codeData) {
        $msg = "🚫 Código de convite inválido ou já utilizado.";
    } 
    elseif (empty($company_name) || empty($user_name) || empty($email) || empty($password)) {
        $msg = "Por favor, preencha todos os campos.";
    } elseif ($password !== $password_confirm) {
        $msg = "As senhas não coincidem.";
    } else {
        // Verifica email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $msg = "Este e-mail já está em uso.";
        } else {
            $pdo->beginTransaction();
            try {
                // A. GERA O SLUG AUTOMÁTICO
                $slug = generateSlug($company_name);

                // B. Cria Empresa (INCLUINDO O SLUG AGORA)
                $stmtTenant = $pdo->prepare("INSERT INTO tenants (name, slug, plan) VALUES (?, ?, 'pro')");
                $stmtTenant->execute([$company_name, $slug]);
                $new_tenant_id = $pdo->lastInsertId();

                // C. Cria Usuário Admin
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password, role) VALUES (?, ?, ?, ?, 'admin')");
                $stmtUser->execute([$new_tenant_id, $user_name, $email, $hash]);

                // D. Configurações
                $pdo->prepare("INSERT INTO system_settings (tenant_id, app_name) VALUES (?, ?)")
                    ->execute([$new_tenant_id, $company_name]);

                // E. QUEIMA O CÓDIGO
                $pdo->prepare("UPDATE invite_codes SET status = 'used', used_by_tenant_id = ? WHERE id = ?")
                    ->execute([$new_tenant_id, $codeData['id']]);

                $pdo->commit();
                header("Location: login.php?registered=1");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "Erro ao criar conta: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar Conta | Bliss OS</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0; padding: 0; background-color: #050505; color: #fff;
            font-family: 'Manrope', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .register-box {
            background: #111; padding: 40px; border-radius: 12px; width: 100%; max-width: 450px;
            border: 1px solid #222;
        }
        h2 { text-align: center; color: #d4af37; text-transform: uppercase; margin:0 0 10px 0; }
        p { text-align: center; color: #666; font-size: 0.9rem; margin-bottom: 30px; }
        .input-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-size: 0.85rem; color: #aaa; }
        input {
            width: 100%; padding: 12px; background: #222; border: 1px solid #333; color: #fff; border-radius: 6px; box-sizing: border-box;
        }
        input:focus { border-color: #d4af37; }
        .btn-register {
            width: 100%; padding: 15px; background: #d4af37; color: #000; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px;
        }
        .alert { background: #fee2e2; color: #ef4444; padding: 10px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>

<div class="register-box">
    <h2>Nova Conta</h2>
    <p>Insira seu código de acesso para ativar.</p>

    <?php if(!empty($msg)): ?>
        <div class="alert"><?php echo $msg; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label style="color: #d4af37; font-weight:bold;">🔑 Código de Convite</label>
            <input type="text" name="invite_code" required placeholder="Insira o código recebido">
        </div>

        <div class="input-group">
            <label>Nome da Agência</label>
            <input type="text" name="company_name" required>
        </div>

        <div class="input-group">
            <label>Seu Nome</label>
            <input type="text" name="user_name" required>
        </div>

        <div class="input-group">
            <label>E-mail</label>
            <input type="email" name="email" required>
        </div>

        <div style="display: flex; gap: 10px;">
            <div class="input-group" style="flex:1;">
                <label>Senha</label>
                <input type="password" name="password" required>
            </div>
            <div class="input-group" style="flex:1;">
                <label>Confirmar</label>
                <input type="password" name="password_confirm" required>
            </div>
        </div>

        <button type="submit" class="btn-register">ATIVAR CONTA</button>
    </form>
    
    <div style="text-align:center; margin-top:20px;">
        <a href="login.php" style="color:#666; text-decoration:none;">Voltar ao Login</a>
    </div>
</div>

</body>
</html>