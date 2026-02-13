<?php
/* Arquivo: modules/notifications/notificacao.php */
/* Versão: Cores de Status (Verde/Vermelho) + Ações em Massa */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
$tenant_id = $_SESSION['tenant_id'];

// Buscar Notificações
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$tenant_id]);
$notifs = $stmt->fetchAll();

// Contagens
$totalCount = count($notifs);
$unreadCount = 0;
foreach($notifs as $n) { if($n['is_read'] == 0) $unreadCount++; }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Notificações</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        .notif-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            padding: 20px; border-radius: 12px; margin-bottom: 12px;
            display: flex; justify-content: space-between; align-items: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            position: relative;
            border-left: 5px solid #cbd5e1; /* Neutra */
        }
        
        .notif-card.type-success { border-left-color: #10b981; background: #f0fdf4; }
        .notif-card.type-warning { border-left-color: #ef4444; background: #fef2f2; }
        
        .notif-unread { font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        .notif-content { flex: 1; margin-right: 15px; }
        .notif-msg { font-size: 1rem; margin-bottom: 5px; color: var(--text-main); }
        .notif-time { font-size: 0.8rem; color: var(--text-muted-alt); font-weight: 400; }
        
        .btn-check { 
            background: transparent; border: 1px solid #cbd5e1; 
            border-radius: 50%; width: 35px; height: 35px;
            cursor: pointer; color: #cbd5e1; display: flex; align-items: center; justify-content: center;
            transition: 0.2s; font-size: 1.2rem; flex-shrink: 0;
        }
        .btn-check:hover { background: #10b981; color: white; border-color: #10b981; }

        .btn-action {
            padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600;
            display: flex; align-items: center; gap: 8px; border: none; transition: 0.2s;
        }
        .btn-mark-read { background: #e0f2fe; color: #0284c7; }
        .btn-mark-read:hover { background: #bae6fd; }
        
        .btn-delete-hist { background: #fee2e2; color: #ef4444; }
        .btn-delete-hist:hover { background: #fecaca; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        
        <div class="page-header">
            <div>
                <h1 style="margin:0;">🔔 Notificações</h1>
                <p style="color:var(--text-muted-alt); margin-top:5px;">Acompanhe as atualizações.</p>
            </div>
            
            <div id="headerActions">
                <?php if($unreadCount > 0): ?>
                    <button class="btn-action btn-mark-read" onclick="clearAllNotifications()">
                        <span>✓</span> Marcar todas como lidas
                    </button>
                <?php elseif($totalCount > 0): ?>
                    <button class="btn-action btn-delete-hist" onclick="deleteHistory()">
                        <span>🗑️</span> Limpar Histórico
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div id="notifList">
            <?php if(empty($notifs)): ?>
                <div style="text-align:center; padding:60px 20px; color:var(--text-muted-alt); border:2px dashed var(--border-color); border-radius:12px;">
                    <div style="font-size:3rem; margin-bottom:10px;">📭</div>
                    Nenhuma notificação encontrada.
                </div>
            <?php else: ?>
                <?php foreach($notifs as $n): 
                    $typeClass = ($n['type'] === 'success') ? 'type-success' : (($n['type'] === 'warning') ? 'type-warning' : '');
                    $unreadClass = ($n['is_read'] == 0) ? 'notif-unread' : '';
                ?>
                    <div class="notif-card <?php echo "$typeClass $unreadClass"; ?>" id="notif-<?php echo $n['id']; ?>">
                        <div class="notif-content">
                            <div class="notif-msg">
                                <?php 
                                    if ($n['type'] === 'success') echo '✅ ';
                                    if ($n['type'] === 'warning') echo '⚠️ ';
                                    echo $n['message']; 
                                ?>
                            </div>
                            <div class="notif-time">
                                <?php echo date('d/m/Y \à\s H:i', strtotime($n['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if($n['is_read'] == 0): ?>
                            <button class="btn-check" onclick="markAsRead(<?php echo $n['id']; ?>, this)" title="Marcar como lida">✓</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
    const API_PATH = 'api.php';

    function markAsRead(id, btn) {
        const formData = new FormData();
        formData.append('id', id);

        fetch(API_PATH + '?action=read', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                const card = btn.closest('.notif-card');
                card.classList.remove('notif-unread');
                card.style.opacity = '0.6';
                btn.remove();
                // Atualiza o sidebar se existir
                try { checkNewNotifications(); } catch(e){}
            }
        });
    }

    function clearAllNotifications() {
        if(!confirm("Marcar todas como lidas?")) return;
        fetch(API_PATH + '?action=clear_all', { method: 'POST' })
        .then(res => res.json())
        .then(data => { if(data.success) location.reload(); });
    }

    function deleteHistory() {
        if(!confirm("Apagar todo o histórico?")) return;
        fetch(API_PATH + '?action=delete_history', { method: 'POST' })
        .then(res => res.json())
        .then(data => { if(data.success) location.reload(); });
    }
</script>

</body>
</html>