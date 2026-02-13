<?php
/* Arquivo: /modules/projects/editar_projeto.php */
/* Versão: Squad Dinâmico + Auto-Role + Notificações de Equipe (Info) */

session_start();
require '../../config/db.php';

// Função auxiliar para notificações (Caso não esteja no db.php)
if (!function_exists('addNotification')) {
    function addNotification($pdo, $tenant_id, $user_id, $type, $message) {
        $stmt = $pdo->prepare("INSERT INTO notifications (tenant_id, user_id, type, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$tenant_id, $user_id, $type, $message]);
    }
}

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) { header("Location: projetos.php"); exit; }

$id = $_GET['id'];
$tenant_id = $_SESSION['tenant_id'];

// --- 1. AUTO-MIGRAÇÃO (Adiciona coluna se necessário) ---
try {
    $check = $pdo->query("SHOW COLUMNS FROM projects LIKE 'squad_data'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN squad_data LONGTEXT DEFAULT NULL");
    }
} catch (Exception $e) {}

// --- 2. PROCESSAR SALVAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $squad_list = [];
        if (isset($_POST['squad_uid']) && isset($_POST['squad_role'])) {
            foreach ($_POST['squad_uid'] as $index => $uid) {
                if (!empty($uid)) {
                    $squad_list[] = [
                        'user_id' => $uid,
                        'role' => $_POST['squad_role'][$index] ?: 'Colaborador'
                    ];
                }
            }
        }
        $squad_json = json_encode($squad_list);
        $projName = $_POST['title'];

        $stmt = $pdo->prepare("
            UPDATE projects SET 
            title=?, description=?, deadline=?, status=?, client_id=?, 
            squad_data=? 
            WHERE id=? AND tenant_id=?
        ");
        
        $stmt->execute([
            $projName, 
            $_POST['description'], 
            $_POST['deadline'], 
            $_POST['status'], 
            $_POST['client_id'],
            $squad_json,
            $id, $tenant_id
        ]);

        // --- UPGRADE: NOTIFICAÇÃO PARA A EQUIPE (COR AZUL/INFO) ---
        foreach ($squad_list as $member) {
            $msg = "Você foi escalado para o projeto **" . $projName . "** como **" . $member['role'] . "**.";
            addNotification($pdo, $tenant_id, $member['user_id'], 'info', $msg);
        }
        
        header("Location: detalhes.php?id=$id"); exit;
        
    } catch (PDOException $e) { 
        $erro = "Erro ao atualizar: " . $e->getMessage(); 
    }
}

// --- 3. BUSCAR DADOS ---
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND tenant_id = ?");
$stmt->execute([$id, $tenant_id]);
$projeto = $stmt->fetch();

if (!$projeto) die("Projeto não encontrado.");

// Buscas auxiliares
$clientes = $pdo->query("SELECT id, name FROM clients WHERE tenant_id = $tenant_id ORDER BY name ASC")->fetchAll();
$usersQuery = $pdo->prepare("SELECT id, name, custom_title FROM users WHERE tenant_id = ? ORDER BY name ASC");
$usersQuery->execute([$tenant_id]);
$users = $usersQuery->fetchAll(PDO::FETCH_ASSOC);

