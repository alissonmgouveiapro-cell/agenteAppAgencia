<?php
/* Arquivo: /modules/clients/editar_cliente.php */
/* Versão: Com Edição de Foto */

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
if (!isset($_GET['id'])) { header("Location: clientes.php"); exit; }

$client_id = $_GET['id'];
$tenant_id = $_SESSION['tenant_id'];
$erro = '';

// 2. PROCESSAR O SALVAMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $company = $_POST['company_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $group_link = $_POST['group_link'];
    
    // Buscar foto atual para manter ou deletar
    $stmtCurrent = $pdo->prepare("SELECT profile_pic FROM clients WHERE id = ?");
    $stmtCurrent->execute([$client_id]);
    $currentPic = $stmtCurrent->fetchColumn();
    $profile_pic = $currentPic;

    try {
        // Upload Nova Foto
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = '../../uploads/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $newName = uniqid('client_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadDir . $newName)) {
                    // Remove antiga se existir e for diferente
                    if ($currentPic && file_exists($uploadDir . $currentPic)) @unlink($uploadDir . $currentPic);
                    $profile_pic = $newName;
                }
            }
        }

        $stmt = $pdo->prepare("
            UPDATE clients 
            SET name = :name, 
                company_name = :company, 
                email = :email, 
                phone = :phone,
                group_link = :group_link,
                profile_pic = :pic
            WHERE id = :id AND tenant_id = :tenant
        ");
        
        $stmt->execute([
            'name' => $name,
            'company' => $company,
            'email' => $email,
            'phone' => $phone,
            'group_link' => $group_link,
            'pic' => $profile_pic,
            'id' => $client_id,
            'tenant' => $tenant_id
        ]);

        header("Location: clientes.php?msg=sucesso");
        exit;

    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

// 3. BUSCAR DADOS
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = :id AND tenant_id = :tenant");
$stmt->execute(['id' => $client_id, 'tenant' => $tenant_id]);
$cliente = $stmt->fetch();

if (!$cliente) die("Cliente não encontrado.");

$avatarUrl = !empty($cliente['profile_pic']) ? "../../uploads/avatars/" . $cliente['profile_pic'] : "";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="max-width: 600px; margin: 0 auto;">
            
            <a href="clientes.php" style="color: var(--text-muted); text-decoration: none;">&larr; Voltar para Lista</a>
            
            <div class="login-wrapper" style="max-width: 100%; text-align: left; margin-top: 1rem;">
                <h2 style="margin-bottom: 1.5rem;">Editar Cliente</h2>

                <?php if ($erro): ?>
                    <div class="alert" style="background: #fee2e2; color: #b91c1c;">
                        <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group" style="text-align:center; margin-bottom:20px;">
                        <label for="picInput" style="cursor:pointer;">
                            <div style="width:100px; height:100px; background:#e2e8f0; border-radius:50%; margin:0 auto; display:flex; align-items:center; justify-content:center; overflow:hidden; border:2px dashed #cbd5e1; position:relative;">
                                <img id="preview" src="<?php echo $avatarUrl; ?>" style="width:100%; height:100%; object-fit:cover; display:<?php echo $avatarUrl?'block':'none'; ?>;">
                                <span id="placeholder" style="color:#64748b; font-size:2rem; display:<?php echo $avatarUrl?'none':'block'; ?>;">📷</span>
                            </div>
                            <div style="margin-top:10px; font-size:0.85rem; color:#64748b;">Alterar Logo/Foto</div>
                        </label>
                        <input type="file" name="profile_pic" id="picInput" accept="image/*" style="display:none;" onchange="previewImage(this)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nome do Contato</label>
                        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($cliente['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" name="company_name" class="form-input" value="<?php echo htmlspecialchars($cliente['company_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">E-mail Principal</label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Telefone / WhatsApp</label>
                            <input type="text" name="phone" class="form-input" 
                                   placeholder="(00) 00000-0000"
                                   maxlength="15"
                                   onkeyup="mascaraTelefone(this)"
                                   value="<?php echo htmlspecialchars($cliente['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Link do Grupo (WhatsApp)</label>
                            <input type="text" name="group_link" class="form-input" placeholder="https://chat.whatsapp..." value="<?php echo htmlspecialchars($cliente['group_link'] ?? ''); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; margin-top: 1rem;">Salvar Alterações</button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function mascaraTelefone(input) {
    let value = input.value;
    value = value.replace(/\D/g, "");
    value = value.replace(/^(\d{2})(\d)/g, "($1) $2");
    value = value.replace(/(\d)(\d{4})$/, "$1-$2");
    input.value = value;
}
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