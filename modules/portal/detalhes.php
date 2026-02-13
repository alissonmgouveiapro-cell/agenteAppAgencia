<?php
/* Arquivo: /modules/projects/detalhes.php */
/* Função: Painel do Projeto (Tarefas, Arquivos e Compartilhamento Público) */

session_start();
require '../../config/db.php';

// Configurações
$allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'mp4', 'mov', 'avi', 'mkv'];

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) { header("Location: projetos.php"); exit; }

$project_id = $_GET['id'];
$tenant_id = $_SESSION['tenant_id'];

// --- PROCESSAMENTO DE FORMULÁRIOS (POST) ---

// 1. Gerar Link Público (Magic Link)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_link'])) {
    try {
        $token = bin2hex(random_bytes(16)); // Gera token seguro
        $stmt = $pdo->prepare("UPDATE projects SET share_token = :token WHERE id = :id AND tenant_id = :t");
        $stmt->execute(['token' => $token, 'id' => $project_id, 't' => $tenant_id]);
        header("Location: detalhes.php?id=$project_id&msg=link_gerado"); exit;
    } catch (Exception $e) { die("Erro ao gerar link."); }
}

// 2. Adicionar Tarefa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_task'])) {
    $task_title = trim($_POST['new_task']);
    if (!empty($task_title)) {
        $stmt = $pdo->prepare("INSERT INTO tasks (tenant_id, project_id, title) VALUES (:t, :p, :title)");
        $stmt->execute(['t' => $tenant_id, 'p' => $project_id, 'title' => $task_title]);
        header("Location: detalhes.php?id=$project_id"); exit;
    }
}

// 3. Mudar Status do Projeto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $stmt = $pdo->prepare("UPDATE projects SET status = :s WHERE id = :id AND tenant_id = :t");
    $stmt->execute(['s' => $_POST['new_status'], 'id' => $project_id, 't' => $tenant_id]);
    header("Location: detalhes.php?id=$project_id&msg=status_ok"); exit;
}

// 4. Upload de Arquivo/Vídeo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $file = $_FILES['arquivo'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed_extensions)) {
        $uploadDir = '../../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $newName = uniqid() . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
            $stmt = $pdo->prepare("INSERT INTO project_files (tenant_id, project_id, uploader_id, file_name, file_path, file_type) VALUES (:t, :p, :u, :fn, :fp, :ft)");
            $stmt->execute(['t' => $tenant_id, 'p' => $project_id, 'u' => $_SESSION['user_id'], 'fn' => basename($file['name']), 'fp' => $newName, 'ft' => $ext]);
            header("Location: detalhes.php?id=$project_id&msg=upload_ok"); exit;
        }
    } else {
        header("Location: detalhes.php?id=$project_id&erro=extensao"); exit;
    }
}

// --- BUSCA DE DADOS ---
try {
    // Busca Projeto
    $stmt = $pdo->prepare("SELECT p.*, c.name as client_name FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.id = :id AND p.tenant_id = :t");
    $stmt->execute(['id' => $project_id, 't' => $tenant_id]);
    $projeto = $stmt->fetch();
    if (!$projeto) die("Projeto não encontrado.");

    // Busca Arquivos
    $stmtFiles = $pdo->prepare("SELECT * FROM project_files WHERE project_id = :id ORDER BY created_at DESC");
    $stmtFiles->execute(['id' => $project_id]);
    $arquivos = $stmtFiles->fetchAll();

    // Busca Tarefas
    $stmtTasks = $pdo->prepare("SELECT * FROM tasks WHERE project_id = :id ORDER BY is_completed ASC, created_at ASC");
    $stmtTasks->execute(['id' => $project_id]);
    $tarefas = $stmtTasks->fetchAll();

} catch (PDOException $e) { die($e->getMessage()); }

// Helpers
function getFileIcon($ext) {
    return in_array($ext, ['mp4', 'mov', 'avi', 'mkv']) ? '🎥' : (in_array($ext, ['jpg', 'png', 'jpeg']) ? '🖼️' : '📄');
}

$status_map = [
    'pending' => ['label' => 'A Fazer', 'class' => 'badge-pending'],
    'in_progress' => ['label' => 'Em Produção', 'class' => 'badge-in_progress'],
    'approval' => ['label' => 'Em Aprovação', 'class' => 'badge-approval'],
    'completed' => ['label' => 'Concluído', 'class' => 'badge-completed']
];
$status_atual = $status_map[$projeto['status']];

