<?php
/* Arquivo: /core/auth_login.php */
/* Função: Processar login, criar sessão e redirecionar para a área correta */

session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        header('Location: ../login.php?erro=vazio');
        exit;
    }

    try {
        // Busca o usuário no banco pelo e-mail
        // Trazemos também o 'avatar' e o 'related_client_id' para a sessão
        $stmt = $pdo->prepare("
            SELECT u.*, t.name as tenant_name 
            FROM users u
            JOIN tenants t ON u.tenant_id = t.id
            WHERE u.email = :email
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Verifica se usuário existe e se a senha bate
        if ($user && password_verify($password, $user['password'])) {
            
            // --- CRIAÇÃO DA SESSÃO (SESSION) ---
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            
            // Dados da Agência (Tenant)
            $_SESSION['tenant_id'] = $user['tenant_id'];
            $_SESSION['tenant_name'] = $user['tenant_name'];
            
            // Dados de Permissão
            $_SESSION['role'] = $user['role'];
            
            // Dados Visuais (Foto de Perfil)
            $_SESSION['user_avatar'] = $user['avatar']; 

            // Dados de Cliente Externo (Se for acesso de cliente)
            $_SESSION['related_client_id'] = $user['related_client_id'];

            // --- REDIRECIONAMENTO INTELIGENTE ---
            
            if ($user['role'] === 'client') {
                // Se for CLIENTE, vai para o Portal Restrito (arquivo renomeado)
                header('Location: ../modules/portal/portal.php');
            } else {
                // Se for EQUIPE (Dono, Admin, Colaborador), vai para o Dashboard Geral
                header('Location: ../index.php');
            }
            exit;

        } else {
            // Login falhou (senha ou usuário incorretos)
            header('Location: ../login.php?erro=login_invalido');
            exit;
        }

    } catch (PDOException $e) {
        die("Erro no sistema: " . $e->getMessage());
    }

} else {
    // Se tentar acessar o arquivo direto sem ser POST
    header('Location: ../login.php');
    exit;
}
?>