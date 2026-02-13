<?php
/* Arquivo: /modules/projects/detalhes.php */
/* Versão: PLATINUM FINAL (Status de Produção Corrigido) + Botão Limpar Histórico */

@ini_set('upload_max_filesize', '500M');
@ini_set('post_max_size', '500M');
@ini_set('memory_limit', '512M');
@ini_set('display_errors', 0);
session_start();
require '../../config/db.php';

// Garante UTF-8 para funções de string
mb_internal_encoding("UTF-8");

// --- 0. FUNÇÃO DE OTIMIZAÇÃO DE VÍDEO ---
function otimizarVideoParaWeb($caminhoEntrada, $caminhoSaida) {
    $ffmpegPath = $_SERVER['DOCUMENT_ROOT'] . '/bin/ffmpeg';
    if (!file_exists($ffmpegPath)) { $ffmpegPath = 'ffmpeg'; }
    $comando = "$ffmpegPath -i " . escapeshellarg($caminhoEntrada) . " \
    -vcodec libx264 -crf 28 -preset veryfast -movflags +faststart \
    -vf \"scale='min(720,iw)':-2\" -acodec aac -b:a 128k \
    -y " . escapeshellarg($caminhoSaida) . " 2>&1";
    exec($comando, $output, $returnVar);
    return ($returnVar === 0 && file_exists($caminhoSaida) && filesize($caminhoSaida) > 0);
}

