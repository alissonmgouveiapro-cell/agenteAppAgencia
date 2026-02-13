<?php
/* Arquivo: /modules/team/equipe.php */
/* Versão: Senha Padrão 123456 Fixa */

session_start();
require '../../config/db.php';

// --- Carrega o disparador de emails (Opcional agora) ---
if (file_exists('../../includes/mailer.php')) {
    require_once '../../includes/mailer.php';
}

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

$tenant_id = $_SESSION['tenant_id'];
$current_user_id = $_SESSION['user_id'];

// --- VERIFICA PERMISSÃO ---
$stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmtRole->execute([$current_user_id]);
$currentUserRole = $stmtRole->fetchColumn();
$isAdmin = ($currentUserRole === 'admin');

// --- PROCESSAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_member'])) {
        $user_id = $_POST['user_id'] ?? '';
        $name = trim($_POST['name']); 
        $email = trim($_POST['email']);
        $custom_title = trim($_POST['custom_title']); 

        // 1. SEGURANÇA DE PERMISSÃO
        if (!$isAdmin) {
            if (empty($user_id) || $user_id != $current_user_id) {
                die("Acesso negado.");
            }
        }

        // 2. VERIFICA SE O EMAIL JÁ EXISTE
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmtCheck->execute([$email, $user_id]); 
        if ($stmtCheck->rowCount() > 0) {
            header("Location: equipe.php?msg=erro_email");
            exit;
        }

        // 3. Lógica de Role
        if ($isAdmin) {
            $role = $_POST['role'];
        } else {
            $stmtR = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtR->execute([$user_id]);
            $role = $stmtR->fetchColumn();
        }

        if(empty($custom_title)) $custom_title = ($role === 'admin') ? 'Administrador' : 'Colaborador';

        // 4. Upload de Avatar
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $uploadDir = '../../uploads/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $newName = uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $newName)) $avatarPath = $newName;
            }
        }

        try {
            if (!empty($user_id)) {
                // UPDATE
                $sql = "UPDATE users SET name=?, email=?, role=?, custom_title=?"; 
                $params = [$name, $email, $role, $custom_title];
                
                if ($avatarPath) { $sql .= ", profile_pic=?"; $params[] = $avatarPath; }
                
                $sql .= " WHERE id=? AND tenant_id=?"; 
                $params[] = $user_id; 
                $params[] = $tenant_id;
                
                $pdo->prepare($sql)->execute($params);
                
                if ($user_id == $current_user_id) { 
                    $_SESSION['user_name'] = $name; 
                    if ($avatarPath) $_SESSION['user_avatar'] = $avatarPath; 
                }
                $msgCode = "updated";

            } else {
                // INSERT (Novo)
                
                // --- CORREÇÃO AQUI: SENHA FIXA 123456 ---
                $rawPassword = "123456"; 
                
                // Cria o Hash seguro dessa senha
                $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password, role, custom_title, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$tenant_id, $name, $email, $passwordHash, $role, $custom_title, $avatarPath])) {
                    // Tenta enviar email, mas se falhar, a senha é 123456
                    if (function_exists('enviarEmailAcesso')) {
                        @enviarEmailAcesso($email, $name, $rawPassword);
                    }
                }
                $msgCode = "created";
            }
            
            header("Location: equipe.php?msg=" . $msgCode);
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                header("Location: equipe.php?msg=erro_email");
            } else {
                header("Location: equipe.php?msg=erro_db");
            }
            exit;
        }
    }
}

// DELETE
if (isset($_GET['del_user'])) {
    if (!$isAdmin) { die("Acesso negado."); }
    $uid = $_GET['del_user'];
    if ($uid != $current_user_id) $pdo->prepare("DELETE FROM users WHERE id=? AND tenant_id=?")->execute([$uid, $tenant_id]);
    header("Location: equipe.php?msg=deleted"); exit;
}

// BUSCAS
$stmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = ? ORDER BY name ASC");
$stmt->execute([$tenant_id]);
$members = $stmt->fetchAll();

$rolesStmt = $pdo->prepare("SELECT DISTINCT custom_title FROM users WHERE tenant_id = ? AND custom_title IS NOT NULL AND custom_title != ''");
$rolesStmt->execute([$tenant_id]);
$dbRoles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
$allRoles = array_unique(array_merge(['Head de Projetos', 'Social Media', 'Designer', 'Editor de Vídeo', 'Copywriter', 'Tráfego'], $dbRoles));
sort($allRoles);