// Lógica de Squad (Compatibilidade com formato antigo)
$currentSquad = [];
if (!empty($projeto['squad_data'])) {
    $currentSquad = json_decode($projeto['squad_data'], true);
} else {
    if ($projeto['head_id']) $currentSquad[] = ['user_id' => $projeto['head_id'], 'role' => 'Head de Projeto'];
    if ($projeto['designer_id']) $currentSquad[] = ['user_id' => $projeto['designer_id'], 'role' => 'Designer'];
    if ($projeto['video_id']) $currentSquad[] = ['user_id' => $projeto['video_id'], 'role' => 'Editor de Vídeo'];
    if ($projeto['social_id']) $currentSquad[] = ['user_id' => $projeto['social_id'], 'role' => 'Social Media'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Projeto | Bliss OS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .form-section-title { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
        .squad-row { display: grid; grid-template-columns: 1fr 1fr 40px; gap: 10px; margin-bottom: 10px; align-items: center; }
        .btn-remove-row { background: #fee2e2; color: #ef4444; border: 1px solid #fecaca; border-radius: 6px; width: 35px; height: 35px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .btn-add-member { background: var(--bg-body-alt); color: var(--text-main); border: 1px dashed var(--border); width: 100%; padding: 12px; border-radius: 8px; cursor: pointer; font-weight: 600; margin-top: 10px; transition: 0.2s; }
        .btn-add-member:hover { border-color: var(--accent-color); color: var(--accent-color); }
        .danger-zone { margin-top: 3rem; padding-top: 2rem; border-top: 1px dashed #fee2e2; text-align: center; }
        .btn-danger-outline { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 14px; background: #fff5f5; color: #dc2626; border: 1px solid #fecaca; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-danger-outline:hover { background: #dc2626; color: white; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <div style="max-width: 800px; margin: 0 auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                <a href="detalhes.php?id=<?php echo $id; ?>" style="color: var(--text-muted); text-decoration:none;">&larr; Voltar ao Projeto</a>
                <h2 style="margin:0;">Configurações do Projeto</h2>
            </div>

            <div class="project-card-wrapper" style="padding: 2rem; background: var(--bg-card); border-radius:16px;">
                <form method="POST">
                    <div class="form-section-title">Dados Gerais</div>
                    <div class="form-group">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($projeto['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descrição / Briefing</label>
                        <textarea name="description" class="form-input" style="height:100px;"><?php echo htmlspecialchars($projeto['description']); ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top:1.5rem;">
                        <div class="form-group">
                            <label class="form-label">Cliente</label>
                            <select name="client_id" class="form-input" required>
                                <?php foreach($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $c['id']==$projeto['client_id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Prazo</label>
                            <input type="date" name="deadline" class="form-input" value="<?php echo $projeto['deadline']; ?>">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:1.5rem;">
                        <label class="form-label">Status do Projeto</label>
                        <select name="status" class="form-input">
                            <option value="pending" <?php echo $projeto['status']=='pending'?'selected':''; ?>>Pendente</option>
                            <option value="in_progress" <?php echo $projeto['status']=='in_progress'?'selected':''; ?>>Em Produção</option>
                            <option value="approval" <?php echo $projeto['status']=='approval'?'selected':''; ?>>Em Aprovação</option>
                            <option value="completed" <?php echo $projeto['status']=='completed'?'selected':''; ?>>Concluído</option>
                        </select>
                    </div>

                    <div class="form-section-title" style="margin-top: 2.5rem;">Squad do Projeto</div>
                    <div id="squadContainer">
                        <?php foreach($currentSquad as $member): ?>
                            <div class="squad-row">
                                <select name="squad_uid[]" class="form-input" onchange="autoFillRole(this)">
                                    <option value="">-- Membro --</option>
                                    <?php foreach($users as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $member['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="squad_role[]" class="form-input" placeholder="Cargo/Função" value="<?php echo htmlspecialchars($member['role']); ?>">
                                <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-add-member" onclick="addSquadRow()">+ Adicionar ao Squad</button>
                    
                    <div style="margin-top:3rem;">
                        <button type="submit" class="btn-primary" style="width:100%; height:55px; font-size:1rem;">💾 Salvar Todas as Alterações</button>
                    </div>
                </form>

                <div class="danger-zone">
                    <button onclick="confirmarExclusao()" class="btn-danger-outline">🗑️ Excluir Projeto Permanentemente</button>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Dados para o Auto-Fill
const userRoles = { <?php foreach($users as $u) echo '"'.$u['id'].'": "'.addslashes($u['custom_title']).'",'; ?> };
const usersList = <?php echo json_encode($users); ?>;

function autoFillRole(select) {
    const roleInput = select.parentElement.querySelector('input[name="squad_role[]"]');
    if (userRoles[select.value]) roleInput.value = userRoles[select.value];
}

function addSquadRow() {
    const container = document.getElementById('squadContainer');
    const div = document.createElement('div');
    div.className = 'squad-row';
    let opts = '<option value="">-- Membro --</option>';
    usersList.forEach(u => { opts += `<option value="${u.id}">${u.name}</option>`; });
    div.innerHTML = `<select name="squad_uid[]" class="form-input" onchange="autoFillRole(this)" required>${opts}</select>
                     <input type="text" name="squad_role[]" class="form-input" placeholder="Cargo/Função" required>
                     <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">×</button>`;
    container.appendChild(div);
}

function confirmarExclusao() {
    if (confirm("ATENÇÃO: Isso apagará permanentemente o projeto e todos os seus arquivos. Deseja continuar?")) {
        window.location.href = "excluir_projeto.php?id=<?php echo $id; ?>";
    }
}
</script>
</body>
</html>