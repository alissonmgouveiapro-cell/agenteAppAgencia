<?php
/* Arquivo: /modules/team/novo_membro.php */
/* ATUALIZAÇÃO: Links ajustados para equipe.php */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

$tenant_id = $_SESSION['tenant_id'];
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (lógica de validação continua igual) ...
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($name) || empty($email) || empty($password)) {
        $erro = "Preencha todos os campos.";
    } else {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmtCheck->execute(['email' => $email]);
        
        if ($stmtCheck->rowCount() > 0) {
            $erro = "Este e-mail já está em uso.";
        } else {
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password, role) VALUES (:t, :n, :e, :p, :r)");
                $stmt->execute(['t' => $tenant_id, 'n' => $name, 'e' => $email, 'p' => $passHash, 'r' => $role]);

                // AQUI MUDOU: Redireciona para equipe.php
                header("Location: equipe.php?msg=criado");
                exit;

            } catch (PDOException $e) { $erro = "Erro: " . $e->getMessage(); }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Novo Membro</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="max-width: 500px; margin: 0 auto;">
            
            <a href="equipe.php" style="color: var(--text-muted); text-decoration: none; display: block; margin-bottom: 1rem;">&larr; Voltar para Equipe</a>
            
            <div class="login-wrapper" style="max-width: 100%; text-align: left;">
                <h2 style="margin-bottom: 1.5rem;">Adicionar Colaborador</h2>
                
                <?php if ($erro): ?> <div class="alert"><?php echo $erro; ?></div> <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nome</label>
                        <input type="text" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Senha Inicial</label>
                        <input type="text" name="password" class="form-input" value="mudar123" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Permissão</label>
                        <select name="role" class="form-input" style="background: white;">
                            <option value="collaborator">Colaborador</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; margin-top: 1rem;">Criar Acesso</button>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>