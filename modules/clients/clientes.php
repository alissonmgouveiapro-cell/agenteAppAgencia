<?php
/* Arquivo: /modules/clients/clientes.php */
/* Versão: Modal Corrigido (Scroll Interno + Fechar Visível) */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

$tenant_id = $_SESSION['tenant_id'];
$current_role = $_SESSION['role'] ?? 'user'; 
$is_admin = ($current_role === 'admin'); 

// --- AUTO-MIGRAÇÃO ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_statuses (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT, name VARCHAR(50), color VARCHAR(7))");
    
    $cols = $pdo->query("SHOW COLUMNS FROM clients");
    $existing_cols = $cols->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('whatsapp_link', $existing_cols)) $pdo->exec("ALTER TABLE clients ADD whatsapp_link VARCHAR(255) DEFAULT NULL");
    if (!in_array('drive_link', $existing_cols)) $pdo->exec("ALTER TABLE clients ADD drive_link VARCHAR(255) DEFAULT NULL");
    if (!in_array('profile_pic', $existing_cols)) $pdo->exec("ALTER TABLE clients ADD profile_pic VARCHAR(255) DEFAULT NULL");

    $colsProj = $pdo->query("SHOW COLUMNS FROM projects");
    $existing_cols_proj = $colsProj->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_visible', $existing_cols_proj)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN is_visible TINYINT DEFAULT 1");
    }
} catch (Exception $e) { }

$checkSt = $pdo->prepare("SELECT COUNT(*) FROM client_statuses WHERE tenant_id = ?");
$checkSt->execute([$tenant_id]);
if ($checkSt->fetchColumn() == 0) {
    $defaults = [['Ativo', '#dcfce7'], ['Inativo', '#fee2e2'], ['Pausado', '#fef3c7'], ['Novo', '#e0e7ff']];
    $ins = $pdo->prepare("INSERT INTO client_statuses (tenant_id, name, color) VALUES (?, ?, ?)");
    foreach($defaults as $d) $ins->execute([$tenant_id, $d[0], $d[1]]);
}

// --- GET ACTIONS ---
if (isset($_GET['del_status_config']) && $is_admin) {
    $sid = (int)$_GET['del_status_config'];
    $pdo->prepare("DELETE FROM client_statuses WHERE id=? AND tenant_id=?")->execute([$sid, $tenant_id]);
    header("Location: clientes.php?open_config=1"); exit;
}

if (isset($_GET['del_client']) && $is_admin) {
    $cid = (int)$_GET['del_client'];
    $stm = $pdo->prepare("SELECT profile_pic FROM clients WHERE id=? AND tenant_id=?");
    $stm->execute([$cid, $tenant_id]);
    $pic = $stm->fetchColumn();
    if($pic && file_exists("../../uploads/avatars/".$pic)) @unlink("../../uploads/avatars/".$pic);

    $pdo->prepare("DELETE FROM clients WHERE id=? AND tenant_id=?")->execute([$cid, $tenant_id]);
    header("Location: clientes.php?msg=deleted"); exit;
}

