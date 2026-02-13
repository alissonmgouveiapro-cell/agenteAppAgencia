<?php
/* Arquivo: modules/admin/convites.php */
/* Versão: Painel Mestre (Com Suspender/Ativar Usuário) */

session_start();
require '../../config/db.php';

// 1. SEGURANÇA
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header("Location: acesso.php"); exit;
}

// --- AUTO-MIGRAÇÃO (Cria coluna status se não existir) ---
try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
    }
} catch (Exception $e) { }

$msg = "";

// --- AÇÕES DE USUÁRIO ---

// 1. Alternar Status (Suspender/Ativar)
if (isset($_GET['toggle_user'])) {
    $uid = $_GET['toggle_user'];
    $current_status = $_GET['st']; // 'active' ou 'inactive'
    
    $new_status = ($current_status === 'active') ? 'inactive' : 'active';
    
    $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $uid]);
    
    // Feedback
    $acao = ($new_status === 'active') ? 'reativado' : 'suspenso';
    header("Location: convites.php?tab=users&msg=user_$acao"); exit;
}

// 2. Alterar Senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $uid = $_POST['user_id']; $new_pass = $_POST['new_password'];
    if (!empty($new_pass)) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
        $msg = "✅ Senha alterada com sucesso!";
    }
}

// 3. Excluir Tenant
if (isset($_GET['del_tenant'])) {
    $tid = $_GET['del_tenant'];
    $pdo->prepare("DELETE FROM users WHERE tenant_id = ?")->execute([$tid]);
    $pdo->prepare("DELETE FROM tenants WHERE id = ?")->execute([$tid]);
    header("Location: convites.php?tab=users"); exit;
}

// --- AÇÕES ADMINS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $new_email = trim($_POST['admin_email']);
    if (filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $check = $pdo->prepare("SELECT id FROM super_admins WHERE email = ?"); $check->execute([$new_email]);
        if($check->rowCount() > 0) { $msg = "⚠️ E-mail já é Admin."; } 
        else { $pdo->prepare("INSERT INTO super_admins (email) VALUES (?)")->execute([$new_email]); $msg = "✅ Admin adicionado!"; }
    }
}
if (isset($_GET['del_admin'])) {
    $id = $_GET['del_admin'];
    $stmtMe = $pdo->prepare("SELECT email FROM super_admins WHERE id = ?"); $stmtMe->execute([$id]); $targetEmail = $stmtMe->fetchColumn();
    if ($targetEmail == $_SESSION['user_email']) { $msg = "❌ Você não pode se excluir."; } 
    else { $pdo->prepare("DELETE FROM super_admins WHERE id = ?")->execute([$id]); header("Location: convites.php?tab=admins"); exit; }
}

// --- AÇÕES CHAVES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $custom = trim($_POST['custom_code']);
    $code = !empty($custom) ? strtoupper($custom) : 'BLISS-' . strtoupper(substr(md5(uniqid()), 0, 4) . '-' . substr(md5(time()), 0, 4));
    $check = $pdo->prepare("SELECT id FROM invite_codes WHERE code = ?"); $check->execute([$code]);
    if ($check->rowCount() > 0) { $msg = "❌ Código já existe."; } 
    else { $pdo->prepare("INSERT INTO invite_codes (code) VALUES (?)")->execute([$code]); $msg = "✅ Código <b>$code</b> criado!"; }
}
if (isset($_GET['del_code'])) {
    $pdo->prepare("DELETE FROM invite_codes WHERE id = ?")->execute([$_GET['del_code']]);
    header("Location: convites.php"); exit;
}
if (isset($_GET['logout_admin'])) { unset($_SESSION['is_super_admin']); header("Location: ../../index.php"); exit; }

// --- BUSCAS ---
$codes = $pdo->query("SELECT * FROM invite_codes ORDER BY created_at DESC")->fetchAll();