// --- 1. FUNÇÕES DE RENDERIZAÇÃO ---
function renderTaskCard($task, $subtasks, $statuses, $statusColors) {
    $taskId = $task['id'];
    $totalSub = count($subtasks);
    $doneSub = 0;
    foreach($subtasks as $st) if($st['is_completed']) $doneSub++;
    $progress = ($totalSub > 0) ? ($doneSub / $totalSub) * 100 : 0;
    $hasSubs = ($totalSub > 0);
    
    $currColor = $statusColors[$task['status']] ?? 'var(--bg-body-alt)';
    $textColor = in_array($currColor, ['#f1f5f9', '#ffffff', '#cbd5e1', 'var(--bg-body-alt)']) ? '#333' : '#fff';

    ob_start();
    ?>
    <div class="item-card" data-id="<?php echo $taskId; ?>" id="task-card-<?php echo $taskId; ?>">
        <div class="task-main-row">
            <div class="task-left">
                <span class="drag-handle">⋮⋮</span>
                <span class="task-title <?php echo $task['status']=='Concluído' ? 'task-done-style' : ''; ?>" id="task-title-display-<?php echo $taskId; ?>">
                    <?php echo htmlspecialchars($task['title']); ?>
                </span>
                <input type="text" class="edit-input-inline" id="task-title-input-<?php echo $taskId; ?>" value="<?php echo htmlspecialchars($task['title']); ?>" onblur="saveTaskTitle(<?php echo $taskId; ?>)" onkeypress="if(event.key==='Enter') saveTaskTitle(<?php echo $taskId; ?>)">
                <button class="btn-edit-pencil" onclick="editTaskTitle(<?php echo $taskId; ?>)" title="Editar">✏️</button>
            </div>
            
            <div class="task-right">
                <select onchange="updateMainStatus(<?php echo $taskId; ?>, this)" class="status-pill-select" style="background-color: <?php echo $currColor; ?>; color: <?php echo $textColor; ?>;">
                    <?php foreach($statuses as $st): ?>
                        <option value="<?php echo $st['name']; ?>" data-color="<?php echo $st['color']; ?>" <?php echo $task['status']==$st['name']?'selected':''; ?>><?php echo $st['name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-delete" onclick="deleteTask(<?php echo $taskId; ?>)" title="Excluir">🗑️</button>
            </div>
        </div>

        <div class="task-meta-row">
            <div class="progress-container" id="prog-cont-<?php echo $taskId; ?>" style="display: <?php echo $hasSubs ? 'block' : 'none'; ?>;">
                <div class="progress-fill" style="width: <?php echo $progress; ?>%;" id="prog-bar-<?php echo $taskId; ?>"></div>
            </div>
            <button class="btn-toggle-subs" onclick="toggleSubtasks(<?php echo $taskId; ?>)" id="btn-sub-<?php echo $taskId; ?>">
                <span id="arrow-<?php echo $taskId; ?>">▶</span> 
                <span id="count-<?php echo $taskId; ?>"><?php echo $doneSub . '/' . $totalSub; ?></span> Subtarefas
            </button>
        </div>

        <div id="subs-<?php echo $taskId; ?>" class="subtasks-wrapper">
            <div id="sub-list-<?php echo $taskId; ?>">
                <?php foreach($subtasks as $st): ?>
                    <?php echo renderSubtaskItem($st, $taskId); ?>
                <?php endforeach; ?>
            </div>
            <div class="sub-input-row">
                <button type="button" class="btn-sub-add" onclick="addSubtask(<?php echo $taskId; ?>)">+</button>
                <input type="text" id="new-sub-<?php echo $taskId; ?>" placeholder="Nova subtarefa..." class="sub-input" onkeypress="if(event.key==='Enter') addSubtask(<?php echo $taskId; ?>)">
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function renderSubtaskItem($st, $taskId) {
    ob_start();
    ?>
    <div class="subtask-item" id="st-item-<?php echo $st['id']; ?>">
        <div style="display:flex; align-items:center; flex:1;">
            <input type="checkbox" class="sub-check" <?php echo $st['is_completed']?'checked':''; ?> onchange="updateSubtask(<?php echo $st['id']; ?>, <?php echo $taskId; ?>, this.checked)">
            <span class="sub-text <?php echo $st['is_completed']?'sub-completed':''; ?>" id="st-txt-<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['title']); ?></span>
            <input type="text" class="edit-input-inline" id="st-input-<?php echo $st['id']; ?>" value="<?php echo htmlspecialchars($st['title']); ?>" onblur="saveSubtaskTitle(<?php echo $st['id']; ?>)" onkeypress="if(event.key==='Enter') saveSubtaskTitle(<?php echo $st['id']; ?>)">
            <button class="btn-edit-pencil-small" onclick="editSubtaskTitle(<?php echo $st['id']; ?>)" title="Editar">✏️</button>
        </div>
        <button type="button" class="btn-sub-del" onclick="deleteSubtask(event, <?php echo $st['id']; ?>, <?php echo $taskId; ?>)">✕</button>
    </div>
    <?php
    return ob_get_clean();
}

// --- 2. AUTO-MIGRAÇÃO ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_calendar (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT, project_id INT, post_date DATE, title VARCHAR(255), platform VARCHAR(50), status VARCHAR(20) DEFAULT 'pending', feedback TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_subtasks (id INT AUTO_INCREMENT PRIMARY KEY, task_id INT NOT NULL, title VARCHAR(255), is_completed TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_briefings (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NOT NULL UNIQUE, objective TEXT, target_audience TEXT, formats VARCHAR(255), visual_style TEXT, required_content TEXT, references_links TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // TABELAS NOVAS
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_analytics (id INT AUTO_INCREMENT PRIMARY KEY, project_id INT NOT NULL, month_year VARCHAR(7), reach VARCHAR(50), engagement VARCHAR(50), new_followers VARCHAR(50), updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_month (project_id, month_year))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_activity_logs (id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT, project_id INT, user_id INT, message TEXT, type VARCHAR(20) DEFAULT 'info', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    $cols = ['form_schema'=>'LONGTEXT', 'tabs_order'=>'TEXT'];
    foreach($cols as $k=>$v) { try{ $pdo->exec("ALTER TABLE projects ADD COLUMN $k $v"); }catch(Exception $e){} }
    
    $delCols = ['approval_status'=>"VARCHAR(20) DEFAULT 'pending'", 'feedback'=>'TEXT', 'internal_status'=>"VARCHAR(20) DEFAULT 'int_pending'", 'internal_notes'=>'TEXT'];
    foreach($delCols as $k=>$v) { try{ $pdo->exec("ALTER TABLE project_deliverables ADD COLUMN $k $v"); }catch(Exception $e){} }
    
    try{ $pdo->exec("ALTER TABLE project_files ADD COLUMN essay_id INT DEFAULT NULL"); }catch(Exception $e){}

} catch (Exception $e) { }

$project_id = $_GET['id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$project_id || !$tenant_id) { header("Location: projetos.php"); exit; }

// --- 3. AJAX HANDLERS ---
if (isset($_POST['ajax_action'])) {
    
    // Status
    $statusList = $pdo->prepare("SELECT * FROM task_statuses WHERE tenant_id = ?"); 
    $statusList->execute([$tenant_id]); $statuses = $statusList->fetchAll(); 
    if (count($statuses) == 0) $statuses = [['name'=>'Pendente','color'=>'#f1f5f9'], ['name'=>'Produção','color'=>'#dbeafe'], ['name'=>'Aprovação','color'=>'#ffedd5'], ['name'=>'Concluído','color'=>'#dcfce7']];
    $statusColors = []; foreach($statuses as $s) $statusColors[$s['name']] = $s['color'];

    if ($_POST['ajax_action'] === 'add_main_task') {
        $title = trim($_POST['title']);
        if($title) {
            $stmt = $pdo->prepare("INSERT INTO tasks (tenant_id, project_id, title, status, sort_order) VALUES (?,?,?, 'Pendente', 999)");
            $stmt->execute([$tenant_id, $project_id, $title]);
            // LOG
            try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Criou tarefa: $title", 'task']); } catch(Exception $e){}
            
            $newTask = ['id'=>$pdo->lastInsertId(), 'title'=>$title, 'status'=>'Pendente'];
            echo renderTaskCard($newTask, [], $statuses, $statusColors);
        }
        exit;
    }
    if ($_POST['ajax_action'] === 'add_subtask') {
        $stmt = $pdo->prepare("INSERT INTO task_subtasks (task_id, title) VALUES (?, ?)");
        $stmt->execute([$_POST['task_id'], $_POST['title']]);
        $st = ['id'=>$pdo->lastInsertId(), 'title'=>$_POST['title'], 'is_completed'=>0];
        echo renderSubtaskItem($st, $_POST['task_id']);
        exit;
    }
    if ($_POST['ajax_action'] === 'update_task_title') {
        $pdo->prepare("UPDATE tasks SET title = ? WHERE id = ? AND tenant_id = ?")->execute([$_POST['title'], $_POST['task_id'], $tenant_id]); exit;
    }
    if ($_POST['ajax_action'] === 'update_subtask_title') {
        $pdo->prepare("UPDATE task_subtasks SET title = ? WHERE id = ?")->execute([$_POST['title'], $_POST['sub_id']]); exit;
    }
    if ($_POST['ajax_action'] === 'toggle_subtask') {
        $pdo->prepare("UPDATE task_subtasks SET is_completed = ? WHERE id = ?")->execute([$_POST['val'], $_POST['subtask_id']]); exit;
    }
    if ($_POST['ajax_action'] === 'delete_subtask') {
        $pdo->prepare("DELETE FROM task_subtasks WHERE id = ?")->execute([$_POST['subtask_id']]); exit;
    }
    if ($_POST['ajax_action'] === 'delete_main_task') {
        $pdo->prepare("DELETE FROM task_subtasks WHERE task_id = ?")->execute([$_POST['task_id']]);
        $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$_POST['task_id']]); 
        try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Excluiu uma tarefa", 'delete']); } catch(Exception $e){}
        exit;
    }
    if ($_POST['ajax_action'] === 'update_main_status') {
        $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND tenant_id = ?")->execute([$_POST['status'], $_POST['task_id'], $tenant_id]); exit;
    }
    // Drag & Drop Calendar
    if ($_POST['ajax_action'] === 'move_calendar_event') {
        $pdo->prepare("UPDATE project_calendar SET post_date = ? WHERE id = ?")->execute([$_POST['new_date'], $_POST['event_id']]);
        try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Reagendou post", 'calendar']); } catch(Exception $e){}
        echo "OK"; exit;
    }
}

// --- CONFIGURAÇÕES ---
$filtro = $_GET['sort'] ?? 'newest';
$sqlOrder = "created_at DESC";
if ($filtro == 'oldest') $sqlOrder = "created_at ASC";
if ($filtro == 'az') $sqlOrder = "title ASC";

$m_raw = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$y_raw = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$ts = mktime(0, 0, 0, $m_raw, 1, $y_raw);
$month = date('m', $ts); $year = date('Y', $ts);
$daysInMonth = date('t', $ts); $dayOfWeek = date('w', $ts);

// --- PROCESSAMENTO POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- NOVO: LIMPAR HISTÓRICO ---
    if (isset($_POST['clear_history'])) {
        $pdo->prepare("DELETE FROM project_activity_logs WHERE project_id = ? AND tenant_id = ?")->execute([$project_id, $tenant_id]);
        header("Location: detalhes.php?id=$project_id&tab=geral&msg=history_cleared"); exit;
    }

    // Briefing
    if (isset($_POST['save_briefing'])) {
        $sql = "INSERT INTO project_briefings (project_id, objective, target_audience, formats, visual_style, required_content, references_links) 
                VALUES (?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                objective = VALUES(objective), target_audience = VALUES(target_audience), formats = VALUES(formats), 
                visual_style = VALUES(visual_style), required_content = VALUES(required_content), references_links = VALUES(references_links)";
        $pdo->prepare($sql)->execute([$project_id, $_POST['objective'], $_POST['target_audience'], $_POST['formats'], $_POST['visual_style'], $_POST['required_content'], $_POST['references_links']]);
        try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Atualizou briefing", 'edit']); } catch(Exception $e){}
        header("Location: detalhes.php?id=$project_id&tab=briefing&msg=briefing_saved"); exit;
    }

    // SALVAR RESULTADOS (ANALYTICS)
    if (isset($_POST['save_analytics'])) {
        $sql = "INSERT INTO project_analytics (project_id, month_year, reach, engagement, new_followers) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                reach = VALUES(reach), engagement = VALUES(engagement), new_followers = VALUES(new_followers)";
        $pdo->prepare($sql)->execute([
            $project_id, 
            $_POST['analytics_month'], 
            $_POST['reach'], 
            $_POST['engagement'], 
            $_POST['followers']
        ]);
        try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Atualizou resultados", 'analytics']); } catch(Exception $e){}
        header("Location: detalhes.php?id=$project_id&tab=resultados&msg=saved"); exit;
    }

    // Upload
    if (isset($_POST['create_post'])) {
        $stmt = $pdo->prepare("INSERT INTO project_deliverables (tenant_id, project_id, title, type, caption, internal_status) VALUES (?,?,?,?,?, 'int_pending')");
        $stmt->execute([$tenant_id, $project_id, $_POST['post_title'], $_POST['post_type'], $_POST['post_caption']]);
        $pid = $pdo->lastInsertId();
        try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Criou post: ".$_POST['post_title'], 'upload']); } catch(Exception $e){}
        
        if (isset($_FILES['midias'])) {
            $uploadDir = '../../uploads/'; if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $total = count($_FILES['midias']['name']);
            
            for ($i = 0; $i < $total; $i++) {
                if ($_FILES['midias']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['midias']['name'][$i], PATHINFO_EXTENSION));
                    $tempFile = $_FILES['midias']['tmp_name'][$i];
                    
                    $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm']);
                    $finalName = uniqid().'_'.$i . ($isVideo ? '.mp4' : '.' . $ext);
                    $destPath = $uploadDir . $finalName;
                    $finalExt = $isVideo ? 'mp4' : $ext;
                    
                    $success = false;

                    if ($isVideo) {
                        $success = otimizarVideoParaWeb($tempFile, $destPath) ? true : move_uploaded_file($tempFile, $destPath);
                    } else {
                        $success = move_uploaded_file($tempFile, $destPath);
                    }

                    if ($success) {
                        $pdo->prepare("INSERT INTO project_files (tenant_id, project_id, deliverable_id, uploader_id, file_name, file_path, file_type) VALUES (?,?,?,?,?,?,?)")
                            ->execute([$tenant_id, $project_id, $pid, $_SESSION['user_id'], $_FILES['midias']['name'][$i], $finalName, $finalExt]);
                    }
                }
            }
        }
        header("Location: detalhes.php?id=$project_id&tab=galeria&sort=$filtro"); exit;
    }
    
    // Status
    if (isset($_POST['update_post_feedback'])) {
        $intStatus = $_POST['internal_status'] ?? 'int_pending';
        $cliStatus = $_POST['approval_status'] ?? 'pending';

        $pdo->prepare("UPDATE project_deliverables SET internal_status=?, internal_notes=?, approval_status=? WHERE id=?")
            ->execute([$intStatus, $_POST['internal_notes'], $cliStatus, $_POST['deliverable_id']]);
            
        try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Atualizou status post #".$_POST['deliverable_id'], 'status']); } catch(Exception $e){}
        header("Location: detalhes.php?id=$project_id&tab=galeria&sort=$filtro"); exit;
    }
    
    // Edit Content
    if (isset($_POST['action']) && $_POST['action'] === 'edit_deliverable_content') {
        $editId = $_POST['edit_id']; $editTitle = $_POST['edit_title'];
        $resubmit = isset($_POST['resubmit_approval']); 
        $sql = "UPDATE project_deliverables SET title = ?, caption = ?" . (isset($_POST['resubmit_approval']) ? ", approval_status = 'pending', internal_status = 'int_approval', feedback = NULL" : "") . " WHERE id = ?"; 
        $pdo->prepare($sql)->execute([$_POST['edit_title'], $_POST['edit_caption'], $_POST['edit_id']]);
        
        if(isset($_POST['delete_files']) && is_array($_POST['delete_files'])) {
            foreach ($_POST['delete_files'] as $fileId) {
                $stmtGet = $pdo->prepare("SELECT file_path FROM project_files WHERE id = ?"); $stmtGet->execute([$fileId]);
                $path = $stmtGet->fetchColumn();
                if ($path) { @unlink("../../uploads/" . $path); $pdo->prepare("DELETE FROM project_files WHERE id = ?")->execute([$fileId]); }
            }
        }
        
        if (!empty($_FILES['new_files']['name'][0])) {
            $uploadDir = '../../uploads/';
            foreach ($_FILES['new_files']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['new_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['new_files']['name'][$key], PATHINFO_EXTENSION));
                    $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'mkv', 'webm']);
                    $finalName = uniqid().'_'.time().'_'.$key . ($isVideo ? '.mp4' : '.' . $ext);
                    $destPath = $uploadDir . $finalName;
                    $finalExt = $isVideo ? 'mp4' : $ext;
                    $ok = $isVideo ? (otimizarVideoParaWeb($tmpName, $destPath)?:move_uploaded_file($tmpName, $destPath)) : move_uploaded_file($tmpName, $destPath);
                    if($ok) {
                        $pdo->prepare("INSERT INTO project_files (tenant_id, project_id, deliverable_id, uploader_id, file_name, file_path, file_type) VALUES (?,?,?,?,?,?,?)")
                            ->execute([$tenant_id, $project_id, $editId, $_SESSION['user_id'], $_FILES['new_files']['name'][$key], $finalName, $finalExt]);
                    }
                }
            }
        }
        try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Editou post", 'edit']); } catch(Exception $e){}
        header("Location: detalhes.php?id=$project_id&tab=galeria&sort=$filtro&msg=edited"); exit;
    }

    // Tabs Order
    if (isset($_POST['action']) && $_POST['action'] === 'reorder_tabs') {
        $pdo->prepare("UPDATE projects SET tabs_order = ? WHERE id = ? AND tenant_id = ?")->execute([$_POST['order'], $project_id, $tenant_id]); echo "OK"; exit;
    }
    
    // --- CALENDÁRIO POST ---
    if (isset($_POST['save_calendar_event'])) {
        $eid = $_POST['event_id'];
        if ($eid) $pdo->prepare("UPDATE project_calendar SET post_date=?, title=?, platform=? WHERE id=?")->execute([$_POST['event_date'], $_POST['event_title'], $_POST['event_platform'], $eid]);
        else $pdo->prepare("INSERT INTO project_calendar (tenant_id, project_id, post_date, title, platform) VALUES (?,?,?,?,?)")->execute([$tenant_id, $project_id, $_POST['event_date'], $_POST['event_title'], $_POST['event_platform']]);
        try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Editou calendário", 'calendar']); } catch(Exception $e){}
        header("Location: detalhes.php?id=$project_id&tab=calendario&m=$month&y=$year"); exit;
    }
    if (isset($_POST['delete_calendar_event'])) {
        $pdo->prepare("DELETE FROM project_calendar WHERE id=?")->execute([$_POST['event_id']]);
        header("Location: detalhes.php?id=$project_id&tab=calendario&m=$month&y=$year"); exit;
    }
    
    // Task Sort
    if (isset($_POST['action']) && $_POST['action'] === 'reorder_tasks') {
        $order = json_decode($_POST['order']); if (is_array($order)) { foreach ($order as $pos => $tid) $pdo->prepare("UPDATE tasks SET sort_order = ? WHERE id = ? AND tenant_id = ?")->execute([$pos, $tid, $tenant_id]); } echo "OK"; exit;
    }
    
    // Status Rapido
    if (isset($_POST['quick_update_status'])) { $pdo->prepare("UPDATE projects SET status=? WHERE id=?")->execute([$_POST['project_status'], $project_id]); header("Location: detalhes.php?id=$project_id"); exit; }
    
    // Capa
    if (isset($_FILES['cover_image'])) {
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION)); $newName = 'cover_'.$project_id.'_'.time().'.'.$ext;
        move_uploaded_file($_FILES['cover_image']['tmp_name'], '../../uploads/covers/'.$newName);
        $pdo->prepare("UPDATE projects SET cover_image=? WHERE id=?")->execute([$newName, $project_id]); header("Location: detalhes.php?id=$project_id"); exit;
    }
    
    // Link Token
    if (isset($_POST['generate_link'])) { $tk = bin2hex(random_bytes(16)); $pdo->prepare("UPDATE projects SET share_token=? WHERE id=?")->execute([$tk, $project_id]); header("Location: detalhes.php?id=$project_id"); exit; }
}