// --- POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['save_status_config'])) {
        if(isset($_POST['status_id'])) {
            foreach($_POST['status_id'] as $k => $id) {
                $name = trim($_POST['status_name'][$k]); $color = $_POST['status_color'][$k];
                if(!empty($name)) $pdo->prepare("UPDATE client_statuses SET name=?, color=? WHERE id=? AND tenant_id=?")->execute([$name, $color, $id, $tenant_id]);
            }
        }
        if(!empty($_POST['new_status_name'])) {
            $pdo->prepare("INSERT INTO client_statuses (tenant_id, name, color) VALUES (?, ?, ?)")->execute([$tenant_id, $_POST['new_status_name'], $_POST['new_status_color']]);
        }
        header("Location: clientes.php"); exit;
    }

    if (isset($_POST['save_client'])) {
        $name = trim($_POST['name']); 
        $email = trim($_POST['email']); 
        $phone = trim($_POST['phone']);
        $status = trim($_POST['status']); if(empty($status)) $status = 'Novo';
        $whatsapp_link = trim($_POST['whatsapp_link']); 
        $drive_link = trim($_POST['drive_link']);
        $client_id = $_POST['client_id'] ?? '';
        
        $new_pic = null;
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $uploadDir = '../../uploads/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = uniqid('avatar_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadDir . $filename)) {
                    $new_pic = $filename;
                }
            }
        }

        $target_id = 0;
        if (!empty($client_id)) {
            $sql = "UPDATE clients SET name=?, email=?, phone=?, status=?, whatsapp_link=?, drive_link=?";
            $params = [$name, $email, $phone, $status, $whatsapp_link, $drive_link];
            if ($new_pic) { $sql .= ", profile_pic=?"; $params[] = $new_pic; }
            $sql .= " WHERE id=? AND tenant_id=?";
            $params[] = $client_id; $params[] = $tenant_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $target_id = $client_id;
        } else {
            $stmt = $pdo->prepare("INSERT INTO clients (tenant_id, name, email, phone, status, whatsapp_link, drive_link, profile_pic) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $name, $email, $phone, $status, $whatsapp_link, $drive_link, $new_pic]);
            $target_id = $pdo->lastInsertId();
        }

        $hidden_statuses = ['Inativo', 'Pausado'];
        $proj_visibility = in_array($status, $hidden_statuses) ? 0 : 1;
        $updProj = $pdo->prepare("UPDATE projects SET is_visible = ? WHERE client_id = ? AND tenant_id = ?");
        $updProj->execute([$proj_visibility, $target_id, $tenant_id]);

        if(!empty($client_id) && isset($_GET['id'])) header("Location: clientes.php?id=$client_id"); 
        else header("Location: clientes.php"); 
        exit;
    }

    if (isset($_POST['update_contract']) && $is_admin) {
        $client_id = $_POST['client_id']; $end_date = $_POST['contract_end']; $pdfPath = null;
        if (isset($_FILES['contract_pdf']) && $_FILES['contract_pdf']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['contract_pdf']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $uploadDir = '../../uploads/contracts/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $newName = 'contrato_' . $client_id . '_' . time() . '.pdf';
                if (move_uploaded_file($_FILES['contract_pdf']['tmp_name'], $uploadDir . $newName)) $pdfPath = $newName;
            }
        }
        $sql = "UPDATE clients SET contract_end = ?";
        $params = [$end_date];
        if ($pdfPath) { $sql .= ", contract_pdf = ?"; $params[] = $pdfPath; }
        $sql .= " WHERE id = ? AND tenant_id = ?";
        $params[] = $client_id; $params[] = $tenant_id;
        $pdo->prepare($sql)->execute($params);
        header("Location: clientes.php?id=$client_id"); exit;
    }
}

// --- DADOS ---
$view_mode = isset($_GET['id']) ? 'detail' : 'list';
$statusConfigStmt = $pdo->prepare("SELECT * FROM client_statuses WHERE tenant_id = ? ORDER BY name ASC");
$statusConfigStmt->execute([$tenant_id]);
$allStatuses = $statusConfigStmt->fetchAll();
$statusColorMap = []; foreach($allStatuses as $st) { $statusColorMap[mb_strtolower($st['name'])] = $st['color']; }

function getStatusStyle($statusName, $map) {
    $key = mb_strtolower(trim($statusName));
    $bgColor = $map[$key] ?? '#f1f5f9'; 
    $textColor = '#1e293b'; 
    if(in_array($bgColor, ['#fee2e2', '#fecaca', '#ef4444'])) $textColor = '#991b1b'; 
    if(in_array($bgColor, ['#dcfce7', '#bbf7d0', '#10b981'])) $textColor = '#166534'; 
    if(in_array($bgColor, ['#e0e7ff', '#c7d2fe', '#3b82f6'])) $textColor = '#3730a3'; 
    if(in_array($bgColor, ['#fef3c7', '#fde68a', '#f59e0b'])) $textColor = '#92400e'; 
    return "background-color: {$bgColor}; color: {$textColor}; border: 1px solid {$bgColor};";
}
function formatWaLink($input) { if(empty($input)) return ''; if(strpos($input, 'http') !== false) return $input; $nums = preg_replace('/[^0-9]/', '', $input); return "https://wa.me/55" . $nums; }
function getInitials($name) { $parts = explode(' ', trim($name)); $ret = strtoupper($parts[0][0]); if (count($parts)>1) $ret .= strtoupper(end($parts)[0]); return $ret; }

