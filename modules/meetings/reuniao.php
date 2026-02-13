<?php
/* Arquivo: modules/meetings/reuniao.php */
/* Versão: Layout Agency + Notificações em Tempo Real */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
$tenant_id = $_SESSION['tenant_id'];

// --- 1. AUTO-MIGRAÇÃO (Adiciona coluna de monitoramento) ---
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM meetings LIKE 'last_ping'");
    if ($checkCol->rowCount() == 0) {
        $pdo->exec("ALTER TABLE meetings ADD COLUMN last_ping DATETIME DEFAULT NULL");
    }
} catch (Exception $e) { }

// --- 2. API DE SINALIZAÇÃO (Chamada pelo Popup e pelo Painel) ---
if (isset($_GET['api_action'])) {
    header('Content-Type: application/json');
    
    // O Popup chama isso para dizer "Estou aqui"
    if ($_GET['api_action'] === 'ping' && isset($_GET['meeting_id'])) {
        $pdo->prepare("UPDATE meetings SET last_ping = NOW() WHERE id = ?")->execute([$_GET['meeting_id']]);
        echo json_encode(['status' => 'alive']);
        exit;
    }

    // O Painel chama isso para saber "Tem alguém online?"
    if ($_GET['api_action'] === 'check_status') {
        // Busca reuniões que tiveram sinal nos últimos 15 segundos
        $stmt = $pdo->prepare("SELECT id, topic FROM meetings WHERE tenant_id = ? AND last_ping > (NOW() - INTERVAL 15 SECOND)");
        $stmt->execute([$tenant_id]);
        $active = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['active_meetings' => $active]);
        exit;
    }
}