// --- GET ACTIONS ---
if (isset($_GET['del_post'])) {
    $pid = $_GET['del_post'];
    $fs = $pdo->prepare("SELECT file_path FROM project_files WHERE deliverable_id=?"); $fs->execute([$pid]);
    foreach($fs as $f) @unlink('../../uploads/'.$f['file_path']);
    $pdo->prepare("DELETE FROM project_files WHERE deliverable_id=?")->execute([$pid]);
    $pdo->prepare("DELETE FROM project_deliverables WHERE id=?")->execute([$pid]);
    try { $pdo->prepare("INSERT INTO project_activity_logs (tenant_id, project_id, user_id, message, type) VALUES (?, ?, ?, ?, ?)")->execute([$tenant_id, $project_id, $_SESSION['user_id'], "Excluiu post", 'delete']); } catch(Exception $e){}
    header("Location: detalhes.php?id=$project_id&tab=galeria&sort=$filtro"); exit;
}
if (isset($_GET['del_task'])) { 
    $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$_GET['del_task']]); 
    $pdo->prepare("DELETE FROM task_subtasks WHERE task_id=?")->execute([$_GET['del_task']]);
    header("Location: detalhes.php?id=$project_id&tab=geral"); exit; 
}

// --- BUSCAS ---
$projeto = $pdo->prepare("SELECT p.*, c.name as client_name, c.profile_pic FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.id = ?"); 
$projeto->execute([$project_id]); $projeto = $projeto->fetch();
if(!$projeto) die("Projeto não encontrado.");

$stmtBrief = $pdo->prepare("SELECT * FROM project_briefings WHERE project_id = ?");
$stmtBrief->execute([$project_id]); $briefing = $stmtBrief->fetch(PDO::FETCH_ASSOC);

$tarefas = $pdo->prepare("SELECT * FROM tasks WHERE project_id = ? ORDER BY sort_order ASC"); $tarefas->execute([$project_id]); $tarefas = $tarefas->fetchAll();

// --- CORREÇÃO SUBTAREFAS (ORDER BY task_id) ---
$all_subtasks = [];
if(count($tarefas) > 0) {
    try {
        $allSubStmt = $pdo->prepare("SELECT task_id, id, title, is_completed, created_at FROM task_subtasks WHERE task_id IN (SELECT id FROM tasks WHERE project_id = ?)");
        $allSubStmt->execute([$project_id]);
        $all_subtasks = $allSubStmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    } catch(Exception $e){}
}

$statusList = $pdo->prepare("SELECT * FROM task_statuses WHERE tenant_id = ?"); 
$statusList->execute([$tenant_id]); $statuses = $statusList->fetchAll(); 
if (count($statuses) == 0) $statuses = [['name'=>'Pendente','color'=>'#f1f5f9'], ['name'=>'Produção','color'=>'#dbeafe'], ['name'=>'Aprovação','color'=>'#ffedd5'], ['name'=>'Concluído','color'=>'#dcfce7']];
$statusColors = []; foreach($statuses as $s) $statusColors[$s['name']] = $s['color'];

$postsStmt = $pdo->prepare("SELECT * FROM project_deliverables WHERE project_id = ? ORDER BY $sqlOrder"); $postsStmt->execute([$project_id]); $all_posts = $postsStmt->fetchAll();
$filesStmt = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ?"); $filesStmt->execute([$project_id]); $all_files = $filesStmt->fetchAll(); $post_files = []; foreach($all_files as $f) { if($f['deliverable_id']) $post_files[$f['deliverable_id']][] = $f; }
$cal = $pdo->prepare("SELECT * FROM project_calendar WHERE project_id = ? AND MONTH(post_date) = ? AND YEAR(post_date) = ?"); $cal->execute([$project_id, $month, $year]); $events = $cal->fetchAll();
$eventsByDate = []; foreach($events as $ev) { $eventsByDate[$ev['post_date']][] = $ev; }

// --- DADOS ANALYTICS (GRÁFICOS) ---
$analyticsHistory = $pdo->prepare("SELECT * FROM project_analytics WHERE project_id = ? ORDER BY month_year ASC");
$analyticsHistory->execute([$project_id]);
$allAnalytics = $analyticsHistory->fetchAll(PDO::FETCH_ASSOC);

$analytics = $pdo->prepare("SELECT * FROM project_analytics WHERE project_id = ? ORDER BY month_year DESC LIMIT 1");
$analytics->execute([$project_id]);
$currentAnalytics = $analytics->fetch(PDO::FETCH_ASSOC);

// Preparar arrays JS
$chartLabels = []; $chartReach = []; $chartEngage = []; $chartFollows = [];
foreach($allAnalytics as $row) {
    $dateObj = DateTime::createFromFormat('Y-m', $row['month_year']);
    $chartLabels[] = $dateObj ? $dateObj->format('M/y') : $row['month_year'];
    // Remove caracteres não numéricos para o gráfico
    $chartReach[] = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $row['reach']));
    $chartEngage[] = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $row['engagement']));
    $chartFollows[] = (float)preg_replace('/[^0-9.]/', '', str_replace(',', '.', $row['new_followers']));
}

// LOGS
$logsStmt = $pdo->prepare("SELECT l.*, u.name as username FROM project_activity_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.project_id = ? ORDER BY l.created_at DESC LIMIT 20");
$logsStmt->execute([$project_id]);
$recentLogs = $logsStmt->fetchAll();

$active_tab = $_GET['tab'] ?? 'geral';
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?"https":"http") . "://" . $_SERVER['HTTP_HOST'] . explode('/modules', $_SERVER['REQUEST_URI'])[0];
$cover_url = $projeto['cover_image'] ? "../../uploads/covers/" . $projeto['cover_image'] : "https://via.placeholder.com/1000x200?text=Sem+Capa";