if ($view_mode === 'detail') {
    $client_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?"); $stmt->execute([$client_id, $tenant_id]); $cliente = $stmt->fetch();
    if (!$cliente) { header("Location: clientes.php"); exit; }
    $stmtProj = $pdo->prepare("SELECT * FROM projects WHERE client_id = ? ORDER BY created_at DESC"); 
    $stmtProj->execute([$client_id]); $projetos = $stmtProj->fetchAll();
    $contract_info = 'Sem contrato'; $contract_cls = '';
    if (!empty($cliente['contract_end'])) {
        $today = date('Y-m-d');
        if ($cliente['contract_end'] >= $today) { $contract_info = 'Ativo • Vence ' . date('d/m/y', strtotime($cliente['contract_end'])); $contract_cls = 'c-active'; } 
        else { $contract_info = 'Vencido ' . date('d/m/y', strtotime($cliente['contract_end'])); $contract_cls = 'c-expired'; }
    }
} else {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE tenant_id = ? ORDER BY name ASC"); $stmt->execute([$tenant_id]); $clientes_lista = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Clientes</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        :root { --bg-card: #ffffff; --bg-body-alt: #f8fafc; --text-main: #333333; --text-muted: #64748b; --border-color: #e2e8f0; --input-bg: #ffffff; --rest-bg: #fff8f6; --rest-border: #fed7aa; }
        [data-theme="dark"] { --bg-card: #27272a; --bg-body-alt: #18181b; --text-main: #f4f4f5; --text-muted: #a1a1aa; --border-color: #3f3f46; --input-bg: #27272a; --rest-bg: #2a1c15; --rest-border: #5c2b18; }
        body { color: var(--text-main); }
        .client-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .client-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 15px; transition: 0.2s; position: relative; }
        .client-card:hover { transform: translateY(-3px); border-color: var(--accent-color); }
        .client-header { display: flex; align-items: center; gap: 15px; cursor: pointer; }
        .client-avatar { width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #4338ca; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; overflow: hidden; border:1px solid var(--border-color); }
        .client-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .client-status-badge { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 12px; letter-spacing: 0.5px; }
        .client-actions { border-top: 1px solid var(--border-color); padding-top: 15px; margin-top: 5px; display: flex; justify-content: space-between; align-items: center; }
        .btn-card-action { font-size: 0.85rem; color: var(--text-muted); cursor: pointer; background:none; border:none; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-card-action:hover { color: var(--text-main); }
        .form-input, .config-input { background-color: var(--input-bg) !important; color: var(--text-main) !important; border: 1px solid var(--border-color) !important; }
        
        /* MODAL FIX */
        .status-modal-card { 
            background: var(--bg-card); 
            color: var(--text-main); 
            width: 100%; 
            max-width: 500px; 
            max-height: 90vh; /* Altura máxima da tela */
            display: flex; 
            flex-direction: column; 
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .st-modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0; /* Cabeçalho fixo */
        }
        .modal-scroll-content {
            padding: 20px;
            overflow-y: auto; /* Conteúdo rolável */
            flex: 1; /* Ocupa o resto do espaço */
        }
        
        .restricted-zone { background-color: var(--rest-bg); border: 1px solid var(--rest-border); border-radius: 12px; padding: 20px; margin-bottom: 2rem; position: relative; }
        .restricted-badge { position: absolute; top: -12px; left: 20px; background: #c2410c; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .c-active { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .c-expired { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
        .history-item { background: var(--bg-card); border: 1px solid var(--border-color); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; transition: 0.2s; }
        .history-item:hover { border-color: var(--accent-color); transform: translateX(5px); }
        .history-item.is-hidden { opacity: 0.5; border: 1px dashed var(--border-color); }
        .config-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .color-picker { width: 40px; height: 38px; padding: 0; border: none; background: none; cursor: pointer; }
        .btn-del-row { color: #ef4444; text-decoration: none; font-size: 1.5rem; line-height: 1; padding: 0 5px; cursor: pointer; border: none; background: none; }
        .btn-del-row:hover { color: #dc2626; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">

        <?php if ($view_mode === 'detail'): ?>
            <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <a href="clientes.php" style="color: var(--text-muted); text-decoration:none; font-size:0.9rem;">&larr; Voltar para Lista</a>
                    <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                        <div class="client-avatar" style="width:70px; height:70px; font-size:1.5rem;">
                            <?php if(!empty($cliente['profile_pic']) && file_exists("../../uploads/avatars/" . $cliente['profile_pic'])): ?>
                                <img src="../../uploads/avatars/<?php echo $cliente['profile_pic']; ?>?v=<?php echo time(); ?>">
                            <?php else: ?>
                                <?php echo getInitials($cliente['name']); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h1 style="margin:0;"><?php echo htmlspecialchars($cliente['name']); ?></h1>
                            <div style="margin-top:5px;">
                                <span class="client-status-badge" style="<?php echo getStatusStyle($cliente['status'], $statusColorMap); ?>"><?php echo htmlspecialchars($cliente['status'] ?: 'Novo'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div style="color:var(--text-muted); margin-top:10px; display:flex; flex-direction:column; gap:5px;">
                        <span>📧 <?php echo htmlspecialchars($cliente['email'] ?? ''); ?></span>
                        <span>📱 <?php echo htmlspecialchars($cliente['phone'] ?? 'Sem telefone'); ?></span>
                    </div>
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <?php if(!empty($cliente['whatsapp_link'])): ?>
                            <a href="<?php echo formatWaLink($cliente['whatsapp_link']); ?>" target="_blank" class="btn-primary" style="background:#dcfce7; color:#166534; border-color:#bbf7d0;">💬 WhatsApp</a>
                        <?php endif; ?>
                        <?php if(!empty($cliente['drive_link'])): ?>
                            <a href="<?php echo htmlspecialchars($cliente['drive_link']); ?>" target="_blank" class="btn-primary" style="background:var(--bg-body-alt); color:var(--text-main); border:1px solid var(--border-color);">📁 Drive</a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <button class="btn-primary" style="background:var(--bg-card); color:var(--text-main); border:1px solid var(--border-color);"
                        onclick="openEditModal('<?php echo $cliente['id']; ?>','<?php echo addslashes($cliente['name']); ?>','<?php echo addslashes($cliente['email']); ?>','<?php echo addslashes($cliente['phone'] ?? ''); ?>','<?php echo addslashes($cliente['status'] ?? 'Novo'); ?>','<?php echo addslashes($cliente['whatsapp_link'] ?? ''); ?>','<?php echo addslashes($cliente['drive_link'] ?? ''); ?>', '<?php echo $cliente['profile_pic'] ?? ''; ?>')">✏️ Editar</button>
                    <?php if($is_admin): ?>
                        <button type="button" onclick="if(confirm('ATENÇÃO: Deseja realmente excluir este cliente?')) window.location.href='clientes.php?del_client=<?php echo $cliente['id']; ?>'" class="btn-primary" style="background:#fef2f2; color:#ef4444; border:1px solid #fecaca;">🗑️</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($is_admin): ?>
                <div class="restricted-zone">
                    <div class="restricted-badge">🔒 Área do Dono</div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="margin:0; color:inherit;">Gestão de Contrato</h3>
                        <?php if(!empty($cliente['contract_end'])): ?><span class="<?php echo $contract_cls; ?>"><?php echo $contract_info; ?></span><?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: end;">
                        <input type="hidden" name="update_contract" value="1"><input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="form-group" style="margin:0;"><label class="form-label">Vencimento</label><input type="date" name="contract_end" class="form-input" value="<?php echo $cliente['contract_end'] ?? ''; ?>"></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">Arquivo PDF</label><input type="file" name="contract_pdf" class="form-input" accept="application/pdf"></div>
                        <button type="submit" class="btn-primary" style="height: 42px;">Salvar Contrato</button>
                    </form>
                    <?php if (!empty($cliente['contract_pdf'])): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--rest-border);"><a href="../../uploads/contracts/<?php echo $cliente['contract_pdf']; ?>" target="_blank" style="text-decoration:none; font-weight:600; color:inherit;">📄 Visualizar PDF</a></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="project-card-wrapper" style="padding: 2rem;">
                <h3 style="margin-bottom: 1.5rem;">Histórico de Projetos</h3>
                <?php if ($cliente['status'] == 'Inativo' || $cliente['status'] == 'Pausado'): ?>
                    <div style="background: #fff1f2; color: #9f1239; padding: 10px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #fecdd3;">
                        ⚠️ Este cliente está <strong><?php echo htmlspecialchars($cliente['status']); ?></strong>. Seus projetos estão ocultos nas listagens gerais.
                    </div>
                <?php endif; ?>

                <?php if (count($projetos) > 0): ?>
                    <div style="display: flex; flex-direction: column; gap:10px;">
                        <?php foreach($projetos as $proj): 
                            $stColor = '#cbd5e1'; $stName = 'Pendente';
                            if(($proj['status'] ?? '') =='completed') { $stColor='#10b981'; $stName='Concluído'; }
                            if(($proj['status'] ?? '') =='in_progress') { $stColor='#3b82f6'; $stName='Em Produção'; }
                            $hiddenClass = (isset($proj['is_visible']) && $proj['is_visible'] == 0) ? 'is-hidden' : '';
                        ?>
                            <a href="../projects/detalhes.php?id=<?php echo $proj['id']; ?>" class="history-item <?php echo $hiddenClass; ?>" style="text-decoration: none; color: inherit;">
                                <div>
                                    <div style="font-weight: 700; font-size: 1rem; color: var(--text-main);">
                                        <?php echo htmlspecialchars($proj['title']); ?>
                                        <?php if($hiddenClass): ?><small style="color:#ef4444;">(Oculto)</small><?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Criado: <?php echo date('d/m/Y', strtotime($proj['created_at'])); ?></div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px;"><span style="padding:4px 10px; border-radius:12px; color:white; font-size:0.75rem; font-weight:bold; background: <?php echo $stColor; ?>;"><?php echo $stName; ?></span><span style="color: var(--text-muted);">➔</span></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">Nenhum projeto.</div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                <div><h1 style="margin:0; font-size:1.8rem;">Clientes</h1><p style="color:var(--text-muted); margin-top:5px;">Gerencie sua carteira.</p></div>
                <div style="display:flex; gap:10px;">
                    <button onclick="document.getElementById('configModal').style.display='flex'" class="btn-primary" style="background:var(--bg-card); color:var(--text-main); border:1px solid var(--border-color); width:45px; padding:0; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">⚙️</button>
                    <button onclick="openNewModal()" class="btn-primary" style="height:45px;">+ Novo Cliente</button>
                </div>
            </div>

            <div class="client-grid">
                <?php foreach($clientes_lista as $c): ?>
                    <div class="client-card">
                        <div class="client-header" onclick="window.location.href='?id=<?php echo $c['id']; ?>'" style="width:100%;">
                            <div class="client-avatar">
                                <?php if(!empty($c['profile_pic']) && file_exists("../../uploads/avatars/" . $c['profile_pic'])): ?>
                                    <img src="../../uploads/avatars/<?php echo $c['profile_pic']; ?>?v=<?php echo time(); ?>">
                                <?php else: ?>
                                    <?php echo getInitials($c['name']); ?>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <div style="font-weight:700; font-size:1.05rem; color:var(--text-main);"><?php echo htmlspecialchars($c['name']); ?></div>
                                    <span class="client-status-badge" style="<?php echo getStatusStyle($c['status'], $statusColorMap); ?>"><?php echo htmlspecialchars($c['status'] ?: 'Novo'); ?></span>
                                </div>
                                <div style="font-size:0.85rem; color:var(--text-muted); margin-top:2px;"><?php echo htmlspecialchars($c['email']); ?></div>
                            </div>
                        </div>
                        <div class="client-actions">
                            <span class="btn-card-action" onclick="window.location.href='?id=<?php echo $c['id']; ?>'">📂 Detalhes</span>
                            <div style="display:flex; gap:10px;">
                                <?php if(!empty($c['whatsapp_link'])): ?><a href="<?php echo formatWaLink($c['whatsapp_link']); ?>" target="_blank" class="btn-card-action">💬</a><?php endif; ?>
                                <?php if(!empty($c['drive_link'])): ?><a href="<?php echo htmlspecialchars($c['drive_link']); ?>" target="_blank" class="btn-card-action">📁</a><?php endif; ?>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button class="btn-card-action" onclick="openEditModal('<?php echo $c['id']; ?>','<?php echo addslashes($c['name']); ?>','<?php echo addslashes($c['email']); ?>','<?php echo addslashes($c['phone'] ?? ''); ?>','<?php echo addslashes($c['status'] ?? 'Novo'); ?>','<?php echo addslashes($c['whatsapp_link'] ?? ''); ?>','<?php echo addslashes($c['drive_link'] ?? ''); ?>', '<?php echo $c['profile_pic'] ?? ''; ?>')">✏️</button>
                                <?php if($is_admin): ?>
                                    <button type="button" onclick="if(confirm('Excluir?')) window.location.href='clientes.php?del_client=<?php echo $c['id']; ?>'" class="btn-card-action" style="color:#ef4444;">🗑️</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="configModal" class="modal-overlay" style="display:<?php echo isset($_GET['open_config'])?'flex':'none'; ?>; align-items:center; justify-content:center;">
            <div class="status-modal-card">
                <div class="st-modal-header"><h3 style="margin:0;">Configurar Status</h3><button onclick="document.getElementById('configModal').style.display='none'" style="background:none; border:none; font-size:2rem; cursor:pointer; color:var(--text-main);">&times;</button></div>
                <div class="modal-scroll-content">
                    <form method="POST">
                        <input type="hidden" name="save_status_config" value="1">
                        <div style="margin-bottom:15px;">
                            <?php foreach($allStatuses as $st): ?>
                                <div class="config-row">
                                    <input type="hidden" name="status_id[]" value="<?php echo $st['id']; ?>">
                                    <input type="color" name="status_color[]" value="<?php echo $st['color']; ?>" class="color-picker">
                                    <input type="text" name="status_name[]" value="<?php echo htmlspecialchars($st['name']); ?>" class="config-input">
                                    <a href="clientes.php?del_status_config=<?php echo $st['id']; ?>" class="btn-del-row" onclick="return confirm('Tem certeza?');">×</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="background:var(--bg-body-alt); padding:10px; border-radius:8px; border:1px dashed var(--border-color); margin-bottom:20px;">
                            <div style="font-size:0.8rem; font-weight:bold; margin-bottom:5px; color:var(--text-muted);">+ Adicionar Status</div>
                            <div class="config-row" style="margin-bottom:0;"><input type="color" name="new_status_color" value="#e0e7ff" class="color-picker"><input type="text" name="new_status_name" placeholder="Nome..." class="config-input"></div>
                        </div>
                        <button type="submit" class="btn-primary" style="width:100%; margin-bottom:20px;">Salvar Configuração</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="clientModal" class="modal-overlay" style="display:none; align-items:center; justify-content:center;">
            <div class="status-modal-card">
                <div class="st-modal-header">
                    <h3 id="modalTitle" style="margin:0;">Novo Cliente</h3>
                    <button onclick="document.getElementById('clientModal').style.display='none'" style="background:none; border:none; font-size:2rem; cursor:pointer; color:var(--text-main); line-height:1;">&times;</button>
                </div>
                
                <div class="modal-scroll-content">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="save_client" value="1"><input type="hidden" name="client_id" id="clientId">
                        
                        <div class="form-group" style="text-align:center; margin-bottom:20px;">
                            <label for="picInput" style="cursor:pointer; display:inline-block;">
                                <div style="width:100px; height:100px; background:#e2e8f0; border-radius:50%; margin:0 auto; display:flex; align-items:center; justify-content:center; overflow:hidden; border:2px dashed #cbd5e1; position:relative;">
                                    <img id="preview" src="" style="width:100%; height:100%; object-fit:cover; display:none;">
                                    <span id="placeholder" style="color:#64748b; font-size:2rem;">📷</span>
                                </div>
                                <div style="margin-top:10px; font-size:0.85rem; color:#64748b;">Alterar Logo</div>
                            </label>
                            <input type="file" name="profile_pic" id="picInput" accept="image/*" style="display:none;" onchange="previewImage(this)">
                        </div>

                        <div class="form-group" style="margin-bottom:15px;"><label class="form-label">Nome da Empresa</label><input type="text" name="name" id="clientName" class="form-input" required></div>
                        <div class="form-group" style="margin-bottom:15px;"><label class="form-label">E-mail</label><input type="email" name="email" id="clientEmail" class="form-input" required></div>
                        <div class="form-group" style="margin-bottom:15px;"><label class="form-label">Telefone / Celular</label><input type="text" name="phone" id="clientPhone" class="form-input" onkeyup="mascaraTelefone(this)"></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div class="form-group" style="margin:0;"><label class="form-label">Link WhatsApp (ou Nº)</label><input type="text" name="whatsapp_link" id="clientWa" class="form-input"></div>
                            <div class="form-group" style="margin:0;"><label class="form-label">Pasta Drive (URL)</label><input type="text" name="drive_link" id="clientDrive" class="form-input"></div>
                        </div>
                        <div class="form-group" style="margin-bottom:20px;">
                            <label class="form-label">Status</label><input type="text" name="status" id="clientStatus" class="form-input" list="statusList">
                            <datalist id="statusList"><?php foreach($allStatuses as $st): ?><option value="<?php echo htmlspecialchars($st['name']); ?>"><?php endforeach; ?></datalist>
                            <small style="color:var(--text-muted); font-size:0.75rem;">* Definir como "Inativo" ou "Pausado" ocultará os projetos.</small>
                        </div>
                        <button type="submit" class="btn-primary" style="width:100%; margin-bottom:20px;">Salvar</button>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
function mascaraTelefone(input) { let v = input.value.replace(/\D/g,""); v = v.replace(/^(\d{2})(\d)/g,"($1) $2"); v = v.replace(/(\d)(\d{4})$/,"$1-$2"); input.value = v; }
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
function openNewModal() { 
    document.getElementById('modalTitle').innerText = 'Novo Cliente'; 
    document.getElementById('clientId').value = ''; 
    document.getElementById('clientName').value = ''; 
    document.getElementById('clientEmail').value = ''; 
    document.getElementById('clientPhone').value = ''; 
    document.getElementById('clientStatus').value = 'Novo'; 
    document.getElementById('clientWa').value = ''; 
    document.getElementById('clientDrive').value = ''; 
    document.getElementById('preview').src = '';
    document.getElementById('preview').style.display = 'none';
    document.getElementById('placeholder').style.display = 'block';
    document.getElementById('clientModal').style.display = 'flex'; 
}
function openEditModal(id, name, email, phone, status, wa, drive, pic) { 
    document.getElementById('modalTitle').innerText = 'Editar Cliente'; 
    document.getElementById('clientId').value = id; 
    document.getElementById('clientName').value = name; 
    document.getElementById('clientEmail').value = email; 
    document.getElementById('clientPhone').value = phone; 
    document.getElementById('clientStatus').value = status; 
    document.getElementById('clientWa').value = wa; 
    document.getElementById('clientDrive').value = drive; 
    if(pic) {
        document.getElementById('preview').src = '../../uploads/avatars/' + pic;
        document.getElementById('preview').style.display = 'block';
        document.getElementById('placeholder').style.display = 'none';
    } else {
        document.getElementById('preview').style.display = 'none';
        document.getElementById('placeholder').style.display = 'block';
    }
    document.getElementById('clientModal').style.display = 'flex'; 
}
</script>
</body>
</html>