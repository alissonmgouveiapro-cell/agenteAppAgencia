<?php
/* Arquivo: login.php */
/* Versão: Upgrade com "Manter Conectado" (Cookies Seguros) */

session_start();
require 'config/db.php';

// --- SEGURANÇA: Chave para assinar o cookie (Pode mudar para algo aleatório) ---
define('COOKIE_SECRET', 'bliss_os_secret_key_2024');

// --- 1. LÓGICA DE AUTO-LOGIN (Verifica Cookie) ---
if (!isset($_SESSION['user_id']) && isset($_COOKIE['bliss_remember'])) {
    list($user_id, $token) = explode(':', $_COOKIE['bliss_remember']);
    
    // Busca o usuário baseado no ID do cookie
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Recria o hash esperado para validar se o cookie é legítimo e se a senha não mudou
        $expected_token = md5($user['password'] . COOKIE_SECRET);
        
        if ($token === $expected_token) {
            // Cookie Válido! Recria a sessão (Login Automático)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_avatar'] = $user['profile_pic'] ?? null;
            $_SESSION['tenant_id'] = $user['tenant_id'];
            $_SESSION['role'] = $user['role'];

            // Busca Tenant
            $stmtT = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmtT->execute([$user['tenant_id']]);
            $_SESSION['tenant_name'] = $stmtT->fetchColumn();
            
            // Redireciona
            header("Location: index.php");
            exit;
        }
    }
}

// Se já estiver logado (por sessão ou cookie recém validado), redireciona
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$erro = "";

// --- 2. PROCESSAMENTO DO FORMULÁRIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']); // Verifica se o checkbox foi marcado

    if (empty($email) || empty($password)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            
            // Sessão Padrão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_avatar'] = $user['profile_pic'] ?? null;
            $_SESSION['tenant_id'] = $user['tenant_id'];
            $_SESSION['role'] = $user['role'];

            $stmtT = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmtT->execute([$user['tenant_id']]);
            $_SESSION['tenant_name'] = $stmtT->fetchColumn();

            // --- CRIAÇÃO DO COOKIE "MANTER CONECTADO" ---
            if ($remember) {
                // Token = Hash da senha atual + Sal (Se a senha mudar, o cookie invalida sozinho)
                $token = md5($user['password'] . COOKIE_SECRET);
                $cookieValue = $user['id'] . ':' . $token;
                
                // Salva por 30 dias (86400 segundos * 30)
                setcookie('bliss_remember', $cookieValue, time() + (86400 * 30), "/", "", false, true); 
            }

            // Redirecionamento
            if ($user['role'] === 'client') {
                $_SESSION['related_client_id'] = $user['client_id'] ?? 0; 
                header("Location: modules/portal/portal.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $erro = "E-mail ou senha incorretos.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bliss OS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* CSS Extra para o Checkbox e Link */
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        .remember-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            gap: 8px;
        }
        .remember-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--accent-color, #4338ca);
        }
        .forgot-link {
            color: #4338ca;
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-screen">
    
    <div class="login-visual">
        <div class="visual-content">
            <div class="visual-quote">"A simplicidade é o último grau de sofisticação."</div>
            <div style="font-size: 0.9rem; opacity: 0.8;">Bliss OS &copy; <?php echo date('Y'); ?></div>
        </div>
    </div>

    <div class="login-form-wrapper">
        <div class="login-box">
            
            <div style="margin-bottom: 2rem;">
                <h1 class="login-title">Bem-vindo</h1>
                <p class="login-subtitle">Entre com suas credenciais para acessar o painel.</p>
            </div>

            <?php if(!empty($erro)): ?>
                <div style="background: #fef2f2; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 20px; border: 1px solid #fecaca;">
                    ⚠️ <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                
                <div class="login-input-group">
                    <label class="form-label">E-mail Corporativo</label>
                    <input type="email" name="email" class="form-input" placeholder="seu@email.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="login-input-group">
                    <label class="form-label">Senha</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>

                <div class="login-options">
                    <label class="remember-label">
                        <input type="checkbox" name="remember">
                        Manter conectado
                    </label>
                    <a href="#" class="forgot-link" onclick="alert('Entre em contato com o administrador para resetar sua senha.')">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="login-btn">Entrar na Plataforma</button>

                <div style="text-align: center; margin-top: 20px; font-size: 0.9rem; color: #666;">
                    Não tem uma conta? <a href="register.php" style="color: #4338ca; text-decoration: none; font-weight:bold;">Cadastre sua Agência</a>
                </div>
            </form>

        </div>
    </div>

</div>

<script>
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
</script>

</body>
</html>