function getInitials($name) { $parts = explode(' ', trim($name)); $ret = strtoupper($parts[0][0]); if (count($parts) > 1) $ret .= strtoupper(end($parts)[0]); return $ret; }
function getProjectStatusStyle($st) { if($st=='pending') return 'background:#fef3c7; color:#92400e; border:1px solid #fcd34d'; if($st=='in_progress') return 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd'; if($st=='approval') return 'background:#ffedd5; color:#9a3412; border:1px solid #fdba74'; if($st=='completed') return 'background:#dcfce7; color:#166534; border:1px solid #86efac'; return 'background:var(--bg-input); color:var(--text-main); border:1px solid var(--border)'; }

// --- TABS (ADICIONADA "RESULTADOS") ---
$tabs_available = ['geral' => '📋 Geral', 'upload' => '📤 Novo Post', 'galeria' => '🖼️ Feed / Gestão', 'calendario' => '📅 Calendário', 'resultados' => '📊 Resultados', 'briefing' => '📄 Briefing'];
$saved_order = json_decode($projeto['tabs_order'] ?? '[]', true);
$final_tabs = [];
if(is_array($saved_order)) { foreach($saved_order as $key) { if(isset($tabs_available[$key])) { $final_tabs[$key] = $tabs_available[$key]; unset($tabs_available[$key]); } } }
foreach($tabs_available as $key => $label) { $final_tabs[$key] = $label; }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($projeto['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* LIGHT MODE */
        :root { --bg-card: #ffffff; --bg-body-alt: #f8fafc; --text-muted-alt: #64748b; --border-color: #e2e8f0; --input-bg: #ffffff; --text-main: #333333; --accent-color: #4338ca; }
        [data-theme="dark"] { --bg-card: #27272a; --bg-body-alt: #18181b; --text-muted-alt: #a1a1aa; --border-color: #3f3f46; --input-bg: #27272a; --text-main: #f4f4f5; }
        body { color: var(--text-main); }
        .project-card-wrapper { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .project-header-info { display:flex; justify-content:space-between; align-items:center; padding: 20px; background: var(--bg-card); }
        [data-theme="dark"] .project-header-info { background: #27272a !important; color: #f4f4f5; }
        .project-cover { height: 200px; width: 100%; background-size: cover; background-position: center; position: relative; }
        .btn-change-cover { position: absolute; bottom: 15px; right: 15px; background: rgba(0,0,0,0.6); color: white; padding: 8px 16px; border-radius: 20px; cursor: pointer; font-size: 0.8rem; backdrop-filter: blur(4px); border:1px solid rgba(255,255,255,0.3); }
        .tab-nav { display: flex; border-bottom: 1px solid var(--border-color); background: var(--bg-body-alt); padding: 0 2rem; gap: 10px; overflow-x: auto; }
        .tab-btn { padding: 15px 15px; background: none; border: none; font-size: 0.95rem; font-weight: 500; color: var(--text-muted-alt); cursor: pointer; border-bottom: 3px solid transparent; transition: color 0.2s; white-space: nowrap; user-select: none; }
        .tab-btn.active { color: var(--text-main); border-bottom-color: var(--text-main); font-weight: 700; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-input, textarea.form-input { background-color: var(--input-bg) !important; color: var(--text-main) !important; border: 1px solid var(--border-color) !important; }
        .status-quick-select { padding: 6px 30px 6px 15px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; cursor: pointer; appearance: none; background-repeat: no-repeat; background-position: right 10px center; background-size: 8px; text-align: center; outline: none; min-width: 130px; }
        .item-card { background: var(--bg-card); border-bottom: 1px solid var(--border-color); padding: 15px; margin-bottom: 0; color: var(--text-main); display: flex; flex-direction: column; }
        .task-main-row { display: flex; align-items: center; justify-content: space-between; width: 100%; }
        .task-left { display: flex; align-items: center; gap: 10px; flex: 1; }
        .drag-handle { cursor: grab; color: var(--text-muted-alt); font-size: 1.2rem; }
        .task-title { font-weight: 500; font-size: 1rem; color: var(--text-main); }
        .task-done-style { text-decoration: line-through; opacity: 0.6; }
        .task-right { display: flex; align-items: center; gap: 10px; }
        .status-pill-select { padding: 5px 0; border-radius: 6px; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 700; outline: none; appearance: none; text-align: center; text-transform: uppercase; width: 120px; }
        .btn-delete { color: #ef4444; opacity: 0.6; cursor: pointer; text-decoration: none; font-size: 1.1rem; border:none; background:none; }
        .edit-input-inline { display: none; padding: 5px; font-size: 1rem; border: 1px solid var(--accent-color); border-radius: 4px; background: var(--input-bg); color: var(--text-main); width: 100%; max-width: 300px; }
        .btn-edit-pencil { background: none; border: none; cursor: pointer; font-size: 0.9rem; opacity: 0.5; transition: 0.2s; margin-left: 5px; }
        .btn-edit-pencil:hover { opacity: 1; transform: scale(1.2); }
        .btn-edit-pencil-small { background: none; border: none; cursor: pointer; font-size: 0.8rem; opacity: 0.4; margin-left: 5px; }
        .btn-edit-pencil-small:hover { opacity: 1; }
        .task-meta-row { display: flex; align-items: center; gap: 15px; margin-top: 8px; padding-left: 28px; }
        .progress-container { flex: 1; max-width: 150px; background: var(--border-color); height: 4px; border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: #10b981; width: 0%; transition: width 0.3s; }
        .btn-toggle-subs { background: var(--bg-body-alt); border: 1px solid var(--border-color); padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; color: var(--text-muted-alt); cursor: pointer; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-toggle-subs:hover { border-color: var(--text-main); color: var(--text-main); }
        .btn-toggle-subs.active { background: var(--text-main); color: var(--bg-card); border-color: var(--text-main); }
        .subtasks-wrapper { display: none; margin-top: 10px; width: 100%; }
        .subtasks-wrapper.open { display: block; animation: slideDown 0.2s ease-out; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        .subtask-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 0 8px 40px; border-bottom: 1px dashed var(--border-color); }
        .sub-check { margin-right: 10px; cursor: pointer; width: 14px; height: 14px; accent-color: var(--accent-color); }
        .sub-text { font-size: 0.9rem; color: #000; flex: 1; text-align: left; font-weight: 500; }
        [data-theme="dark"] .sub-text { color: #f4f4f5 !important; }
        .sub-completed { text-decoration: line-through; opacity: 0.6; }
        .btn-sub-del { background: none; border: none; color: var(--text-muted-alt); font-size: 0.9rem; cursor: pointer; padding: 0 10px; }
        .sub-input-row { display: flex; gap: 8px; margin-top: 10px; padding-left: 40px; }
        .sub-input { padding: 6px 10px; font-size: 0.85rem; flex: 1; border-radius: 4px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-main); }
        .btn-sub-add { padding: 0 15px; font-size: 1rem; border-radius: 4px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; background-color: #000 !important; color: #fff !important; }
        [data-theme="dark"] .btn-sub-add { background-color: #fff !important; color: #000 !important; }
        .bf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .bf-full { grid-column: span 2; }
        .form-textarea { min-height: 120px; resize: vertical; line-height: 1.6; }
        .view-box { margin-bottom: 20px; }
        .view-label { font-size: 0.85rem; font-weight: bold; color: var(--accent-color); text-transform: uppercase; margin-bottom: 5px; display: block; letter-spacing: 0.5px; }
        .view-content { font-size: 1rem; color: var(--text-main); line-height: 1.6; white-space: pre-wrap; background: var(--bg-body-alt); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); }
        .insta-grid-wrapper { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; padding-top: 20px; }
        .insta-post-card { background: var(--bg-card); border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); transition: transform 0.2s; position: relative; cursor: pointer; }
        .insta-cover-box { width: 100%; aspect-ratio: 1/1; background: #000; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .insta-cover-img, .insta-cover-video { width: 100%; height: 100%; object-fit: cover; }
        .badge-stack { position: absolute; top: 10px; left: 10px; display: flex; flex-direction: column; gap: 4px; z-index: 10; }
        .status-tag { padding: 4px 8px; border-radius: 4px; font-size: 0.6rem; font-weight: 800; color: white; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.3); }
        
        /* STATUS COLORS FIXED */
        .st-int-pending { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .st-int-working { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .st-int-approval { background: #fef08a; color: #854d0e; border: 1px solid #fde047; }
        .st-int-to-change { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .st-int-approved { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        
        .st-cli-approved { background: #22c55e; color:#fff; }
        .st-cli-changes { background: #ef4444; color:#fff; }
        .st-cli-pending { background: #64748b; color:#fff; opacity:0.7; }
        
        .type-indicator { position: absolute; top: 10px; right: 10px; color: white; background: rgba(0,0,0,0.5); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; z-index: 2; }
        .btn-trash-card { position: absolute; bottom: 8px; right: 8px; background: rgba(255,0,0,0.7); color: white; border: none; width: 28px; height: 28px; border-radius: 4px; cursor: pointer; z-index: 5; display: flex; align-items: center; justify-content: center; }
        .btn-edit-card { position: absolute; bottom: 8px; right: 42px; background: rgba(0,0,0,0.7); color: white; border: none; width: 28px; height: 28px; border-radius: 4px; cursor: pointer; z-index: 5; display: flex; align-items: center; justify-content: center; }
        .insta-meta { padding: 10px; }
        .insta-meta-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-main); }
        .modal-overlay-manage { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; align-items: center; justify-content: center; }
        .modal-manage-content { width: 95%; max-width: 1200px; height: 85vh; display: flex; background: var(--bg-card); border-radius: 12px; overflow: hidden; }
        .manage-preview-area { flex: 2; background: #000; display: flex; align-items: center; justify-content: center; position: relative; }
        .media-viewer { max-width: 100%; max-height: 100%; object-fit: contain; }
        .nav-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.2); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 100; transition: 0.2s; color: white; }
        .nav-btn:hover { background: #fff; color: #000; transform: translateY(-50%) scale(1.1); }
        .nav-btn svg { width: 24px; height: 24px; stroke: currentColor; stroke-width: 2.5; fill: none; }
        #phPrev { left: 20px; } #phNext { right: 20px; }
        .manage-controls-area { flex: 1; min-width: 350px; background: var(--bg-card); padding: 0; border-left: 1px solid var(--border-color); display: flex; flex-direction: column; color: var(--text-main); position: relative; }
        .close-manage { position: absolute; top: 15px; right: 15px; color: white; font-size: 2rem; cursor: pointer; z-index: 100; }
        .status-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 10px; }
        .btn-status-action { padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); cursor: pointer; font-weight: 600; font-size: 0.8rem; text-align: center; transition: 0.2s; opacity: 0.7; }
        .btn-status-action:hover { opacity: 1; transform: translateY(-1px); }
        .btn-status-action.active { opacity: 1; border-color: var(--accent-color); box-shadow: 0 2px 8px rgba(0,0,0,0.1); transform: scale(1.02); }
        .bt-app { background: #dcfce7; color: #166534; } .bt-cha { background: #fee2e2; color: #991b1b; } .bt-pen { background: #f1f5f9; color: #475569; } .bt-work { background: #dbeafe; color: #1e40af; } .bt-approval { background: #fef08a; color: #854d0e; } .bt-neutral { background: #e2e8f0; color: #334155; } 
        .mode-tabs { display: flex; border-bottom: 1px solid var(--border-color); background: var(--bg-body-alt); }
        .mode-btn { flex: 1; padding: 15px; border: none; background: none; font-weight: 600; cursor: pointer; color: var(--text-muted-alt); border-bottom: 3px solid transparent; }
        .mode-btn.active { color: var(--text-main); border-bottom-color: var(--accent-color); background: var(--bg-card); }
        .mode-content { padding: 25px; flex: 1; display: none; overflow-y: auto; }
        .mode-content.active { display: flex; flex-direction: column; }
        .feedback-box { background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; font-size: 0.9rem; margin-top: 5px; }
        .modal-footer-sticky { padding: 20px; background: var(--bg-card); border-top: 1px solid var(--border-color); position: sticky; bottom: 0; z-index: 10; box-shadow: 0 -4px 10px rgba(0,0,0,0.05); }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-top: 20px; }
        .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .cal-day { background: var(--bg-body-alt); border: 1px solid var(--border-color); border-radius: 8px; min-height: 120px; padding: 10px; position: relative; transition: 0.2s; }
        .cal-day:hover { border-color: var(--accent-color); }
        .cal-num { font-weight: 700; font-size: 0.9rem; color: var(--text-muted-alt); margin-bottom: 5px; }
        .cal-add-layer { position: absolute; inset: 0; cursor: pointer; z-index: 1; }
        .cal-add-btn { position: absolute; top: 5px; right: 5px; opacity: 0; cursor: pointer; background: var(--accent-color); color: white; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; z-index: 2; }
        .cal-day:hover .cal-add-btn { opacity: 1; }
        .cal-event { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; padding: 5px 8px; margin-bottom: 5px; font-size: 0.75rem; cursor: pointer; position: relative; z-index: 5; display: flex; align-items: center; gap: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); color: var(--text-main); transition: 0.2s; }
        .plat-dot { width: 8px; height: 8px; border-radius: 50%; }
        .plat-insta { background: #E1306C; } .plat-linkedin { background: #0077B5; }
        .client-avatar-header { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 1px solid var(--border-color); background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #4338ca; }
        .client-avatar-header img { width: 100%; height: 100%; object-fit: cover; }
        .cal-day.drag-over { background-color: rgba(67, 56, 202, 0.1); border-color: var(--accent-color); border-style: dashed; }
        
        /* LOG TIMELINE */
        .log-list { margin-top: 20px; border-left: 2px solid var(--border-color); padding-left: 20px; }
        .log-item { margin-bottom: 15px; position: relative; }
        .log-item::before { content: ''; position: absolute; left: -25px; top: 5px; width: 8px; height: 8px; background: var(--accent-color); border-radius: 50%; }
        .log-meta { font-size: 0.75rem; color: var(--text-muted-alt); }
        .log-msg { font-size: 0.9rem; color: var(--text-main); }
        
        /* CHARTS & PDF */
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 30px; }
        .chart-card { background: var(--bg-card); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); height: 350px; display: flex; flex-direction: column; justify-content: space-between; }
        .chart-canvas-wrapper { flex: 1; position: relative; width: 100%; min-height: 0; }
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; background: white; }
            .no-print { display: none !important; }
        }

        @media(max-width: 900px) { .modal-manage-content { flex-direction: column; height: 100%; border-radius: 0; } .manage-preview-area { flex: none; height: 50vh; } .manage-controls-area { flex: 1; } }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <div style="margin-bottom: 1.5rem;"><a href="projetos.php" style="color: var(--text-muted-alt);">&larr; Voltar</a></div>

        <div class="project-card-wrapper" style="max-width: 1200px; margin: 0 auto;">
            <div class="project-cover" style="background-image: url('<?php echo $cover_url; ?>');">
                <form method="POST" enctype="multipart/form-data"><label class="btn-change-cover">📷 Alterar Capa <input type="file" name="cover_image" onchange="this.form.submit()" style="display: none;"></label></form>
            </div>

            <div class="project-header-info">
                <div>
                    <h1 style="font-size: 1.8rem; margin:0; color: var(--text-main);"><?php echo htmlspecialchars($projeto['title']); ?></h1>
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                        <p style="color: var(--text-muted-alt); margin:0;">Cliente: <strong><?php echo htmlspecialchars($projeto['client_name']); ?></strong></p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <form method="POST"><input type="hidden" name="quick_update_status" value="1">
                        <select name="project_status" class="status-quick-select" style="<?php echo getProjectStatusStyle($projeto['status']); ?>" onchange="this.form.submit()">
                            <option value="pending" <?php echo $projeto['status']=='pending'?'selected':''; ?>>Pendente</option>
                            <option value="in_progress" <?php echo $projeto['status']=='in_progress'?'selected':''; ?>>Em Produção</option>
                            <option value="approval" <?php echo $projeto['status']=='approval'?'selected':''; ?>>Aprovação</option>
                            <option value="completed" <?php echo $projeto['status']=='completed'?'selected':''; ?>>Concluído</option>
                        </select>
                    </form>
                    <a href="editar_projeto.php?id=<?php echo $project_id; ?>" class="btn-primary" style="background:var(--bg-card); color:var(--text-main); border:1px solid var(--border-color);">⚙️ Editar</a>
                </div>
            </div>

            <div class="tab-nav" id="tabNavContainer">
                <?php foreach($final_tabs as $key => $label): ?>
                    <button class="tab-btn <?php echo $active_tab==$key?'active':''; ?>" data-id="<?php echo $key; ?>" onclick="openTab('<?php echo $key; ?>')"><?php echo $label; ?></button>
                <?php endforeach; ?>
            </div>

            <div class="project-body">
                <div class="body-main">
                    
                    <div id="tab-geral" class="tab-content <?php echo $active_tab=='geral'?'active':''; ?>">
                        <div style="margin-bottom: 2rem; padding: 20px;">
                            <h3 style="margin-bottom: 10px; color: var(--text-main);">Descrição</h3>
                            <div style="color: var(--text-muted-alt); line-height: 1.6;"><?php echo nl2br(htmlspecialchars($projeto['description'] ?: 'Sem descrição.')); ?></div>
                        </div>
                        <div style="padding: 0 20px 20px 20px;">
                            <h3 style="color: var(--text-main); margin-bottom: 15px;">Checklist</h3>
                            <div id="taskList"><?php foreach ($tarefas as $task) echo renderTaskCard($task, $all_subtasks[$task['id']]??[], $statuses, $statusColors); ?></div>
                            <div style="margin-top: 20px; display: flex; gap: 10px;">
                                <input type="text" id="main-task-input" class="form-input" placeholder="+ Adicionar nova tarefa principal..." onkeypress="if(event.key==='Enter') addMainTask()">
                                <button type="button" class="btn-primary" onclick="addMainTask()">Adicionar</button>
                            </div>
                            
                            <div style="margin-top:50px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-bottom:15px;">
                                     <h4 style="margin:0; color:var(--text-main);">Histórico de Atividades</h4>
                                     <?php if(count($recentLogs) > 0): ?>
                                        <form method="POST" onsubmit="return confirm('Limpar todo o histórico deste projeto?');" style="margin:0;">
                                            <input type="hidden" name="clear_history" value="1">
                                            <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:0.85rem; font-weight:600;">🗑️ Limpar</button>
                                        </form>
                                     <?php endif; ?>
                                </div>
                                <div class="log-list">
                                    <?php if(count($recentLogs) > 0): ?>
                                        <?php foreach($recentLogs as $log): ?>
                                            <div class="log-item">
                                                <div class="log-msg">
                                                    <strong><?php echo htmlspecialchars($log['username'] ?? 'Sistema'); ?></strong> 
                                                    <?php echo htmlspecialchars($log['message']); ?>
                                                </div>
                                                <div class="log-meta"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="color:var(--text-muted-alt);">Nenhuma atividade registrada.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tab-briefing" class="tab-content <?php echo $active_tab=='briefing'?'active':''; ?>">
                        <div style="padding:2rem; background:var(--bg-card); border-radius:16px; border:1px solid var(--border-color);">
                            <div id="briefing-view" style="display: <?php echo !empty($briefing) ? 'block' : 'none'; ?>">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                                    <h3 style="margin:0; color:var(--text-main); display:flex; align-items:center; gap:10px;"><span style="font-size:1.5rem;">📄</span> Briefing Criativo</h3>
                                    <button onclick="toggleBriefingMode('edit')" class="btn-primary" style="background:var(--bg-body-alt); color:var(--text-main); border:1px solid var(--border-color);">✏️ Editar Briefing</button>
                                </div>
                                <div class="bf-grid">
                                    <div class="bf-full view-box"><span class="view-label">Objetivo Principal</span><div class="view-content"><?php echo nl2br(htmlspecialchars($briefing['objective']??'')); ?></div></div>
                                    <div class="view-box"><span class="view-label">Público-Alvo</span><div class="view-content"><?php echo nl2br(htmlspecialchars($briefing['target_audience']??'')); ?></div></div>
                                    <div class="view-box"><span class="view-label">Formatos</span><div class="view-content"><?php echo nl2br(htmlspecialchars($briefing['formats']??'')); ?></div></div>
                                    <div class="bf-full view-box"><span class="view-label">Diretrizes Visuais</span><div class="view-content"><?php echo nl2br(htmlspecialchars($briefing['visual_style']??'')); ?></div></div>
                                    <div class="bf-full view-box"><span class="view-label">Conteúdo Obrigatório</span><div class="view-content"><?php echo nl2br(htmlspecialchars($briefing['required_content']??'')); ?></div></div>
                                    <div class="bf-full view-box"><span class="view-label">Referências</span><div class="view-content"><?php echo nl2br(htmlspecialchars($briefing['references_links']??'')); ?></div></div>
                                </div>
                            </div>
                            <div id="briefing-edit" style="display: <?php echo empty($briefing) ? 'block' : 'none'; ?>">
                                <form method="POST">
                                    <input type="hidden" name="save_briefing" value="1">
                                    <div class="bf-grid">
                                        <div class="bf-full"><label class="form-label" style="color:var(--text-main);">Objetivo Principal</label><textarea name="objective" class="form-input form-textarea"><?php echo htmlspecialchars($briefing['objective']??''); ?></textarea></div>
                                        <div><label class="form-label" style="color:var(--text-main);">Público-Alvo</label><textarea name="target_audience" class="form-input form-textarea"><?php echo htmlspecialchars($briefing['target_audience']??''); ?></textarea></div>
                                        <div><label class="form-label" style="color:var(--text-main);">Formatos</label><textarea name="formats" class="form-input form-textarea"><?php echo htmlspecialchars($briefing['formats']??''); ?></textarea></div>
                                        <div class="bf-full"><label class="form-label" style="color:var(--text-main);">Diretrizes Visuais</label><textarea name="visual_style" class="form-input form-textarea"><?php echo htmlspecialchars($briefing['visual_style']??''); ?></textarea></div>
                                        <div class="bf-full"><label class="form-label" style="color:var(--text-main);">Conteúdo Obrigatório</label><textarea name="required_content" class="form-input form-textarea" style="min-height:150px;"><?php echo htmlspecialchars($briefing['required_content']??''); ?></textarea></div>
                                        <div class="bf-full"><label class="form-label" style="color:var(--text-main);">Referências</label><input type="text" name="references_links" class="form-input" value="<?php echo htmlspecialchars($briefing['references_links']??''); ?>"></div>
                                    </div>
                                    <div style="margin-top:30px; text-align:right;"><button type="submit" class="btn-primary" style="padding:12px 25px;">💾 Salvar Briefing</button></div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div id="tab-upload" class="tab-content <?php echo $active_tab=='upload'?'active':''; ?>">
                        <div style="max-width: 600px; margin: 0 auto; padding-top: 2rem;">
                            <h3 style="text-align:center; margin-bottom: 2rem; color: var(--text-main);">Criar Novo Post</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="create_post" value="1">
                                <div style="margin-bottom: 15px;"><label class="form-label" style="color: var(--text-main);">Título</label><input type="text" name="post_title" class="form-input" required></div>
                                <div style="margin-bottom: 15px;"><label class="form-label" style="color: var(--text-main);">Tipo</label><select name="post_type" class="form-input"><option value="image">Imagem</option><option value="carousel">Carrossel</option><option value="video">Vídeo</option></select></div>
                                <label class="upload-zone" style="background: var(--bg-card); border-color: var(--border-color);">
                                    <div class="upload-icon">☁️</div><strong style="font-size: 1.1rem; display:block; margin-bottom:0.5rem; color:var(--text-main);">Arquivos</strong>
                                    <input type="file" name="midias[]" style="display:none;" multiple required onchange="this.parentElement.style.borderColor = 'var(--text-main)'; this.parentElement.querySelector('strong').innerText = this.files.length + ' selecionados';">
                                </label>
                                <div style="margin-bottom: 1.5rem; margin-top: 1.5rem;"><label class="form-label" style="color: var(--text-main);">Legenda</label><textarea name="post_caption" class="form-input" placeholder="..." style="height: 100px;"></textarea></div>
                                <button type="submit" class="btn-primary" style="width: 100%; height: 50px; font-size: 1rem;">Publicar</button>
                            </form>
                        </div>
                    </div>

                    <div id="tab-calendario" class="tab-content <?php echo $active_tab=='calendario'?'active':''; ?>">
                        <div class="cal-header">
                            <h3 style="margin:0; text-transform:capitalize; color:var(--text-main);">
                                <?php 
                                    $meses = [1=>'Janeiro', 2=>'Fevereiro', 3=>'Março', 4=>'Abril', 5=>'Maio', 6=>'Junho', 7=>'Julho', 8=>'Agosto', 9=>'Setembro', 10=>'Outubro', 11=>'Novembro', 12=>'Dezembro'];
                                    echo $meses[(int)$month] . ' ' . $year;
                                ?>
                            </h3>
                            <div style="display:flex; gap:10px;">
                                <a href="?id=<?php echo $project_id; ?>&tab=calendario&m=<?php echo $m_raw-1; ?>&y=<?php echo $y_raw; ?>" class="btn-primary" style="padding:5px 15px;">&larr;</a>
                                <a href="?id=<?php echo $project_id; ?>&tab=calendario&m=<?php echo $m_raw+1; ?>&y=<?php echo $y_raw; ?>" class="btn-primary" style="padding:5px 15px;">&rarr;</a>
                            </div>
                        </div>
                        <div class="calendar-grid">
                            <div class="cal-day-name">Dom</div><div class="cal-day-name">Seg</div><div class="cal-day-name">Ter</div><div class="cal-day-name">Qua</div><div class="cal-day-name">Qui</div><div class="cal-day-name">Sex</div><div class="cal-day-name">Sab</div>
                            <?php 
                                for($i=0; $i<$dayOfWeek; $i++) echo "<div class='cal-day' style='opacity:0.5; background:transparent; border:none;'></div>";
                                for($day=1; $day<=$daysInMonth; $day++) {
                                    $cDate = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                                    echo "<div class='cal-day' ondragover='allowDrop(event)' ondrop='drop(event, \"$cDate\")'><div class='cal-add-layer' onclick=\"openCalModal('new', '$cDate')\"></div><div class='cal-num'>$day</div><div class='cal-add-btn'>+</div>";
                                    if(isset($eventsByDate[$cDate])) {
                                        foreach($eventsByDate[$cDate] as $ev) {
                                            $platClass = 'plat-insta'; if($ev['platform']=='LinkedIn') $platClass='plat-linkedin';
                                            echo "<div class='cal-event' draggable='true' ondragstart='drag(event, \"{$ev['id']}\")' onclick=\"openCalModal('edit', '$cDate', '{$ev['id']}', '".addslashes($ev['title'])."', '{$ev['platform']}'); event.stopPropagation();\">";
                                            // UTF-8 FIX NO TÍTULO DO EVENTO
                                            echo "<div class='plat-dot $platClass'></div> <span>".htmlspecialchars(mb_substr($ev['title'], 0, 15, 'UTF-8'))."...</span></div>";
                                        }
                                    }
                                    echo "</div>";
                                }
                            ?>
                        </div>
                    </div>

                    <div id="tab-galeria" class="tab-content <?php echo $active_tab=='galeria'?'active':''; ?>">
                        <div class="insta-grid-wrapper">
                            <?php foreach ($all_posts as $post): 
                                $files = $post_files[$post['id']] ?? []; if(empty($files)) continue;
                                $main = $files[0]; $ext = strtolower($main['file_type']); $isVideo = in_array($ext, ['mp4','mov','webm']); $isCarousel = count($files) > 1; 
                                
                                $clientSt = $post['approval_status'] ?? 'pending';
                                $internalSt = $post['internal_status'] ?? 'int_pending';
                                $jsonPostSafe = htmlspecialchars(json_encode($post), ENT_QUOTES, 'UTF-8');
                                $jsonFilesSafe = htmlspecialchars(json_encode($files), ENT_QUOTES, 'UTF-8');

                                // CORREÇÃO DE COR E TEXTO DO STATUS
                                $tagClass = 'st-int-pending'; $tagLabel = 'PENDENTE';
                                if($internalSt == 'int_working') { $tagClass='st-int-working'; $tagLabel='PRODUÇÃO'; }
                                if($internalSt == 'int_approval') { $tagClass='st-int-approval'; $tagLabel='APROVAÇÃO'; }
                                if($internalSt == 'int_to_change') { $tagClass='st-int-to-change'; $tagLabel='REFAZER'; }
                                if($internalSt == 'int_approved') { $tagClass='st-int-approved'; $tagLabel='APROVADO HEAD'; }
                                
                                $cliClass = 'st-cli-pending'; $cliLabel = 'AGUARD. CLIENTE';
                                if($clientSt == 'approved') { $cliClass='st-cli-approved'; $cliLabel='CLIENTE: OK'; }
                                if($clientSt == 'changes') { $cliClass='st-cli-changes'; $cliLabel='CLIENTE: ALTERAÇÃO'; }
                            ?>
                                <div class="insta-post-card" onclick='openManageModal(<?php echo $jsonPostSafe; ?>, <?php echo $jsonFilesSafe; ?>)'>
                                    <div class="insta-cover-box">
                                        <div class="badge-stack">
                                            <div class="status-tag <?php echo $tagClass; ?>"><?php echo $tagLabel; ?></div>
                                            <div class="status-tag <?php echo $cliClass; ?>"><?php echo $cliLabel; ?></div>
                                        </div>
                                        <?php if($isCarousel): ?><div class="type-indicator">❏</div><?php endif; ?>
                                        <?php if($isVideo): ?><div class="type-indicator">▶</div><?php endif; ?>
                                        <button onclick="event.stopPropagation(); if(confirm('Excluir este post?')) window.location.href='?id=<?php echo $project_id; ?>&tab=galeria&del_post=<?php echo $post['id']; ?>'" class="btn-trash-card">🗑️</button>
                                        <button onclick="event.stopPropagation(); openEditContentModal(<?php echo $post['id']; ?>, '<?php echo addslashes($post['title']); ?>', <?php echo $jsonFilesSafe; ?>)" class="btn-edit-card">✏️</button>
                                        <?php if($isVideo): ?><video src="../../uploads/<?php echo $main['file_path']; ?>" class="insta-cover-video" muted preload="metadata"></video><?php else: ?><img src="../../uploads/<?php echo $main['file_path']; ?>" class="insta-cover-img" loading="lazy"><?php endif; ?>
                                    </div>
                                    <div class="insta-meta"><div class="insta-meta-title"><?php echo htmlspecialchars($post['title']); ?></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div id="tab-resultados" class="tab-content <?php echo $active_tab=='resultados'?'active':''; ?>">
                        <div id="print-area" style="padding:2rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                                <h2 style="color:var(--text-main); margin:0;">Relatório de Performance</h2>
                                <button class="btn-primary no-print" onclick="window.print()" style="background:#4b5563; border:none;">🖨️ Gerar PDF</button>
                            </div>
                            <div style="margin-bottom:30px;">
                                <p style="margin:0; color:var(--text-muted-alt);">Cliente: <strong><?php echo htmlspecialchars($projeto['client_name']); ?></strong></p>
                                <p style="margin:5px 0 0 0; color:var(--text-muted-alt);">Mês: <strong><?php echo $currentAnalytics['month_year'] ?? date('Y-m'); ?></strong></p>
                            </div>
                            
                            <div class="no-print" style="max-width:800px; margin:0 auto 2rem; background:var(--bg-card); padding:2rem; border-radius:12px; border:1px solid var(--border-color);">
                                <h4 style="margin-top:0; margin-bottom:20px; color:var(--text-main);">Atualizar Dados</h4>
                                <form method="POST">
                                    <input type="hidden" name="save_analytics" value="1">
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                                        <div><label class="form-label" style="color:var(--text-main);">Mês Referência</label><input type="month" name="analytics_month" class="form-input" value="<?php echo date('Y-m'); ?>" required></div>
                                        <div><label class="form-label" style="color:var(--text-main);">Alcance</label><input type="text" name="reach" class="form-input" placeholder="Ex: 15400"></div>
                                        <div><label class="form-label" style="color:var(--text-main);">Engajamento</label><input type="text" name="engagement" class="form-input" placeholder="Ex: 8.5%"></div>
                                        <div><label class="form-label" style="color:var(--text-main);">Seguidores</label><input type="text" name="followers" class="form-input" placeholder="Ex: +120"></div>
                                    </div>
                                    <button class="btn-primary" style="margin-top:20px; width:100%;">Salvar Dados</button>
                                </form>
                            </div>
                            
                            <h3 style="color:var(--text-main); margin-top:40px;">Evolução</h3>
                            <div class="charts-grid">
                                <div class="chart-card">
                                    <div>
                                        <h4 style="margin:0 0 10px 0; color:var(--text-muted-alt);">Alcance</h4>
                                        <div style="font-size:2rem; font-weight:bold; margin-bottom:10px;"><?php echo $currentAnalytics['reach'] ?? '--'; ?></div>
                                    </div>
                                    <div class="chart-canvas-wrapper"><canvas id="reachChart"></canvas></div>
                                </div>
                                <div class="chart-card">
                                    <div>
                                        <h4 style="margin:0 0 10px 0; color:var(--text-muted-alt);">Engajamento</h4>
                                        <div style="font-size:2rem; font-weight:bold; margin-bottom:10px;"><?php echo $currentAnalytics['engagement'] ?? '--'; ?></div>
                                    </div>
                                    <div class="chart-canvas-wrapper"><canvas id="engageChart"></canvas></div>
                                </div>
                                <div class="chart-card">
                                    <div>
                                        <h4 style="margin:0 0 10px 0; color:var(--text-muted-alt);">Novos Seguidores</h4>
                                        <div style="font-size:2rem; font-weight:bold; margin-bottom:10px;"><?php echo $currentAnalytics['new_followers'] ?? '--'; ?></div>
                                    </div>
                                    <div class="chart-canvas-wrapper"><canvas id="followChart"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="body-sidebar">
                    <div class="link-box">
                        <label class="form-label">Link do Cliente</label>
                        <?php if ($projeto['share_token']): ?>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" value="<?php echo $base_url . '/public/ver.php?t=' . $projeto['share_token']; ?>" id="shareLink" readonly class="form-input" style="font-size: 0.8rem;">
                                <button onclick="copiarLink()" class="btn-primary" style="width: auto; padding: 0 10px;">📋</button>
                            </div>
                        <?php else: ?>
                            <form method="POST"><input type="hidden" name="generate_link" value="1"><button type="submit" class="btn-primary" style="width: 100%;">Gerar Link</button></form>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<div id="manageModal" class="modal-overlay-manage">
    <div class="close-manage" onclick="closeManageModal()">×</div>
    <div class="modal-manage-content">
        <div class="manage-preview-area" id="phContainer">
            <button class="nav-btn" id="phPrev" onclick="changeSlide(-1)"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"></polyline></svg></button>
            <button class="nav-btn" id="phNext" onclick="changeSlide(1)"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"></polyline></svg></button>
        </div>
        <div class="manage-controls-area">
            <div class="mode-tabs">
                <button class="mode-btn active" onclick="switchMode('team')">🔒 Equipe</button>
                <button class="mode-btn" onclick="switchMode('client')">👤 Cliente</button>
            </div>
            <form method="POST" id="manageForm" style="display:flex; flex-direction:column; height:100%;">
                <input type="hidden" name="update_post_feedback" value="1"><input type="hidden" name="deliverable_id" id="mDelId">
                <input type="hidden" name="internal_status" id="mInternalStatusInput">
                <input type="hidden" name="approval_status" id="mClientStatusInput">

                <div id="viewTeam" class="mode-content active">
                    <div class="manage-header"><div class="manage-title" style="font-weight:bold; font-size:1.1rem; margin-bottom:15px;">Controle Interno</div></div>
                    <label class="form-label" style="color:var(--text-main);">Status da Produção</label>
                    <div class="status-grid">
                        <div class="btn-status-action bt-neutral" onclick="setInternalStatus('int_pending')" id="btnIntPend">Pendente (Novo)</div>
                        <div class="btn-status-action bt-app" onclick="setInternalStatus('int_approved')" id="btnIntApp">Aprovado (Head)</div>
                        <div class="btn-status-action bt-approval" onclick="setInternalStatus('int_approval')" id="btnIntApproval">Em Aprovação</div>
                        <div class="btn-status-action bt-work" onclick="setInternalStatus('int_working')" id="btnIntWork">Em Alteração</div>
                        <div class="btn-status-action bt-cha" onclick="setInternalStatus('int_to_change')" id="btnIntReq">Pedir Ajuste</div>
                    </div>
                    
                    <hr style="border:0; border-top:1px solid var(--border-color); margin:20px 0;">
                    <label class="form-label" style="color:var(--text-main);">Status do Cliente (Manual)</label>
                    <div class="status-grid">
                        <div class="btn-status-action bt-app" onclick="setClientStatus('approved')" id="btnApproveTeam">Aprovado</div>
                        <div class="btn-status-action bt-cha" onclick="setClientStatus('changes')" id="btnChangesTeam">Alteração</div>
                        <div class="btn-status-action bt-pen" onclick="setClientStatus('pending')" id="btnPendingTeam">Pendente</div>
                    </div>

                    <div style="margin-top:20px; flex:1;">
                        <label class="form-label" style="color:var(--text-main);">Notas da Equipe</label>
                        <textarea name="internal_notes" id="mInternalNotes" class="form-input" style="height:100px; resize:none;" placeholder="Instruções para o time..."></textarea>
                    </div>
                </div>

                <div id="viewClient" class="mode-content">
                    <div class="manage-header"><div class="manage-title" style="font-weight:bold; font-size:1.1rem; margin-bottom:15px;">Status do Cliente</div></div>
                    <label class="form-label" style="color:var(--text-main);">Aprovação Externa</label>
                    <div class="status-grid">
                        <div class="btn-status-action bt-app" onclick="setClientStatus('approved')" id="btnApprove">Aprovado</div>
                        <div class="btn-status-action bt-cha" onclick="setClientStatus('changes')" id="btnChanges">Alteração</div>
                        <div class="btn-status-action bt-pen" onclick="setClientStatus('pending')" id="btnPending">Pendente</div>
                    </div>
                    <div style="margin-top:20px; flex:1;">
                        <label class="form-label" style="color:var(--text-main);">Feedback do Cliente</label>
                        <div id="clientFeedbackDisplay" class="feedback-box" style="display:none; margin-bottom:10px;"></div>
                        <div style="font-size:0.8rem; color:var(--text-muted-alt);">Feedback da área pública.</div>
                    </div>
                </div>
                
                <div class="modal-footer-sticky">
                    <button type="submit" class="btn-primary" style="width:100%; font-weight:bold; padding:12px;">SALVAR STATUS</button>
                </div>
                
            </form>
        </div>
    </div>
</div>

<div id="editContentModal" class="modal-overlay-manage" style="z-index: 10000;">
    <div style="background:var(--bg-card); padding:30px; border-radius:12px; width:100%; max-width:600px; border:1px solid var(--border-color); position:relative; max-height:90vh; overflow-y:auto;">
        <div onclick="document.getElementById('editContentModal').style.display='none'" style="position:absolute; top:15px; right:15px; cursor:pointer; font-size:1.5rem; color:var(--text-main);">×</div>
        <h3 style="margin-top:0; color:var(--text-main); border-bottom:1px solid var(--border-color); padding-bottom:15px;">Editar Post (Arquivos)</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_deliverable_content">
            <input type="hidden" name="edit_id" id="edit_content_id">
            <div style="margin-bottom:15px;"><label class="form-label" style="color:var(--text-main);">Título</label><input type="text" name="edit_title" id="edit_content_title" required class="form-input"></div>
            <div style="background:var(--bg-body-alt); padding:15px; border-radius:6px; margin-bottom:15px; border:1px solid var(--border-color);">
                <label style="color:var(--text-main); font-weight:bold; display:block; margin-bottom:10px;">📂 Arquivos Atuais (Marque para EXCLUIR)</label>
                <div id="fileListContainer" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap:10px;"></div>
            </div>
            <div style="background:var(--bg-body-alt); padding:15px; border-radius:6px; margin-bottom:20px; border:1px dashed var(--accent-color);">
                <label style="color:var(--accent-color); font-weight:bold; display:block; margin-bottom:5px;">➕ Adicionar Novos Arquivos</label>
                <input type="file" name="new_files[]" multiple class="form-input">
            </div>
            <div style="margin-bottom:20px; display:flex; align-items:center; gap:10px; background:var(--bg-body-alt); padding:10px; border-radius:6px;">
                <input type="checkbox" name="resubmit_approval" id="resubmit_check" value="1" style="transform:scale(1.2);">
                <label for="resubmit_check" style="color:var(--text-main); cursor:pointer; font-weight:bold;">Mudar Status para "Pendente" (Enviar p/ Cliente)</label>
            </div>
            <div style="display:flex; gap:10px;"><button type="submit" class="btn-primary" style="flex:1;">SALVAR ALTERAÇÕES</button></div>
        </form>
    </div>
</div>

<div id="calModal" class="modal-overlay-manage" style="z-index: 20000;">
    <div style="background:var(--bg-card); padding:25px; border-radius:12px; width:100%; max-width:400px; border:1px solid var(--border-color);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="modalTitleCal" style="margin:0; color:var(--text-main);">Novo Evento</h3>
            <button onclick="document.getElementById('calModal').style.display='none'" style="border:none; background:none; color:var(--text-main); font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        <form method="POST" action="detalhes.php?id=<?php echo $project_id; ?>&tab=calendario&m=<?php echo $month; ?>&y=<?php echo $year; ?>">
            <input type="hidden" name="event_id" id="eventId"><input type="hidden" name="event_date" id="eventDate">
            <div style="margin-bottom:15px;"><label class="form-label" style="color:var(--text-main);">Título do Post</label><input type="text" name="event_title" id="eventTitle" class="form-input" required></div>
            <div style="margin-bottom:20px;"><label class="form-label" style="color:var(--text-main);">Plataforma</label><select name="event_platform" id="eventPlatform" class="form-input"><option value="Instagram">Instagram</option><option value="LinkedIn">LinkedIn</option><option value="TikTok">TikTok</option><option value="YouTube">YouTube</option></select></div>
            <div style="display:flex; gap:10px;"><button type="submit" name="save_calendar_event" value="1" class="btn-primary" style="flex:1;">Salvar</button><button type="submit" name="delete_calendar_event" value="1" id="btnDeleteEvent" class="btn-primary" style="flex:1; background:#fee2e2; color:#ef4444; border:1px solid #fecaca; display:none;" onclick="return confirm('Excluir agendamento?')">Excluir</button></div>
        </form>
    </div>
</div>

<script>
// --- CHART.JS CONFIG ---
<?php 
echo "const chartLabels = " . json_encode($chartLabels) . ";\n";
echo "const chartReach = " . json_encode($chartReach) . ";\n";
echo "const chartEngage = " . json_encode($chartEngage) . ";\n";
echo "const chartFollows = " . json_encode($chartFollows) . ";\n";
?>

if(document.getElementById('reachChart')) {
    const commonOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } };
    
    new Chart(document.getElementById('reachChart'), {
        type: 'bar', 
        data: { labels: chartLabels, datasets: [{ label: 'Alcance', data: chartReach, backgroundColor: '#4338ca', borderRadius: 4 }] },
        options: { ...commonOpts, plugins: { title: { display: true, text: 'Alcance' } } }
    });

    new Chart(document.getElementById('engageChart'), {
        type: 'line', 
        data: { labels: chartLabels, datasets: [{ label: 'Engajamento', data: chartEngage, borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.1)', fill: true, tension: 0.4 }] },
        options: { ...commonOpts, plugins: { title: { display: true, text: 'Engajamento' } } }
    });

    new Chart(document.getElementById('followChart'), {
        type: 'line', 
        data: { labels: chartLabels, datasets: [{ label: 'Novos Seguidores', data: chartFollows, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', fill: true, tension: 0.4 }] },
        options: { ...commonOpts, plugins: { title: { display: true, text: 'Novos Seguidores' } } }
    });
}

function openTab(t) { document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active')); document.querySelectorAll('.tab-btn').forEach(el=>el.classList.remove('active')); document.getElementById('tab-'+t).classList.add('active'); event.currentTarget.classList.add('active'); }
function copiarLink() { const i = document.getElementById("shareLink"); i.select(); i.setSelectionRange(0, 99999); navigator.clipboard.writeText(i.value).then(() => alert('Link Copiado!')); }
function toggleBriefingMode(mode) { document.getElementById('briefing-view').style.display = mode==='view'?'block':'none'; document.getElementById('briefing-edit').style.display = mode==='edit'?'block':'none'; }

// --- DRAG & DROP CALENDAR ---
function allowDrop(ev) { ev.preventDefault(); event.target.closest('.cal-day').classList.add('drag-over'); }
function drag(ev, id) { ev.dataTransfer.setData("text", id); event.target.classList.add('dragging'); }
function drop(ev, date) {
    ev.preventDefault();
    document.querySelectorAll('.cal-day').forEach(el => el.classList.remove('drag-over'));
    var id = ev.dataTransfer.getData("text");
    const fd = new FormData();
    fd.append('ajax_action', 'move_calendar_event');
    fd.append('event_id', id);
    fd.append('new_date', date);
    fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }).then(r => window.location.reload());
}

// --- AJAX TAREFAS (CORE FIX) ---
function addMainTask() {
    const input = document.getElementById('main-task-input'); const title = input.value.trim(); if(!title) return;
    const fd = new FormData(); fd.append('ajax_action', 'add_main_task'); fd.append('title', title);
    fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }).then(r => r.text()).then(html => { document.getElementById('taskList').insertAdjacentHTML('beforeend', html); input.value = ''; });
}
function updateMainStatus(id, sel) {
    const newStatus = sel.value; const color = sel.options[sel.selectedIndex].getAttribute('data-color') || '#e2e8f0';
    sel.style.backgroundColor = color; sel.style.color = (['#f1f5f9','#ffffff','#cbd5e1'].includes(color)) ? '#333' : '#fff';
    const fd = new FormData(); fd.append('ajax_action', 'update_main_status'); fd.append('task_id', id); fd.append('status', newStatus);
    fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd });
}
function deleteTask(id) { if(!confirm('Excluir tarefa?')) return; const fd = new FormData(); fd.append('ajax_action', 'delete_main_task'); fd.append('task_id', id); fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }); document.getElementById('task-card-'+id).remove(); }
function toggleSubtasks(id) { document.getElementById('subs-'+id).classList.toggle('open'); document.getElementById('btn-sub-'+id).classList.toggle('active'); }
function addSubtask(tid) {
    const inp = document.getElementById('new-sub-'+tid); if(!inp.value.trim()) return;
    const fd = new FormData(); fd.append('ajax_action', 'add_subtask'); fd.append('task_id', tid); fd.append('title', inp.value);
    fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }).then(r=>r.text()).then(html => { const list = document.getElementById('sub-list-'+tid); list.insertAdjacentHTML('beforeend', html); inp.value=''; document.getElementById('prog-cont-'+tid).style.display='block'; recalcProgress(tid); });
}
function updateSubtask(sid, tid, val) { const fd = new FormData(); fd.append('ajax_action', 'toggle_subtask'); fd.append('subtask_id', sid); fd.append('val', val?1:0); fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }); document.getElementById('st-txt-'+sid).classList.toggle('sub-completed', val); recalcProgress(tid); }
function deleteSubtask(e, sid, tid) { e.preventDefault(); if(!confirm('Apagar?')) return; const fd = new FormData(); fd.append('ajax_action', 'delete_subtask'); fd.append('subtask_id', sid); fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }); document.getElementById('st-item-'+sid).remove(); recalcProgress(tid); }
function recalcProgress(tid) { const total = document.getElementById('sub-list-'+tid).children.length; const done = document.querySelectorAll(`#sub-list-${tid} .sub-check:checked`).length; const pct = total>0 ? (done/total)*100 : 0; document.getElementById('prog-bar-'+tid).style.width = pct+'%'; document.getElementById('count-'+tid).innerText = done+'/'+total; }
function editTaskTitle(id) { document.getElementById('task-title-display-'+id).style.display = 'none'; const inp = document.getElementById('task-title-input-'+id); inp.style.display = 'inline-block'; inp.focus(); }
function saveTaskTitle(id) { const inp = document.getElementById('task-title-input-'+id); const val = inp.value.trim(); if(val) { const fd = new FormData(); fd.append('ajax_action', 'update_task_title'); fd.append('task_id', id); fd.append('title', val); fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }); document.getElementById('task-title-display-'+id).innerText = val; } inp.style.display = 'none'; document.getElementById('task-title-display-'+id).style.display = 'inline-block'; }
function editSubtaskTitle(id) { document.getElementById('st-txt-'+id).style.display = 'none'; const inp = document.getElementById('st-input-'+id); inp.style.display = 'inline-block'; inp.focus(); }
function saveSubtaskTitle(id) { const inp = document.getElementById('st-input-'+id); const val = inp.value.trim(); if(val) { const fd = new FormData(); fd.append('ajax_action', 'update_subtask_title'); fd.append('sub_id', id); fd.append('title', val); fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }); document.getElementById('st-txt-'+id).innerText = val; } inp.style.display = 'none'; document.getElementById('st-txt-'+id).style.display = 'inline-block'; }