// --- AJAX END MEETING ---
if (isset($_GET['ajax_end'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT started_at FROM meetings WHERE id = ?"); $stmt->execute([$id]); $start = $stmt->fetchColumn();
    if($start) {
        $minutes = (new DateTime($start))->diff(new DateTime())->i + ((new DateTime($start))->diff(new DateTime())->h * 60);
        $pdo->prepare("UPDATE meetings SET ended_at = NOW(), duration_minutes = ? WHERE id = ?")->execute([$minutes, $id]);
    }
    exit;
}

// --- ENCERRAR MANUALMENTE ---
if (isset($_GET['force_end'])) {
    $id = $_GET['force_end'];
    // Ao forçar fim, removemos também o ping para parar a notificação
    $pdo->prepare("UPDATE meetings SET ended_at = NOW(), last_ping = NULL WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);
    header("Location: reuniao.php"); exit;
}

// --- POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_meeting'])) {
        $topic = $_POST['topic'] ?: "Reunião Geral";
        $room = "BlissMeeting_" . $tenant_id . "_" . uniqid(); 
        
        if ($_POST['action_meeting'] === 'schedule') {
            $date = $_POST['date']; $time = $_POST['time'];
            $scheduled_at = $date . ' ' . $time . ':00';
            $stmt = $pdo->prepare("INSERT INTO meetings (tenant_id, room_name, topic, scheduled_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$tenant_id, $room, $topic, $scheduled_at]);
            header("Location: reuniao.php"); exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO meetings (tenant_id, room_name, topic, started_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$tenant_id, $room, $topic]);
            $newId = $pdo->lastInsertId();
            header("Location: reuniao.php?open_popup=" . $newId); exit;
        }
    }
    if (isset($_POST['clear_history'])) {
        $pdo->prepare("DELETE FROM meetings WHERE tenant_id = ? AND started_at IS NOT NULL")->execute([$tenant_id]);
        header("Location: reuniao.php"); exit;
    }
}

// --- GET ACTIONS ---
if (isset($_GET['start_scheduled'])) {
    $id = $_GET['start_scheduled'];
    $pdo->prepare("UPDATE meetings SET started_at = NOW() WHERE id = ? AND tenant_id = ?")->execute([$id, $tenant_id]);
    header("Location: reuniao.php?open_popup=" . $id); exit;
}
if (isset($_GET['del_scheduled'])) {
    $pdo->prepare("DELETE FROM meetings WHERE id = ? AND tenant_id = ?")->execute([$_GET['del_scheduled'], $tenant_id]);
    header("Location: reuniao.php"); exit;
}

// --- BUSCAS ---
$scheduled = $pdo->prepare("SELECT * FROM meetings WHERE tenant_id = ? AND scheduled_at IS NOT NULL AND started_at IS NULL ORDER BY scheduled_at ASC");
$scheduled->execute([$tenant_id]); $scheduledData = $scheduled->fetchAll();

$history = $pdo->prepare("SELECT * FROM meetings WHERE tenant_id = ? AND started_at IS NOT NULL ORDER BY started_at DESC LIMIT 20");
$history->execute([$tenant_id]); $historyData = $history->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Central de Reuniões</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        :root { --bg-card: #ffffff; --text-main: #333; --border-color: #e2e8f0; --input-bg: #fff; --text-muted: #64748b; }
        [data-theme="dark"] { --bg-card: #27272a; --text-main: #f4f4f5; --border-color: #3f3f46; --input-bg: #27272a; --text-muted: #a1a1aa; }

        .meeting-hub { max-width: 850px; margin: 0 auto; padding: 20px; }
        
        /* CARD ROXO (ESTILO ORIGINAL) */
        .new-meeting-card { 
            background: linear-gradient(135deg, #4f46e5, #4338ca); 
            color: white; 
            padding: 30px 25px; 
            border-radius: 12px; 
            margin-bottom: 40px; 
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.25); 
            text-align: center;
        }
        .card-title { font-size: 1.5rem; font-weight: 700; margin: 0 0 5px 0; color: white; }
        .card-sub { font-size: 0.9rem; opacity: 0.8; margin-bottom: 20px; }

        /* INPUT TRANSLUCIDO NO CARD */
        .input-meeting-hero {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 10px 15px;
            color: white;
            width: 100%;
            max-width: 400px;
            margin-bottom: 15px;
            font-size: 0.95rem;
            text-align: center;
        }
        .input-meeting-hero::placeholder { color: rgba(255, 255, 255, 0.6); }
        .input-meeting-hero:focus { outline: none; background: rgba(255, 255, 255, 0.25); border-color: white; }

        /* BOTÕES PRINCIPAIS */
        .card-btn-group { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn-hero { 
            padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; font-size: 0.9rem; 
            display: flex; align-items: center; gap: 8px; transition: 0.2s;
        }
        .btn-hero.primary { background: white; color: #4338ca; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn-hero.primary:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .btn-hero.secondary { background: transparent; color: white; border: 1px solid rgba(255,255,255,0.5); }
        .btn-hero.secondary:hover { background: rgba(255,255,255,0.1); border-color: white; }

        /* LISTAS */
        .section-title { font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 30px 0 10px 0; display:flex; justify-content:space-between; align-items:center; }
        .hist-list { background: var(--bg-card); border-radius: 10px; border: 1px solid var(--border-color); overflow: hidden; }
        .hist-item { padding: 12px 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; transition: 0.2s; }
        .hist-item:last-child { border-bottom: none; }
        .hist-item:hover { background: var(--bg-body-alt); }
        
        .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-right: 10px; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-ended { background: #f1f5f9; color: #64748b; }
        .status-scheduled { background: #e0e7ff; color: #4338ca; }

        .btn-mini { padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; cursor: pointer; border: none; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-reenter { background: #4f46e5; color: white; }
        .btn-end-call { background: #fee2e2; color: #b91c1c; margin-left: 5px; }
        .btn-end-call:hover { background: #fecaca; }
        .btn-clear { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 0.8rem; opacity: 0.7; transition: 0.2s; }
        .btn-clear:hover { opacity: 1; text-decoration: underline; }

        /* TOAST NOTIFICATION */
        .toast-notification {
            position: fixed; bottom: 20px; right: 20px;
            background: #10b981; color: white;
            padding: 15px 25px; border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            font-weight: bold; display: flex; align-items: center; gap: 10px;
            transform: translateY(100px); opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            z-index: 10000;
        }
        .toast-notification.show { transform: translateY(0); opacity: 1; }
        .pulse-dot { width: 10px; height: 10px; background: white; border-radius: 50%; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(255, 255, 255, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); } }

        /* MODAL PADRÃO DO SISTEMA */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .status-modal-card { 
            background: var(--bg-card); color: var(--text-main); 
            width: 100%; max-width: 500px; 
            border-radius: 12px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.3); 
            display: flex; flex-direction: column; 
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .st-modal-header { 
            padding: 20px 25px; 
            border-bottom: 1px solid var(--border-color); 
            display: flex; justify-content: space-between; align-items: center; 
            background: var(--bg-body-alt);
        }
        .modal-scroll-content { padding: 25px; overflow-y: auto; max-height: 80vh; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-input { 
            width: 100%; padding: 12px; border-radius: 8px; 
            border: 1px solid var(--border-color); 
            background: var(--input-bg); color: var(--text-main); 
            font-size: 0.95rem;
        }
        .form-label { font-size: 0.9rem; font-weight: 600; margin-bottom: 6px; display: block; color: var(--text-main); }
        .btn-modal-action {
            width: 100%; padding: 12px; border-radius: 8px; font-weight: 700;
            background: #4f46e5; color: white; border: none; cursor: pointer;
            font-size: 1rem; margin-top: 10px; transition: 0.2s;
        }
        .btn-modal-action:hover { background: #4338ca; }

        @media(max-width: 600px) { .card-btn-group { flex-direction: column; } .btn-hero { width: 100%; justify-content: center; } }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        
        <div id="toast" class="toast-notification">
            <div class="pulse-dot"></div>
            <span id="toastMsg">Alguém entrou na sala!</span>
        </div>
        <audio id="alertSound" src="https://cdn.pixabay.com/audio/2022/03/24/audio_73e72eb402.mp3"></audio>

        <div class="meeting-hub">
            <h1 style="margin-bottom:20px; font-size:1.5rem; color:var(--text-main);">Salas de Reunião</h1>

            <div class="new-meeting-card">
                <div class="card-title">Nova Chamada</div>
                <div class="card-sub">Crie uma sala instantânea ou agende.</div>
                
                <form method="POST">
                    <input type="hidden" name="action_meeting" value="start_now">
                    <input type="text" name="topic" class="input-meeting-hero" placeholder="Assunto (Opcional)...">
                    
                    <div class="card-btn-group">
                        <button type="submit" class="btn-hero primary">
                            <span class="material-icons-round" style="font-size:1.1rem;">Iniciar Agora</span>
                        </button>
                        <button type="button" onclick="document.getElementById('scheduleModal').style.display='flex'" class="btn-hero secondary">
                            <span class="material-icons-round" style="font-size:1.1rem;">Agendar Reunião</span>
                        </button>
                    </div>
                </form>
            </div>

            <?php if(count($scheduledData) > 0): ?>
                <div class="section-title">Próximos Agendamentos</div>
                <div class="hist-list">
                    <?php foreach($scheduledData as $sch): $schDate = new DateTime($sch['scheduled_at']); ?>
                        <div class="hist-item">
                            <div>
                                <div style="font-size:0.75rem; font-weight:700; color:var(--accent-color); margin-bottom:3px;">
                                    <?php echo $schDate->format('d/m') . ' • ' . $schDate->format('H:i'); ?>
                                </div>
                                <div style="font-weight:600; font-size:0.95rem; color:var(--text-main);"><?php echo htmlspecialchars($sch['topic']); ?></div>
                            </div>
                            <div style="display:flex; align-items:center; gap:5px;">
                                <span class="status-badge status-scheduled">Agendada</span>
                                <a href="?start_scheduled=<?php echo $sch['id']; ?>" class="btn-mini btn-reenter">Iniciar</a>
                                <a href="?del_scheduled=<?php echo $sch['id']; ?>" style="color:#ef4444; padding:5px; text-decoration:none; font-size:1.1rem;" onclick="return confirm('Cancelar?')">✕</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="section-title">
                Histórico Recente
                <?php if(count($historyData) > 0): ?>
                    <form method="POST" onsubmit="return confirm('Limpar histórico?');" style="margin:0;">
                        <button type="submit" name="clear_history" value="1" class="btn-clear">Limpar</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="hist-list" id="historyList">
                <?php if(count($historyData) == 0 && count($scheduledData) == 0): ?>
                    <div style="padding: 30px; text-align: center; color: var(--text-muted);">Nenhuma reunião recente.</div>
                <?php endif; ?>

                <?php foreach($historyData as $h): 
                    // Se teve ping nos últimos 30s, considera online
                    $isOnline = false;
                    if ($h['last_ping']) {
                        $diff = time() - strtotime($h['last_ping']);
                        if ($diff < 30) $isOnline = true;
                    }
                    
                    $isOpen = ($h['duration_minutes'] == 0);
                    $statusClass = $isOnline ? 'status-badge status-active' : ($isOpen ? 'status-badge status-scheduled' : 'status-badge status-ended');
                    $statusText = $isOnline ? '🟢 ONLINE AGORA' : ($isOpen ? 'Aberta' : $h['duration_minutes'] . ' min');
                ?>
                    <div class="hist-item">
                        <div>
                            <div style="font-weight:600; font-size:0.95rem; color:var(--text-main); margin-bottom:3px;"><?php echo htmlspecialchars($h['topic']); ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted);">Iniciada em: <?php echo date('d/m \à\s H:i', strtotime($h['started_at'])); ?></div>
                        </div>
                        <div style="text-align:right; display:flex; align-items:center;">
                            <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            
                            <?php if($isOpen): ?>
                                <button onclick="openPopup(<?php echo $h['id']; ?>)" class="btn-mini btn-reenter" style="margin-left:10px;">Entrar</button>
                                <a href="?force_end=<?php echo $h['id']; ?>" class="btn-mini btn-end-call" onclick="return confirm('Encerrar reunião?')">Fim</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="scheduleModal" class="modal-overlay">
            <div class="status-modal-card">
                <div class="st-modal-header">
                    <h3 style="margin:0; font-size:1.2rem; color:var(--text-main);">Agendar Reunião</h3>
                    <button onclick="document.getElementById('scheduleModal').style.display='none'" style="border:none; background:none; font-size:1.8rem; cursor:pointer; color:var(--text-main); line-height:1;">&times;</button>
                </div>
                <div class="modal-scroll-content">
                    <form method="POST">
                        <input type="hidden" name="action_meeting" value="schedule">
                        <div class="form-group">
                            <label class="form-label">Assunto / Pauta</label>
                            <input type="text" name="topic" class="form-input" placeholder="Ex: Briefing Mensal" required>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div class="form-group"><label class="form-label">Data</label><input type="date" name="date" class="form-input" required min="<?php echo date('Y-m-d'); ?>"></div>
                            <div class="form-group"><label class="form-label">Hora</label><input type="time" name="time" class="form-input" required></div>
                        </div>
                        <button type="submit" class="btn-modal-action">Confirmar Agendamento</button>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
    function openPopup(id) {
        const w = 450; const h = 600;
        const y = window.top.outerHeight / 2 + window.top.screenY - ( h / 2);
        const x = window.top.outerWidth / 2 + window.top.screenX - ( w / 2);
        window.open('video_popup.php?id=' + id, 'BlissCall_' + id, `toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=yes, copyhistory=no, width=${w}, height=${h}, top=${y}, left=${x}`);
    }

    <?php if(isset($_GET['open_popup'])): ?>
        openPopup(<?php echo $_GET['open_popup']; ?>);
        window.history.replaceState({}, document.title, "reuniao.php");
    <?php endif; ?>

    // --- SISTEMA DE NOTIFICAÇÃO (POLLING) ---
    let lastActiveCount = 0;
    
    function checkNotifications() {
        fetch('reuniao.php?api_action=check_status')
            .then(response => response.json())
            .then(data => {
                const active = data.active_meetings;
                
                // Se tem reunião ativa e antes não tinha, toca o som
                if (active.length > 0 && active.length > lastActiveCount) {
                    showToast("🔔 " + active[0].topic + ": Alguém está na sala!");
                    document.getElementById('alertSound').play().catch(e=>{});
                    
                    // Recarrega a página para atualizar o status visual (bola verde)
                    setTimeout(() => window.location.reload(), 2000);
                }
                
                lastActiveCount = active.length;
            })
            .catch(err => console.log('Polling error', err));
    }

    function showToast(msg) {
        const t = document.getElementById('toast');
        document.getElementById('toastMsg').innerText = msg;
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 5000);
    }

    // Verifica a cada 8 segundos
    setInterval(checkNotifications, 8000);
</script>

</body>
</html>