// Monta a URL Base automaticamente para o link público
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// Ajuste fino: remove '/modules/projects' da URL atual para achar a raiz
$path_parts = explode('/modules', $_SERVER['REQUEST_URI']);
$base_url = $protocol . "://" . $host . $path_parts[0];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($projeto['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .project-dashboard { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem; }
        @media (max-width: 900px) { .project-dashboard { grid-template-columns: 1fr; } }

        .panel { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 1.5rem; }
        .panel h3 { font-size: 1.1rem; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; }

        .task-item { display: flex; align-items: center; gap: 10px; padding: 0.8rem 0; border-bottom: 1px solid #f8fafc; }
        .task-checkbox { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .task-checkbox.checked { background: #10b981; border-color: #10b981; color: white; }
        .task-text.checked { text-decoration: line-through; color: var(--text-muted); opacity: 0.7; }
        
        .upload-area { border: 2px dashed #cbd5e1; padding: 1.5rem; text-align: center; border-radius: 8px; cursor: pointer; transition: 0.2s; }
        .upload-area:hover { border-color: var(--primary-color); background: #eff6ff; }
        
        .details-header { background: white; padding: 2rem; border-radius: 12px; box-shadow: var(--shadow); }
        .btn-icon { text-decoration: none; font-size: 1.1rem; padding: 5px; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <a href="projetos.php" style="color: var(--text-muted); text-decoration: none;">&larr; Voltar</a>
        <br><br>

        <div class="details-header">
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1><?php echo htmlspecialchars($projeto['title']); ?></h1>
                    <div style="margin-top: 5px; color: var(--text-muted);">
                        🏢 <?php echo htmlspecialchars($projeto['client_name']); ?> &nbsp;•&nbsp; 
                        📅 <?php echo date('d/m/Y', strtotime($projeto['deadline'])); ?>
                    </div>
                </div>
                
                <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                    <span class="badge <?php echo $status_atual['class']; ?>"><?php echo $status_atual['label']; ?></span>
                    <select name="new_status" onchange="this.form.submit()" style="padding: 6px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <option value="" disabled selected>Mudar Status</option>
                        <option value="pending">A Fazer</option>
                        <option value="in_progress">Em Produção</option>
                        <option value="approval">Aprovação</option>
                        <option value="completed">Concluído</option>
                    </select>
                </form>
            </div>

            <?php if (!empty($projeto['client_feedback'])): ?>
                <div style="background: #fff1f2; color: #be123c; padding: 1rem; border-radius: 6px; margin-top: 1.5rem; border: 1px solid #fecdd3;">
                    <strong>💬 Feedback do Cliente (Alteração Solicitada):</strong><br>
                    <?php echo htmlspecialchars($projeto['client_feedback']); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="project-dashboard">
            
            <div class="column-left">
                <div class="panel">
                    <h3>📋 Checklist de Produção</h3>
                    <div class="task-list">
                        <?php if (count($tarefas) > 0): ?>
                            <?php foreach ($tarefas as $task): ?>
                                <a href="toggle_task.php?id=<?php echo $task['id']; ?>&pid=<?php echo $project_id; ?>" style="text-decoration: none; color: inherit;">
                                    <div class="task-item">
                                        <div class="task-checkbox <?php echo $task['is_completed'] ? 'checked' : ''; ?>">
                                            <?php if ($task['is_completed']): ?>✓<?php endif; ?>
                                        </div>
                                        <div class="task-text <?php echo $task['is_completed'] ? 'checked' : ''; ?>">
                                            <?php echo htmlspecialchars($task['title']); ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #cbd5e1; font-size: 0.9rem;">Nenhuma tarefa.</p>
                        <?php endif; ?>
                    </div>
                    <form method="POST" style="display: flex; gap: 10px; margin-top: 1rem;">
                        <input type="text" name="new_task" class="form-input" placeholder="+ Nova tarefa..." required>
                        <button type="submit" class="btn-primary" style="padding: 0 1rem;">Add</button>
                    </form>
                </div>
            </div>

            <div class="column-right">
                
                <div class="panel" style="border: 1px solid #bfdbfe; background: #eff6ff;">
                    <h3 style="color: #1e40af; border-bottom-color: #dbeafe;">🔗 Compartilhamento Público</h3>
                    
                    <?php if ($projeto['share_token']): ?>
                        <p style="font-size: 0.85rem; color: #1e3a8a; margin-bottom: 10px;">Link para cliente (sem senha):</p>
                        <div style="display: flex; gap: 5px;">
                            <input type="text" value="<?php echo $base_url . '/public/ver.php?t=' . $projeto['share_token']; ?>" 
                                   id="shareLink" readonly 
                                   style="width: 100%; padding: 8px; border: 1px solid #93c5fd; border-radius: 6px; font-size: 0.8rem; color: #555;">
                            <button onclick="copiarLink()" class="btn-primary" style="padding: 0 12px; font-size: 0.8rem;">Copiar</button>
                        </div>
                        <span id="copyMsg" style="display: none; color: #166534; font-size: 0.75rem; margin-top: 5px;">Link copiado! ✅</span>
                    <?php else: ?>
                        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 10px;">Gere um link para enviar no WhatsApp.</p>
                        <form method="POST">
                            <input type="hidden" name="generate_link" value="1">
                            <button type="submit" class="btn-primary" style="width: 100%; background: #2563eb;">⚡ Gerar Link</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <h3>☁️ Arquivos e Mídia</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                            <p>📥 Clique para enviar arquivos</p>
                            <input type="file" name="arquivo" id="fileInput" style="display: none;" onchange="this.form.submit()">
                        </div>
                    </form>
                    <div class="file-list" style="margin-top: 1rem;">
                        <?php foreach ($arquivos as $arq): ?>
                            <div class="file-item" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                                <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                                    <span><?php echo getFileIcon($arq['file_type']); ?></span>
                                    <div style="font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 150px;">
                                        <?php echo htmlspecialchars($arq['file_name']); ?>
                                    </div>
                                </div>
                                <div>
                                    <a href="../../uploads/<?php echo $arq['file_path']; ?>" download class="btn-icon">⬇️</a>
                                    <a href="delete_file.php?id=<?php echo $arq['id']; ?>&pid=<?php echo $project_id; ?>" class="btn-icon" style="color: red;" onclick="return confirm('Apagar?')">🗑️</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
function copiarLink() {
    var copyText = document.getElementById("shareLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999); 
    document.execCommand("copy");
    
    // Feedback visual
    var msg = document.getElementById("copyMsg");
    msg.style.display = "block";
    setTimeout(function(){ msg.style.display = "none"; }, 3000);
}
</script>

</body>
</html>