function getInitials($name) { $parts = explode(' ', trim($name)); $ret = strtoupper($parts[0][0]); if (count($parts)>1) $ret .= strtoupper(end($parts)[0]); return $ret; }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Equipe</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        :root {
            --bg-card: #ffffff; --bg-body-alt: #f8fafc; --text-main: #333333; --text-muted: #64748b; --border-color: #e2e8f0; --input-bg: #ffffff;
        }
        [data-theme="dark"] {
            --bg-card: #27272a; --bg-body-alt: #18181b; --text-main: #f4f4f5; --text-muted: #a1a1aa; --border-color: #3f3f46; --input-bg: #27272a;
        }

        body { color: var(--text-main); }
        h1, h2, h3 { color: var(--text-main); }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; text-align: center; border: 1px solid transparent; }
        .alert-error { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
        .alert-success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        
        .member-card {
            background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-main);
            border-radius: 8px; padding: 20px; display: flex; flex-direction: column; align-items: center;
            text-align: center; transition: 0.2s;
        }
        
        .team-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }

        .member-avatar {
            width: 80px; height: 80px; border-radius: 50%; background: #e2e8f0; color: #64748b;
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold; margin-bottom: 15px;
        }
        .member-avatar-img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; }
        
        .member-name { color: var(--text-main); font-weight: bold; font-size: 1.1rem; }
        .member-email { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 10px; }
        
        .role-badge {
            display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px;
        }
        .role-admin { background: #fee2e2; color: #ef4444; }
        .role-head { background: #e0e7ff; color: #4338ca; }
        .role-video { background: #ffedd5; color: #c2410c; }
        .role-design { background: #fce7f3; color: #be185d; }
        .role-social { background: #dcfce7; color: #15803d; }

        .member-actions { display: flex; gap: 10px; justify-content: center; margin-top: auto; }
        .btn-member-action { background: none; border: none; cursor: pointer; font-size: 0.85rem; padding: 5px 10px; border-radius: 4px; transition: 0.2s; }
        .btn-member-action:hover { background: var(--bg-body-alt); }
        .btn-member-delete { color: #ef4444; text-decoration: none; }
        
        .status-modal-card { background: var(--bg-card); color: var(--text-main); border-radius: 8px; overflow: hidden; }
        .st-modal-header { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }

        .form-input {
            background-color: var(--input-bg) !important; color: var(--text-main) !important;
            border: 1px solid var(--border-color) !important; width: 100%; padding: 10px; border-radius: 6px;
        }
        .form-input:disabled { opacity: 0.6; cursor: not-allowed; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); }

        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000;
            display: none; align-items: center; justify-content: center; backdrop-filter: blur(3px);
        }
        #avatarPreview { background: var(--bg-body-alt) !important; border-color: var(--border-color) !important; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        
        <div class="page-header">
            <div>
                <h1 style="margin:0; font-size:1.8rem;">Minha Equipe</h1>
                <p style="color:var(--text-muted); margin-top:5px;">Gerencie os membros e permissões.</p>
            </div>
            <?php if($isAdmin): ?>
                <button onclick="openMemberModal()" class="btn-primary" style="height:45px;">+ Novo Membro</button>
            <?php endif; ?>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <?php 
                $m = $_GET['msg'];
                if($m == 'erro_email') echo '<div class="alert-box alert-error">❌ Este e-mail já está sendo usado!</div>';
                if($m == 'erro_db') echo '<div class="alert-box alert-error">❌ Erro no banco de dados.</div>';
                if($m == 'created') echo '<div class="alert-box alert-success">✅ Membro criado com sucesso! Senha padrão: 123456</div>';
                if($m == 'updated') echo '<div class="alert-box alert-success">✅ Dados atualizados com sucesso.</div>';
                if($m == 'deleted') echo '<div class="alert-box alert-success">🗑️ Membro removido.</div>';
            ?>
        <?php endif; ?>

        <div class="team-grid">
            <?php foreach($members as $m): 
                $displayRole = !empty($m['custom_title']) ? $m['custom_title'] : ucfirst($m['role']);
                $roleClass = 'role-head'; 
                if($m['role'] == 'admin') $roleClass = 'role-admin';
                $txt = mb_strtolower($displayRole);
                if(strpos($txt, 'vídeo')!==false || strpos($txt, 'video')!==false) $roleClass = 'role-video'; 
                if(strpos($txt, 'design')!==false || strpos($txt, 'arte')!==false) $roleClass = 'role-design'; 
                if(strpos($txt, 'social')!==false || strpos($txt, 'mídia')!==false) $roleClass = 'role-social'; 
                
                $isMe = ($m['id'] == $current_user_id);
                $canEdit = $isAdmin || $isMe; 
                $canDelete = $isAdmin && !$isMe; 
            ?>
                <div class="member-card">
                    <?php if (!empty($m['profile_pic']) && file_exists("../../uploads/avatars/" . $m['profile_pic'])): ?>
                        <img src="../../uploads/avatars/<?php echo $m['profile_pic']; ?>?v=<?php echo time(); ?>" class="member-avatar-img">
                    <?php else: ?>
                        <div class="member-avatar"><?php echo getInitials($m['name']); ?></div>
                    <?php endif; ?>
                    
                    <div class="member-name"><?php echo htmlspecialchars($m['name']); ?></div>
                    <div class="member-email"><?php echo htmlspecialchars($m['email']); ?></div>
                    
                    <span class="role-badge <?php echo $roleClass; ?>"><?php echo htmlspecialchars($displayRole); ?></span>

                    <div class="member-actions">
                        <?php if($canEdit): ?>
                            <button onclick='editMember(<?php echo htmlspecialchars(json_encode($m)); ?>, <?php echo $isAdmin ? "true" : "false"; ?>)' class="btn-member-action" style="color:var(--text-muted);">✏️ Editar</button>
                        <?php endif; ?>
                        
                        <?php if($canDelete): ?>
                            <a href="?del_user=<?php echo $m['id']; ?>" class="btn-member-action btn-member-delete" onclick="return confirm('Remover?')">🗑️ Excluir</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<div id="memberModal" class="modal-overlay">
    <div class="status-modal-card" style="max-width: 500px; width: 90%;">
        <div class="st-modal-header"><h3 id="modalTitle" style="margin:0;">Novo Membro</h3><button onclick="closeMemberModal()" style="border:none; background:none; cursor:pointer; font-size:1.5rem; color:var(--text-main);">×</button></div>
        <form method="POST" enctype="multipart/form-data" style="padding: 2rem;">
            <input type="hidden" name="save_member" value="1"><input type="hidden" name="user_id" id="userId">
            <div style="display:flex; justify-content:center; margin-bottom:1.5rem;">
                <label style="cursor:pointer; text-align:center;">
                    <div id="avatarPreview" style="width:80px; height:80px; border-radius:50%; background:var(--bg-body-alt); display:flex; align-items:center; justify-content:center; border:2px dashed var(--border-color); overflow:hidden; margin: 0 auto;">
                        <span style="font-size:2rem; color:var(--text-muted);">📷</span>
                    </div>
                    <span style="font-size:0.8rem; color:var(--accent-color); margin-top:5px; display:block; font-weight:600;">Alterar Foto</span>
                    <input type="file" name="avatar" style="display:none;" onchange="previewImage(this)">
                </label>
            </div>
            <div class="form-group"><label class="form-label">Nome Completo</label><input type="text" name="name" id="userName" class="form-input" required></div>
            <div class="form-group"><label class="form-label">E-mail</label><input type="email" name="email" id="userEmail" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Cargo</label><input type="text" name="custom_title" id="userCustomTitle" class="form-input" list="rolesList" placeholder="Selecione..."><datalist id="rolesList"><?php foreach($allRoles as $role): ?><option value="<?php echo htmlspecialchars($role); ?>"><?php endforeach; ?></datalist></div>
            
            <div class="form-group" style="margin-bottom:2rem;">
                <label class="form-label">Permissão</label>
                <select name="role" id="userRole" class="form-input" required>
                    <option value="user">Colaborador</option>
                    <option value="admin">Administrador</option>
                </select>
                <small id="roleWarning" style="display:none; color:var(--text-muted); font-size:0.8rem;">(Apenas administradores alteram permissões)</small>
            </div>
            
            <button type="submit" class="btn-primary" style="width:100%; height:50px;">Salvar</button>
        </form>
    </div>
</div>

<script>
function openMemberModal() { 
    document.getElementById('modalTitle').innerText='Novo Membro'; 
    document.getElementById('userId').value=''; 
    document.getElementById('userName').value=''; 
    document.getElementById('userEmail').value=''; 
    document.getElementById('userCustomTitle').value=''; 
    document.getElementById('userRole').value='user'; 
    
    document.getElementById('userRole').disabled = false;
    document.getElementById('roleWarning').style.display = 'none';

    resetPreview(); 
    document.getElementById('memberModal').style.display='flex'; 
}

function editMember(d, isAdmin) { 
    document.getElementById('modalTitle').innerText='Editar Membro'; 
    document.getElementById('userId').value=d.id; 
    document.getElementById('userName').value=d.name; 
    document.getElementById('userEmail').value=d.email; 
    document.getElementById('userCustomTitle').value=d.custom_title||''; 
    document.getElementById('userRole').value=(d.role==='admin')?'admin':'user'; 
    
    if (isAdmin) {
        document.getElementById('userRole').disabled = false;
        document.getElementById('roleWarning').style.display = 'none';
    } else {
        document.getElementById('userRole').disabled = true;
        document.getElementById('roleWarning').style.display = 'block';
    }

    if(d.profile_pic){ 
        document.getElementById('avatarPreview').innerHTML=`<img src="../../uploads/avatars/${d.profile_pic}" style="width:100%; height:100%; object-fit:cover;">`; 
    } else { 
        resetPreview(); 
    } 
    document.getElementById('memberModal').style.display='flex'; 
}

function closeMemberModal() { document.getElementById('memberModal').style.display='none'; }
function resetPreview() { document.getElementById('avatarPreview').innerHTML='<span style="font-size:2rem; color:var(--text-muted);">📷</span>'; }
function previewImage(i) { if(i.files&&i.files[0]){ var r=new FileReader(); r.onload=function(e){document.getElementById('avatarPreview').innerHTML=`<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">`;}; r.readAsDataURL(i.files[0]); } }
</script>
</body>
</html>