// Busca usuários com o status
$users = $pdo->query("
    SELECT u.id, u.name, u.email, u.status, t.name as company, t.id as tenant_id, u.created_at 
    FROM users u 
    JOIN tenants t ON u.tenant_id = t.id 
    WHERE u.role = 'admin' 
    ORDER BY u.created_at DESC
")->fetchAll();

$admins = $pdo->query("SELECT * FROM super_admins ORDER BY added_at DESC")->fetchAll();
$tab = $_GET['tab'] ?? 'keys';

// Mensagens de feedback via GET
if(isset($_GET['msg'])) {
    if($_GET['msg'] == 'user_suspenso') $msg = "⏸️ Usuário suspenso. Ele não poderá mais logar.";
    if($_GET['msg'] == 'user_reativado') $msg = "▶️ Usuário reativado com sucesso!";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Mestre | Bliss OS</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #d4af37; --dark: #050505; --card: #111; --border: #222; }
        body { background-color: var(--dark); color: #fff; font-family: 'Manrope', sans-serif; margin: 0; padding: 40px; display: flex; flex-direction: column; align-items: center; min-height: 100vh; }
        .container { width: 100%; max-width: 1100px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .brand { font-size: 1.5rem; color: var(--accent); font-weight: bold; text-transform: uppercase; letter-spacing: 2px; }
        .btn-exit { color: #ef4444; text-decoration: none; border: 1px solid #ef4444; padding: 8px 15px; border-radius: 4px; transition:0.3s; font-size: 0.8rem; }
        .btn-exit:hover { background: #ef4444; color: #fff; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn { background: var(--card); color: #666; border: 1px solid var(--border); padding: 12px 25px; cursor: pointer; font-weight: bold; border-radius: 8px; flex: 1; text-align: center; text-decoration: none; transition: 0.2s; min-width: 150px; }
        .tab-btn.active { background: var(--accent); color: #000; border-color: var(--accent); }
        .tab-btn:hover:not(.active) { color: #fff; border-color: #444; }

        .card { background: var(--card); padding: 30px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); }
        h3 { margin-top: 0; color: #fff; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; font-size: 1.2rem; }

        .input-box { width: 100%; padding: 12px; background: #222; border: 1px solid #333; color: #fff; border-radius: 6px; box-sizing: border-box; }
        .btn-primary { background: var(--accent); color: #000; padding: 12px 25px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-primary:hover { background: #b5952f; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: #666; padding: 12px; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 15px 12px; border-bottom: 1px solid var(--border); font-size: 0.9rem; vertical-align: middle; }
        
        .code-tag { font-family: monospace; font-size: 1rem; background: #222; padding: 5px 10px; border-radius: 4px; color: var(--accent); border: 1px solid #333; }
        .badge-active { color: #4ade80; background: rgba(74, 222, 128, 0.1); padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: bold; }
        .badge-used { color: #f87171; background: rgba(248, 113, 113, 0.1); padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: bold; }
        
        /* User Status Badges */
        .st-user-active { display:inline-block; width:10px; height:10px; background:#4ade80; border-radius:50%; margin-right:5px; }
        .st-user-inactive { display:inline-block; width:10px; height:10px; background:#ef4444; border-radius:50%; margin-right:5px; }
        .row-inactive { opacity: 0.5; }

        .modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999; align-items:center; justify-content:center; }
        .modal { background: #1a1a1a; padding: 30px; border-radius: 12px; border: 1px solid #333; width: 100%; max-width: 400px; }
        
        .action-btn { cursor: pointer; text-decoration: none; font-size: 1.2rem; margin-left: 10px; border:none; background:none; transition: 0.2s; }
        .action-btn:hover { transform: scale(1.2); }
        .btn-pass { color: #60a5fa; } 
        .btn-del { color: #ef4444; }
        .btn-pause { color: #facc15; }
        .btn-play { color: #4ade80; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="brand">👑 Painel Mestre</div>
        <div style="display:flex; align-items:center; gap:20px;">
            <span style="font-size:0.8rem; color:#666;">Modo Deus</span>
            <a href="?logout_admin=1" class="btn-exit">Sair</a>
        </div>
    </div>

    <?php if($msg): ?><div style="margin-bottom:20px; padding:15px; background:rgba(255,255,255,0.1); color:#fff; border:1px solid #555; border-radius:6px; text-align:center;"><?php echo $msg; ?></div><?php endif; ?>

    <div class="tabs">
        <a href="?tab=keys" class="tab-btn <?php echo $tab=='keys'?'active':''; ?>">🔑 Vendas/Chaves</a>
        <a href="?tab=users" class="tab-btn <?php echo $tab=='users'?'active':''; ?>">👥 Gestão de Donos</a>
        <a href="?tab=admins" class="tab-btn <?php echo $tab=='admins'?'active':''; ?>">🛡️ Admins do Sistema</a>
    </div>

    <?php if($tab == 'keys'): ?>
    <div class="card">
        <h3>Gerar Nova Chave</h3>
        <form method="POST" style="display:flex; gap:15px;">
            <input type="text" name="custom_code" placeholder="Código Personalizado (Opcional)" class="input-box">
            <button type="submit" name="generate" class="btn-primary">Criar Chave</button>
        </form>
    </div>
    <div class="card">
        <h3>Histórico de Vendas</h3>
        <table>
            <thead><tr><th>Chave</th><th>Status</th><th>ID Empresa</th><th>Criado</th><th style="text-align:right;">Ação</th></tr></thead>
            <tbody>
                <?php foreach($codes as $c): ?>
                <tr>
                    <td><span class="code-tag"><?php echo htmlspecialchars($c['code']); ?></span></td>
                    <td><?php echo ($c['status']=='active') ? '<span class="badge-active">Disponível</span>' : '<span class="badge-used">Usada</span>'; ?></td>
                    <td style="color:#666;"><?php echo $c['used_by_tenant_id'] ? '#' . $c['used_by_tenant_id'] : '-'; ?></td>
                    <td style="color:#666;"><?php echo date('d/m/y', strtotime($c['created_at'])); ?></td>
                    <td style="text-align:right;">
                        <?php if($c['status'] == 'active'): ?>
                            <a href="?del_code=<?php echo $c['id']; ?>" onclick="return confirm('Apagar?')" class="action-btn btn-del">🗑️</a>
                        <?php else: ?>🔒<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if($tab == 'users'): ?>
    <div class="card">
        <h3>Donos Registrados</h3>
        <table>
            <thead><tr><th>Empresa</th><th>Dono / Login</th><th>Status</th><th>Senha</th><th style="text-align:right;">Ações</th></tr></thead>
            <tbody>
                <?php foreach($users as $u): 
                    $isActive = ($u['status'] !== 'inactive');
                    $rowClass = $isActive ? '' : 'row-inactive';
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td>
                        <strong style="color:var(--accent);"><?php echo htmlspecialchars($u['company']); ?></strong><br>
                        <small style="color:#666;">ID #<?php echo $u['tenant_id']; ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($u['name']); ?><br>
                        <span style="font-family:monospace; color:#aaa; font-size:0.85rem;"><?php echo htmlspecialchars($u['email']); ?></span>
                    </td>
                    <td>
                        <?php if($isActive): ?>
                            <span style="color:#4ade80; font-size:0.8rem; font-weight:bold;">● Ativo</span>
                        <?php else: ?>
                            <span style="color:#ef4444; font-size:0.8rem; font-weight:bold;">● Suspenso</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button onclick="openPassModal('<?php echo $u['id']; ?>', '<?php echo $u['name']; ?>')" class="action-btn btn-pass" title="Alterar Senha">🔑</button>
                    </td>
                    <td style="text-align:right; white-space:nowrap;">
                        <?php if($isActive): ?>
                            <a href="?tab=users&toggle_user=<?php echo $u['id']; ?>&st=active" class="action-btn btn-pause" title="Suspender Acesso (Não exclui dados)">⏸️</a>
                        <?php else: ?>
                            <a href="?tab=users&toggle_user=<?php echo $u['id']; ?>&st=inactive" class="action-btn btn-play" title="Reativar Acesso">▶️</a>
                        <?php endif; ?>

                        <a href="?tab=users&del_tenant=<?php echo $u['tenant_id']; ?>" onclick="return confirm('ATENÇÃO: Isso apagará a empresa e o usuário DEFINITIVAMENTE. Confirmar?')" class="action-btn btn-del" title="Excluir Definitivamente">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if($tab == 'admins'): ?>
    <div class="card">
        <h3>Autorizar Novo Admin</h3>
        <form method="POST" style="display:flex; gap:15px;">
            <input type="hidden" name="add_admin" value="1">
            <input type="email" name="admin_email" placeholder="E-mail do novo admin..." class="input-box" required>
            <button type="submit" class="btn-primary">Autorizar</button>
        </form>
    </div>
    <div class="card">
        <h3>Admins Autorizados</h3>
        <table>
            <thead><tr><th>E-mail Autorizado</th><th>Adicionado em</th><th style="text-align:right;">Remover</th></tr></thead>
            <tbody>
                <?php foreach($admins as $a): ?>
                <tr>
                    <td style="font-size:1.1rem; color:#fff;"><?php echo htmlspecialchars($a['email']); ?></td>
                    <td style="color:#666;"><?php echo date('d/m/Y H:i', strtotime($a['added_at'])); ?></td>
                    <td style="text-align:right;">
                        <a href="?del_admin=<?php echo $a['id']; ?>" onclick="return confirm('Remover acesso deste admin?')" class="action-btn btn-del">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<div id="passModal" class="modal-overlay">
    <div class="modal">
        <h3 style="border:none; margin-bottom:10px;">Alterar Senha</h3>
        <p style="color:#888; margin-bottom:20px;">Nova senha para: <strong id="modalUserName" style="color:#fff;"></strong></p>
        <form method="POST">
            <input type="hidden" name="change_pass" value="1">
            <input type="hidden" name="user_id" id="modalUserId">
            <input type="text" name="new_password" class="input-box" required placeholder="Digite a nova senha..." style="margin-bottom:20px;">
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn-primary" style="flex:1;">Salvar</button>
                <button type="button" onclick="document.getElementById('passModal').style.display='none'" class="btn-primary" style="background:#333; color:#fff; flex:1;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPassModal(id, name) {
        document.getElementById('modalUserId').value = id;
        document.getElementById('modalUserName').innerText = name;
        document.getElementById('passModal').style.display = 'flex';
    }
</script>

</body>
</html>