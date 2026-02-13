<?php
/* Arquivo: /modules/clients/novo_cliente.php */
/* Versão: Com Upload de Foto + Auto-Migração */

session_start();
require '../../config/db.php';

// Segurança: Apenas logados
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

// --- 1. AUTO-MIGRAÇÃO (Adiciona coluna profile_pic se não existir) ---
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM clients LIKE 'profile_pic'");
    if ($checkCol->rowCount() == 0) {
        $pdo->exec("ALTER TABLE clients ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) { }

// --- 2. PROCESSAMENTO DO FORMULÁRIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['name'];
    $empresa = $_POST['company_name'];
    $email = $_POST['email'];
    $tenant_id = $_SESSION['tenant_id'];
    $profile_pic = null;

    if (!empty($nome) && !empty($email)) {
        try {
            // Upload da Imagem
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
                $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = '../../uploads/avatars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $newName = uniqid('client_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadDir . $newName)) {
                        $profile_pic = $newName;
                    }
                }
            }

            $stmt = $pdo->prepare("INSERT INTO clients (tenant_id, name, company_name, email, profile_pic) VALUES (:tenant_id, :name, :company, :email, :pic)");
            $stmt->execute([
                'tenant_id' => $tenant_id,
                'name' => $nome,
                'company' => $empresa,
                'email' => $email,
                'pic' => $profile_pic
            ]);

            header("Location: clientes.php?msg=sucesso");
            exit;

        } catch (PDOException $e) {
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha os campos obrigatórios.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Novo Cliente - Agência OS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="max-width: 600px; margin: 0 auto;">
            
            <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                <a href="clientes.php" style="color: var(--text-muted); text-decoration: none;">&larr; Voltar</a>
                <h1>Cadastrar Novo Cliente</h1>
            </div>

            <div class="login-wrapper" style="max-width: 100%; text-align: left;">
                
                <?php if (isset($erro)): ?>
                    <div class="alert"><?php echo $erro; ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group" style="text-align:center; margin-bottom:20px;">
                        <label for="picInput" style="cursor:pointer;">
                            <div style="width:100px; height:100px; background:#e2e8f0; border-radius:50%; margin:0 auto; display:flex; align-items:center; justify-content:center; overflow:hidden; border:2px dashed #cbd5e1;">
                                <img id="preview" src="" style="width:100%; height:100%; object-fit:cover; display:none;">
                                <span id="placeholder" style="color:#64748b; font-size:2rem;">📷</span>
                            </div>
                            <div style="margin-top:10px; font-size:0.85rem; color:#64748b;">Adicionar Logo/Foto</div>
                        </label>
                        <input type="file" name="profile_pic" id="picInput" accept="image/*" style="display:none;" onchange="previewImage(this)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nome do Contato *</label>
                        <input type="text" name="name" class="form-input" placeholder="Ex: Ana Silva" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" name="company_name" class="form-input" placeholder="Ex: Tech Solutions Ltda">
                    </div>

                    <div class="form-group">
                        <label class="form-label">E-mail Principal *</label>
                        <input type="email" name="email" class="form-input" placeholder="contato@empresa.com" required>
                    </div>

                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn-primary">Salvar Cliente</button>
                        <a href="clientes.php" class="btn-primary" style="background: #e2e8f0; color: #334155; text-align: center; text-decoration: none;">Cancelar</a>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview').src = e.target.result;
            document.getElementById('preview').style.display = 'block';
            document.getElementById('placeholder').style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>