// MANAGE MODAL
const manageModal = document.getElementById('manageModal'); const phContainer = document.getElementById('phContainer'); const phPrev = document.getElementById('phPrev'); const phNext = document.getElementById('phNext'); let currentFiles = []; let slideIndex = 0;

function openManageModal(post, files) {
    currentFiles = files; slideIndex = 0;
    document.getElementById('mDelId').value = post.id;
    document.getElementById('mInternalNotes').value = post.internal_notes || '';
    
    // ATUALIZAÇÃO CORRETA DOS CAMPOS E CLASSES ATIVAS
    setInternalStatus(post.internal_status || 'int_pending');
    setClientStatus(post.approval_status || 'pending');
    
    // Forçar aba EQUIPE (Correção Final)
    switchMode('team');

    const fbDiv = document.getElementById('clientFeedbackDisplay');
    if (post.feedback) { fbDiv.innerText = post.feedback; fb.style.display = 'block'; } else { fb.style.display = 'none'; }
    renderSlide(); manageModal.style.display = 'flex';
}

function openEditContentModal(id, title, filesJSON) { 
    const files = (typeof filesJSON === 'string') ? JSON.parse(filesJSON) : filesJSON;
    document.getElementById('edit_content_id').value = id; 
    document.getElementById('edit_content_title').value = title; 
    const container = document.getElementById('fileListContainer'); container.innerHTML = ''; 
    if(files.length === 0) { container.innerHTML = '<small style="color:var(--text-muted)">Sem arquivos</small>'; } 
    else { 
        files.forEach(f => { 
            const ext = f.file_type.toLowerCase(); 
            const isImg = ['jpg','jpeg','png','gif','webp'].includes(ext); 
            const path = "../../uploads/" + f.file_path; 
            const thumb = isImg ? `<img src="${path}" style="width:100%; height:60px; object-fit:cover;">` : `<div style="width:100%; height:60px; background:#333; display:flex; align-items:center; justify-content:center; color:#fff;">FILE</div>`; 
            const div = document.createElement('div'); 
            div.innerHTML = `<label style="cursor:pointer; font-size:0.7rem;"><div style="border:1px solid var(--border-color); border-radius:4px; overflow:hidden;">${thumb}</div><input type="checkbox" name="delete_files[]" value="${f.id}"> Excluir</label>`; 
            container.appendChild(div); 
        }); 
    } 
    document.getElementById('editContentModal').style.display = 'flex'; 
}

