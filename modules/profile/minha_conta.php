<?php
/* Arquivo: /modules/profile/minha_conta.php */
/* Versão: Sincronização de Foto (Profile Pic Fix) */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

$user_id = $_SESSION['user_id'];
$msg = '';
$erro = '';

// --- 1. PROCESSAR UPLOAD DE FOTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($ext, $allowed)) {
        $uploadDir = '../../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        // Nome único para evitar cache
        $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $new_name)) {
            
            // ATUALIZAÇÃO NO BANCO (Campo profile_pic é o usado na equipe/radar)
            // Atualizamos 'avatar' também por garantia de compatibilidade legado
            try {
                // Tenta atualizar profile_pic (padrão novo)
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = :a WHERE id = :id");
                $stmt->execute(['a' => $new_name, 'id' => $user_id]);
                
                // Tenta atualizar avatar (padrão antigo, se a coluna existir)
                try {
                    $stmtOld = $pdo->prepare("UPDATE users SET avatar = :a WHERE id = :id");
                    $stmtOld->execute(['a' => $new_name, 'id' => $user_id]);
                } catch (Exception $e) { /* Ignora se não existir coluna avatar */ }

            } catch (Exception $e) {
                $erro = "Erro ao salvar no banco: " . $e->getMessage();
            }
            
            // Atualiza Sessão IMEDIATAMENTE
            $_SESSION['user_avatar'] = $new_name;
            
            header("Location: minha_conta.php?msg=foto_ok"); 
            exit;
        }
    } else {
        $erro = "Formato inválido. Use JPG ou PNG.";
    }
}

// --- 2. PROCESSAR MUDANÇA DE SENHA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass === $confirm_pass) {
        if (strlen($new_pass) >= 6) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
            $stmt->execute(['p' => $hash, 'id' => $user_id]);
            $msg = "Senha alterada com sucesso!";
        } else {
            $erro = "A senha deve ter pelo menos 6 caracteres.";
        }
    } else {
        $erro = "As senhas não coincidem.";
    }
}

// --- 3. PROCESSAR ATUALIZAÇÃO DE DADOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_name'])) {
    $name = trim($_POST['full_name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE users SET name = :n WHERE id = :id");
        $stmt->execute(['n' => $name, 'id' => $user_id]);
        $_SESSION['user_name'] = $name; // Atualiza sessão
        $msg = "Dados atualizados!";
    }
}

// Busca dados atualizados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user = $stmt->fetch();

// Define qual campo de imagem usar (profile_pic tem preferência)
$user_img = !empty($user['profile_pic']) ? $user['profile_pic'] : ($user['avatar'] ?? null);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Minha Conta</title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem; }
        @media(max-width: 800px) { .profile-grid { grid-template-columns: 1fr; } }
        
        .profile-card { background: var(--bg-card, #fff); padding: 2rem; border-radius: 12px; border: 1px solid var(--border-color, #e2e8f0); text-align: center; }
        
        /* Avatar Styling */
        .avatar-container { 
            position: relative; width: 120px; height: 120px; margin: 0 auto 1.5rem auto; 
            border-radius: 50%; overflow: hidden; border: 3px solid var(--bg-body-alt, #f1f5f9); 
            background-color: #e2e8f0; display: flex; align-items: center; justify-content: center;
        }
        .avatar-img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-placeholder { font-size: 3rem; color: #64748b; font-weight: bold; }
        
        .avatar-upload-btn {
            margin-top: -10px; font-size: 0.9rem; color: var(--accent-color, #4338ca); cursor: pointer; text-decoration: underline; font-weight: 600;
        }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid transparent; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <h1 style="margin-bottom: 20px;">Minha Conta</h1>
        
        <?php if ($msg): ?> <div class="alert" style="background:#dcfce7; color:#166534; border-color:#bbf7d0;">✅ <?php echo $msg; ?></div> <?php endif; ?>
        <?php if ($erro): ?> <div class="alert" style="background:#fee2e2; color:#991b1b; border-color:#fecaca;">⚠️ <?php echo $erro; ?></div> <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg']=='foto_ok'): ?> <div class="alert" style="background:#dcfce7; color:#166534; border-color:#bbf7d0;">✅ Foto atualizada com sucesso!</div> <?php endif; ?>

        <div class="profile-grid">
            
            <div class="profile-card">
                <form method="POST" enctype="multipart/form-data" id="photoForm">
                    <div class="avatar-container">
                        <?php if ($user_img && file_exists("../../uploads/avatars/" . $user_img)): ?>
                            <img src="../../uploads/avatars/<?php echo $user_img; ?>?v=<?php echo time(); ?>" class="avatar-img">
                        <?php else: ?>
                            <div class="avatar-placeholder"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <label for="avatarInput" class="avatar-upload-btn">Alterar Foto de Perfil</label>
                    <input type="file" name="avatar" id="avatarInput" style="display: none;" onchange="document.getElementById('photoForm').submit()">
                </form>

                <hr style="margin: 2rem 0; border: 0; border-top: 1px solid var(--border-color);">

                <form method="POST" style="text-align: left;">
                    <div class="form-group">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($user['name']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mail (Login)</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                    </div>
                    <button type="submit" name="update_name" class="btn-primary" style="width: 100%;">Salvar Dados Pessoais</button>
                </form>
            </div>

            <div class="profile-card" style="text-align: left;">
                <h3 style="margin-bottom: 1.5rem;">🔒 Segurança</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Nova Senha</label>
                        <input type="password" name="new_password" class="form-input" placeholder="No mínimo 6 caracteres" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmar Senha</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="Repita a senha" required>
                    </div>
                    <button type="submit" class="btn-primary" style="background: #ef4444; border-color: #ef4444; width: 100%;">Atualizar Senha</button>
                </form>
            </div>

        </div>
    </main>
</div>

</body>
</html>