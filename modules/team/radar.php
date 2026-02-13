<?php
/* Arquivo: /modules/team/radar.php */
/* Versão: Radar de Alocação de Equipe + Carga de Trabalho */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
$tenant_id = $_SESSION['tenant_id'];

// --- 1. BUSCAR TODOS OS MEMBROS ---
$stmtUsers = $pdo->prepare("SELECT id, name, email, profile_pic, custom_title FROM users WHERE tenant_id = ? ORDER BY name ASC");
$stmtUsers->execute([$tenant_id]);
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// --- 2. BUSCAR PROJETOS ATIVOS ---
// Ignoramos projetos concluídos ('completed') para focar na carga atual
$stmtProj = $pdo->prepare("SELECT id, title, status, squad_data FROM projects WHERE tenant_id = ? AND status != 'completed'");
$stmtProj->execute([$tenant_id]);
$projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

// --- 3. MAPEAMENTO (LÓGICA DO RADAR) ---
$allocation = [];

// Inicializa o array para cada usuário
foreach($users as $u) {
    $allocation[$u['id']] = [
        'info' => $u,
        'projects' => [],
        'count' => 0
    ];
}

// Varre os projetos e distribui nos usuários
foreach($projects as $p) {
    if (!empty($p['squad_data'])) {
        $squad = json_decode($p['squad_data'], true);
        if (is_array($squad)) {
            foreach($squad as $member) {
                $uid = $member['user_id'];
                // Se o usuário ainda existe na empresa
                if (isset($allocation[$uid])) {
                    $allocation[$uid]['projects'][] = [
                        'id' => $p['id'],
                        'title' => $p['title'],
                        'status' => $p['status'],
                        'role_in_project' => $member['role'] // O cargo dele NESTE projeto
                    ];
                    $allocation[$uid]['count']++;
                }
            }
        }
    }
}

// Função para pegar iniciais
function getInitials($name) {
    $parts = explode(' ', trim($name));
    return strtoupper($parts[0][0] . (count($parts)>1 ? end($parts)[0] : ''));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Radar de Equipe | Bliss OS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .radar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .member-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .member-card:hover { transform: translateY(-3px); border-color: var(--accent-color); }

        .mc-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--bg-body-alt);
        }

        .mc-avatar {
            width: 50px; height: 50px; border-radius: 50%; background: #e2e8f0; 
            display: flex; align-items: center; justify-content: center; 
            font-weight: bold; color: #64748b; font-size: 1.1rem; overflow: hidden;
            flex-shrink: 0;
        }
        .mc-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .mc-info h3 { margin: 0; font-size: 1.1rem; color: var(--text-main); }
        .mc-role { font-size: 0.85rem; color: var(--text-muted); margin-top: 2px; }

        .workload-bar {
            height: 6px; width: 100%; background: #e2e8f0; margin-top: 10px; border-radius: 3px; overflow: hidden;
        }
        .workload-fill { height: 100%; border-radius: 3px; }
        
        /* CORES DE CARGA */
        .load-low { background: #22c55e; width: 30%; } /* 1-2 projetos */
        .load-med { background: #eab308; width: 60%; } /* 3-5 projetos */
        .load-high { background: #ef4444; width: 100%; } /* 6+ projetos */

        .mc-body { padding: 15px; flex: 1; }
        .project-list { list-style: none; padding: 0; margin: 0; }
        
        .project-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px; border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
        }
        .project-item:last-child { border-bottom: none; }
        
        .pi-left { display: flex; flex-direction: column; }
        .pi-title { font-weight: 600; color: var(--text-main); text-decoration: none; }
        .pi-title:hover { text-decoration: underline; color: var(--accent-color); }
        .pi-role { font-size: 0.75rem; color: var(--text-muted); background: var(--bg-body-alt); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; width: fit-content; }

        .pi-status {
            width: 10px; height: 10px; border-radius: 50%;
        }
        .st-pending { background: #fcd34d; }
        .st-in_progress { background: #3b82f6; }
        .st-approval { background: #f97316; }

        .empty-state { text-align: center; color: var(--text-muted); padding: 20px; font-size: 0.9rem; font-style: italic; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        
        <div style="margin-bottom: 2rem;">
            <h1 style="margin:0; font-size:1.8rem;">📡 Radar de Alocação</h1>
            <p style="color:var(--text-muted); margin-top:5px;">Visualize onde cada membro da equipe está trabalhando.</p>
        </div>

        <div class="radar-grid">
            <?php foreach($allocation as $uid => $data): 
                $count = $data['count'];
                // Lógica de Carga
                $loadClass = 'load-low'; $loadText = 'Livre';
                if($count >= 3) { $loadClass = 'load-med'; $loadText = 'Ocupado'; }
                if($count >= 6) { $loadClass = 'load-high'; $loadText = 'Sobrecarregado'; }
                if($count == 0) { $loadClass = ''; $loadText = 'Disponível'; }
                
                $u = $data['info'];
            ?>
            <div class="member-card">
                <div class="mc-header">
                    <div class="mc-avatar">
                        <?php if($u['profile_pic'] && file_exists("../../uploads/avatars/".$u['profile_pic'])): ?>
                            <img src="../../uploads/avatars/<?php echo $u['profile_pic']; ?>">
                        <?php else: echo getInitials($u['name']); endif; ?>
                    </div>
                    <div class="mc-info" style="flex:1;">
                        <h3><?php echo htmlspecialchars($u['name']); ?></h3>
                        <div class="mc-role"><?php echo htmlspecialchars($u['custom_title'] ?: 'Colaborador'); ?></div>
                        
                        <?php if($count > 0): ?>
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-top:5px; color:var(--text-muted);">
                                <span><?php echo $count; ?> Projetos ativos</span>
                                <span><?php echo $loadText; ?></span>
                            </div>
                            <div class="workload-bar"><div class="workload-fill <?php echo $loadClass; ?>"></div></div>
                        <?php else: ?>
                            <div style="font-size:0.75rem; margin-top:5px; color:#22c55e;">● Totalmente Disponível</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mc-body">
                    <?php if($count > 0): ?>
                        <ul class="project-list">
                            <?php foreach($data['projects'] as $proj): 
                                $stClass = 'st-pending';
                                if($proj['status']=='in_progress') $stClass='st-in_progress';
                                if($proj['status']=='approval') $stClass='st-approval';
                            ?>
                                <li class="project-item">
                                    <div class="pi-left">
                                        <a href="../projects/detalhes.php?id=<?php echo $proj['id']; ?>" class="pi-title">
                                            <?php echo htmlspecialchars($proj['title']); ?>
                                        </a>
                                        <span class="pi-role">Como: <?php echo htmlspecialchars($proj['role_in_project']); ?></span>
                                    </div>
                                    <div class="pi-status <?php echo $stClass; ?>" title="Status: <?php echo $proj['status']; ?>"></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">Nenhum projeto ativo no momento.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

</body>
</html>