function renderSlide() {
    const f = currentFiles[slideIndex];
    const isVid = ['mp4','mov','webm'].includes(f.file_type.toLowerCase());
    phContainer.innerHTML = '';
    if(isVid) {
        // Fix: Muted + Playsinline + Autoplay
        phContainer.innerHTML = `<video src="../../uploads/${f.file_path}" controls autoplay muted playsinline style="max-width:100%; max-height:100%; box-shadow: 0 4px 15px rgba(0,0,0,0.5);"></video>`;
    } else {
        phContainer.innerHTML = `<img src="../../uploads/${f.file_path}" style="max-width:100%; max-height:100%; object-fit:contain;">`;
    }
    if(currentFiles.length > 1) { phPrev.style.display='flex'; phNext.style.display='flex'; } else { phPrev.style.display='none'; phNext.style.display='none'; }
}
function changeSlide(n) { slideIndex += n; if(slideIndex >= currentFiles.length) slideIndex=0; if(slideIndex < 0) slideIndex=currentFiles.length-1; renderSlide(); }
function switchMode(mode) { document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active')); document.querySelectorAll('.mode-content').forEach(c => c.classList.remove('active')); if(mode === 'team') { document.querySelector("button[onclick=\"switchMode('team')\"]").classList.add('active'); document.getElementById('viewTeam').classList.add('active'); } else { document.querySelector("button[onclick=\"switchMode('client')\"]").classList.add('active'); document.getElementById('viewClient').classList.add('active'); } }

// LÓGICA DE STATUS REVISADA (Corrigido para selecionar em AMBAS as abas)
function setInternalStatus(st) { 
    document.getElementById('mInternalStatusInput').value = st; 
    document.querySelectorAll('#viewTeam .btn-status-action').forEach(b=>b.classList.remove('active')); 
    if(st === 'int_pending') document.getElementById('btnIntPend').classList.add('active');
    if(st === 'int_approved') document.getElementById('btnIntApp').classList.add('active');
    if(st === 'int_working') document.getElementById('btnIntWork').classList.add('active');
    if(st === 'int_to_change') document.getElementById('btnIntReq').classList.add('active');
    if(st === 'int_approval') document.getElementById('btnIntApproval').classList.add('active');
}

function setClientStatus(st) { 
    document.getElementById('mClientStatusInput').value = st; 
    
    // Resetar botões do cliente tanto na aba Client quanto na Team
    // AQUI ESTÁ O AJUSTE PARA MARCAR NAS DUAS ABAS
    document.querySelectorAll('#viewClient .btn-status-action, #viewTeam .status-grid:last-of-type .btn-status-action').forEach(b=>b.classList.remove('active'));
    
    if(st === 'pending') { 
        document.getElementById('btnPending').classList.add('active'); 
        if(document.getElementById('btnPendingTeam')) document.getElementById('btnPendingTeam').classList.add('active');
    }
    if(st === 'approved') { 
        document.getElementById('btnApprove').classList.add('active'); 
        if(document.getElementById('btnApproveTeam')) document.getElementById('btnApproveTeam').classList.add('active');
    }
    if(st === 'changes') { 
        document.getElementById('btnChanges').classList.add('active'); 
        if(document.getElementById('btnChangesTeam')) document.getElementById('btnChangesTeam').classList.add('active');
    }
}

function closeManageModal() { manageModal.style.display = 'none'; const vids = phContainer.querySelectorAll('video'); vids.forEach(v => v.pause()); }
function openCalModal(mode, date, id, title, platform) { document.getElementById('eventDate').value = date; if (mode === 'edit') { document.getElementById('modalTitleCal').innerText = 'Editar Post'; document.getElementById('eventId').value = id; document.getElementById('eventTitle').value = title; document.getElementById('eventPlatform').value = platform; document.getElementById('btnDeleteEvent').style.display = 'block'; } else { document.getElementById('modalTitleCal').innerText = 'Novo Post'; document.getElementById('eventId').value = ''; document.getElementById('eventTitle').value = ''; document.getElementById('btnDeleteEvent').style.display = 'none'; } document.getElementById('calModal').style.display = 'flex'; }
function allowDrop(ev) { ev.preventDefault(); event.target.closest('.cal-day').classList.add('drag-over'); }
function drag(ev, id) { ev.dataTransfer.setData("text", id); event.target.classList.add('dragging'); }
function drop(ev, date) { ev.preventDefault(); document.querySelectorAll('.cal-day').forEach(el => el.classList.remove('drag-over')); var id = ev.dataTransfer.getData("text"); const fd = new FormData(); fd.append('ajax_action', 'move_calendar_event'); fd.append('event_id', id); fd.append('new_date', date); fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: fd }).then(r => window.location.reload()); }

var sortable = Sortable.create(document.getElementById('taskList'), { handle: '.drag-handle', animation: 150, onEnd: function (evt) { var order = []; document.querySelectorAll('#taskList .item-card').forEach(function(item){ order.push(item.getAttribute('data-id')); }); var formData = new FormData(); formData.append('action', 'reorder_tasks'); formData.append('order', JSON.stringify(order)); fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: formData }); } });
var tabSortable = Sortable.create(document.getElementById('tabNavContainer'), { animation: 150, onEnd: function (evt) { var order = []; document.querySelectorAll('#tabNavContainer .tab-btn').forEach(function(item){ order.push(item.getAttribute('data-id')); }); var formData = new FormData(); formData.append('action', 'reorder_tabs'); formData.append('order', JSON.stringify(order)); fetch('detalhes.php?id=<?php echo $project_id; ?>', { method: 'POST', body: formData }); } });
</script>
</body>
</html>