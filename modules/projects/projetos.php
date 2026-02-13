<?php
/* Arquivo: /modules/projects/projetos.php */
/* Versão: Kanban + Filtro de Clientes Inativos */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
$tenant_id = $_SESSION['tenant_id'];

// 1. Busca Projetos (COM FILTRO DE VISIBILIDADE)
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as client_name 
        FROM projects p
        JOIN clients c ON p.client_id = c.id
        WHERE p.tenant_id = :tenant_id
        AND (p.is_visible = 1 OR p.is_visible IS NULL)  /* <--- O UPGRADE ESTÁ AQUI */
        ORDER BY p.deadline ASC
    ");
    $stmt->execute(['tenant_id' => $tenant_id]);
    $projetos = $stmt->fetchAll();
} catch (PDOException $e) { die("Erro: " . $e->getMessage()); }

// 2. Organiza Arrays
$kanban = ['pending' => [], 'in_progress' => [], 'approval' => [], 'completed' => []];
foreach ($projetos as $p) {
    if (array_key_exists($p['status'], $kanban)) $kanban[$p['status']][] = $p;
}

function getStatusLabel($s) {
    $map = ['pending'=>'A Fazer', 'in_progress'=>'Produção', 'approval'=>'Aprovação', 'completed'=>'Concluído'];
    return $map[$s] ?? $s;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Meus Projetos</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <style>
        /* Estilo visual enquanto arrasta */
        .sortable-ghost { opacity: 0.4; background-color: #f3f4f6; border: 2px dashed #ccc; }
        .sortable-drag { cursor: grabbing; opacity: 1; background: #fff; transform: rotate(2deg); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2>Projetos</h2>
            <a href="novo_projeto.php" class="btn-primary" style="text-decoration: none;">+ Novo Projeto</a>
        </div>

        <div class="view-switcher">
            <button class="view-btn active" onclick="openView(event, 'view-kanban')">📊 Kanban</button>
            <button class="view-btn" onclick="openView(event, 'view-lista')">📝 Lista</button>
            <button class="view-btn" onclick="openView(event, 'view-galeria')">🖼️ Galeria</button>
            <button class="view-btn" onclick="openView(event, 'view-timeline')">📅 Cronograma</button>
        </div>

        <div id="view-kanban" class="view-section active-view">
            <div class="kanban-board">
                <?php foreach($kanban as $status => $lista): ?>
                    <div class="kanban-column" data-status="<?php echo $status; ?>">
                        
                        <div style="margin-bottom:10px; font-weight:700; color:var(--text-muted); font-size:0.85rem; text-transform:uppercase; pointer-events: none;">
                            <?php echo getStatusLabel($status); ?> <span class="counter">(<?php echo count($lista); ?>)</span>
                        </div>
                        
                        <div class="kanban-items-container" style="min-height: 100px;">
                            <?php foreach($lista as $p): ?>
                                <a href="detalhes.php?id=<?php echo $p['id']; ?>" class="kanban-card" data-id="<?php echo $p['id']; ?>" style="display:block;">
                                    <strong style="display:block; margin-bottom:5px;"><?php echo htmlspecialchars($p['title']); ?></strong>
                                    <small style="color:var(--text-muted);">🏢 <?php echo htmlspecialchars($p['client_name']); ?></small>
                                    <div style="margin-top:8px; font-size:0.75rem; color:var(--text-main); font-weight:500;">
                                        📅 <?php echo date('d/m', strtotime($p['deadline'])); ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="view-lista" class="view-section">
            <div class="list-container">
                <div class="list-row list-header">
                    <span>Projeto</span><span>Cliente</span><span>Status</span><span>Prazo</span><span>Ação</span>
                </div>
                <?php foreach($projetos as $p): ?>
                    <a href="detalhes.php?id=<?php echo $p['id']; ?>" class="list-row" style="text-decoration:none; color:inherit;">
                        <strong><?php echo htmlspecialchars($p['title']); ?></strong>
                        <span><?php echo htmlspecialchars($p['client_name']); ?></span>
                        <span><?php echo getStatusLabel($p['status']); ?></span>
                        <span><?php echo date('d/m/Y', strtotime($p['deadline'])); ?></span>
                        <span>✏️</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="view-galeria" class="view-section">
            <div class="gallery-grid">
                <?php foreach($projetos as $p): ?>
                    <a href="detalhes.php?id=<?php echo $p['id']; ?>" class="gallery-card" style="text-decoration:none; color:inherit;">
                        <div class="gallery-cover">📂</div>
                        <div style="padding: 1.2rem;">
                            <strong style="font-size:1.1rem; display:block; margin-bottom:5px;"><?php echo htmlspecialchars($p['title']); ?></strong>
                            <p style="color:var(--text-muted); font-size:0.9rem;">🏢 <?php echo htmlspecialchars($p['client_name']); ?></p>
                            <div style="margin-top:10px; font-size:0.8rem; background:var(--bg-hover); display:inline-block; padding:2px 8px; border-radius:4px;">
                                <?php echo getStatusLabel($p['status']); ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="view-timeline" class="view-section">
            <div class="timeline-container">
                <?php foreach($projetos as $p): ?>
                    <a href="detalhes.php?id=<?php echo $p['id']; ?>" class="timeline-item" style="text-decoration:none; color:inherit;">
                        <div style="font-weight:600;"><?php echo htmlspecialchars($p['title']); ?></div>
                        <div style="display:flex; gap:10px; font-size:0.85rem; color:var(--text-muted); margin-top:4px;">
                            <span>📅 <?php echo date('d/m/Y', strtotime($p['deadline'])); ?></span>
                            <span>•</span>
                            <span><?php echo getStatusLabel($p['status']); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

    </main>
</div>

<script>
function openView(evt, viewName) {
    var i, x, tablinks;
    x = document.getElementsByClassName("view-section");
    for (i = 0; i < x.length; i++) {
        x[i].style.display = "none";
        x[i].classList.remove("active-view");
    }
    tablinks = document.getElementsByClassName("view-btn");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(viewName).style.display = "block";
    document.getElementById(viewName).classList.add("active-view");
    evt.currentTarget.className += " active";
}
</script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Seleciona todas as áreas onde os cards ficam
        const columns = document.querySelectorAll('.kanban-items-container');

        columns.forEach((col) => {
            new Sortable(col, {
                group: 'shared', // Permite mover entre colunas diferentes
                animation: 150,
                ghostClass: 'sortable-ghost', // Classe visual do espaço vazio
                dragClass: 'sortable-drag',   // Classe visual do item sendo arrastado
                delay: 100, // Pequeno delay para evitar clique acidental em touch
                delayOnTouchOnly: true,
                
                onEnd: function (evt) {
                    const itemEl = evt.item; // O card movido
                    const newColumn = evt.to.closest('.kanban-column'); // A coluna de destino
                    const oldColumn = evt.from.closest('.kanban-column'); // A coluna de origem
                    
                    // Se mudou de coluna
                    if (newColumn && oldColumn && newColumn !== oldColumn) {
                        const projectId = itemEl.getAttribute('data-id');
                        const newStatus = newColumn.getAttribute('data-status');
                        
                        // Atualiza contadores visuais
                        updateCounters(newColumn, oldColumn);

                        // Envia para o servidor
                        updateDatabase(projectId, newStatus);
                    }
                }
            });
        });

        function updateDatabase(id, status) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('status', status);

            fetch('ajax_update_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) console.error("Erro ao salvar");
            })
            .catch(error => console.error("Erro de conexão", error));
        }

        function updateCounters(newCol, oldCol) {
            // Atualiza o numerozinho (x) no topo da coluna
            const newCounter = newCol.parentElement.querySelector('.counter');
            const oldCounter = oldCol.parentElement.querySelector('.counter');
            
            if (newCounter) {
                let count = parseInt(newCounter.innerText.replace(/\D/g,'')) + 1;
                newCounter.innerText = `(${count})`;
            }
            if (oldCounter) {
                let count = parseInt(oldCounter.innerText.replace(/\D/g,'')) - 1;
                oldCounter.innerText = `(${count})`;
            }
        }
    });
</script>

</body>
</html>