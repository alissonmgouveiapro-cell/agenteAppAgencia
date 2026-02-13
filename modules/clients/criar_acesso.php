<?php
/* Arquivo: /modules/clients/criar_acesso.php */
/* Função: Criar um login para um cliente existente */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
if (!isset($_GET['client_id'])) { header("Location: clientes.php"); exit; }

$client_id = $_GET['client_id'];
$tenant_id = $_SESSION['tenant_id'];
$erro = '';

// 1. Busca dados do cliente para preencher o form
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id AND tenant_id = :t");
$stmt->execute(['id' => $client_id, 't' => $tenant_id]);
$cliente = $stmt->fetch();

if (!$cliente) die("Cliente não encontrado.");

// 2. Processa Criação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $name = $_POST['name'];

    // Verifica se email já existe
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmtCheck->execute(['email' => $email]);

    if ($stmtCheck->rowCount() > 0) {
        $erro = "Este e-mail já possui cadastro no sistema.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        // INSERE USUÁRIO VINCULADO AO CLIENTE (related_client_id)
        $stmtInsert = $pdo->prepare("
            INSERT INTO users (tenant_id, related_client_id, name, email, password, role) 
            VALUES (:t, :cid, :n, :e, :p, 'client')
        ");
        
        try {
            $stmtInsert->execute([
                't' => $tenant_id,
                'cid' => $client_id,
                'n' => $name,
                'e' => $email,
                'p' => $hash
            ]);
            header("Location: clientes.php?msg=acesso_criado"); exit;
        } catch (PDOException $e) {
            $erro = "Erro: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar Acesso Cliente</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <div style="max-width: 500px; margin: 0 auto;">
            <a href="clientes.php" style="color: var(--text-muted); text-decoration: none;">&larr; Voltar</a>
            
            <div class="login-wrapper" style="max-width: 100%; text-align: left; margin-top: 1rem;">
                <h2>Criar Acesso para: <?php echo htmlspecialchars($cliente['name']); ?></h2>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">O cliente usará este login para aprovar projetos.</p>
                
                <?php if ($erro): ?> <div class="alert"><?php echo $erro; ?></div> <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nome do Usuário</label>
                        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($cliente['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mail de Login</label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($cliente['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Definir Senha</label>
                        <input type="text" name="password" class="form-input" value="mudar123" required>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%;">Gerar Acesso</button>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>