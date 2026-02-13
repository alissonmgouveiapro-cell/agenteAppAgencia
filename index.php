<?php
/* Arquivo: index.php (Dashboard) */
/* Versão: Ignora Clientes Inativos/Pausados no Total */

session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$tenant_id = $_SESSION['tenant_id'];

// --- BUSCAR ESTATÍSTICAS ---
try {
    // 1. Total Projetos (Ignora ocultos)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE tenant_id = ? AND (is_visible = 1 OR is_visible IS NULL)");
    $stmt->execute([$tenant_id]);
    $total_projects = $stmt->fetchColumn();

    // 2. Projetos em Andamento (Ignora ocultos)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE tenant_id = ? AND status != 'completed' AND (is_visible = 1 OR is_visible IS NULL)");
    $stmt->execute([$tenant_id]);
    $active_projects = $stmt->fetchColumn();

    // 3. [CORREÇÃO] Total Clientes (Ignora Inativo E Pausado)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND status NOT IN ('Inativo', 'Pausado')");
    $stmt->execute([$tenant_id]);
    $total_clients = $stmt->fetchColumn();

    // 4. Gráficos e Listas
    $stmtChart = $pdo->prepare("SELECT status, COUNT(*) as total FROM projects WHERE tenant_id = ? AND (is_visible = 1 OR is_visible IS NULL) GROUP BY status");
    $stmtChart->execute([$tenant_id]);
    $chartData = $stmtChart->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stats = [
        'pending' => $chartData['pending'] ?? 0,
        'in_progress' => $chartData['in_progress'] ?? 0,
        'approval' => $chartData['approval'] ?? 0,
        'completed' => $chartData['completed'] ?? 0
    ];

    $recent_projects = $pdo->query("SELECT title, status FROM projects WHERE tenant_id = $tenant_id AND (is_visible = 1 OR is_visible IS NULL) ORDER BY id DESC LIMIT 3")->fetchAll();
    
    $recent_briefings = $pdo->query("SELECT r.id, r.client_name, f.title as form_title, f.id as form_id FROM briefing_responses r JOIN briefing_forms f ON r.form_id = f.id WHERE f.tenant_id = $tenant_id ORDER BY r.created_at DESC LIMIT 3")->fetchAll();

    $recent_tasks = $pdo->query("SELECT title, status FROM tasks WHERE tenant_id = $tenant_id AND status != 'Concluído' ORDER BY id DESC LIMIT 3")->fetchAll();

} catch (PDOException $e) { $total_projects = 0; }

date_default_timezone_set('America/Sao_Paulo');
$hora = date('H');
$saudacao = ($hora < 12) ? "Bom dia" : (($hora < 18) ? "Boa tarde" : "Boa noite");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Bliss OS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .section-title { font-size: 1rem; font-weight: 600; color: var(--text-main); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .mini-badge { font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; background: var(--bg-body-alt); color: var(--text-muted-alt); }
        .panel-section { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
        .panel-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .list-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; font-size: 0.95rem; }
        .row-meta { font-size: 0.8rem; color: var(--text-muted-alt); }
    </style>
</head>
<body>

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        
        <div style="margin-bottom: 3rem;" class="animate-enter">
            <h1 style="font-size: 2.5rem; font-weight: 400;"><?php echo $saudacao; ?>, <?php echo explode(' ', $_SESSION['user_name'])[0]; ?>.</h1>
            <p style="color: var(--text-muted-alt);">Aqui está o resumo da sua agência hoje.</p>
        </div>

        <div class="dashboard-stats animate-enter" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div class="stat-label">Projetos Ativos</div>
                <div class="stat-number"><?php echo $active_projects; ?></div>
                <div class="stat-icon-bg">🚀</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Clientes</div>
                <div class="stat-number"><?php echo $total_clients; ?></div>
                <div class="stat-icon-bg">👥</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Entregues</div>
                <div class="stat-number"><?php echo $total_projects - $active_projects; ?></div>
                <div class="stat-icon-bg">✅</div>
            </div>
        </div>

        <div class="dashboard-content animate-enter" style="animation-delay: 0.2s;">
            
            <div class="panel">
                <div class="panel-section">
                    <div class="section-title">📊 Status dos Projetos</div>
                    <div style="height: 250px; position: relative; width: 100%;">
                        <canvas id="projectsChart"></canvas>
                    </div>
                </div>

                <div class="panel-section">
                    <div class="section-title">📝 Briefings Recentes</div>
                    <?php if (count($recent_briefings) > 0): ?>
                        <?php foreach ($recent_briefings as $b): ?>
                            <div class="list-row">
                                <div><strong style="display:block; color:var(--text-main);"><?php echo htmlspecialchars($b['client_name']); ?></strong><span class="row-meta"><?php echo htmlspecialchars($b['form_title']); ?></span></div>
                                <a href="modules/briefings/respostas.php?id=<?php echo $b['form_id']; ?>" style="font-size:0.8rem; text-decoration:none; color:var(--accent-color);">Ver</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted-alt); font-size:0.9rem;">Nenhum briefing novo.</p>
                    <?php endif; ?>
                </div>

                <div class="panel-section">
                    <div class="section-title">🚀 Projetos Recentes</div>
                    <?php if (count($recent_projects) > 0): ?>
                        <?php foreach ($recent_projects as $p): ?>
                            <div class="list-row">
                                <span><?php echo htmlspecialchars($p['title']); ?></span>
                                <span class="mini-badge"><?php echo $p['status'] == 'completed' ? 'Concluído' : 'Em andamento'; ?></span>
                            </div>
                        <?php endforeach; ?>
                        <a href="modules/projects/projetos.php" style="font-size:0.8rem; margin-top:10px; display:inline-block; color:var(--text-muted-alt);">Ver todos &rarr;</a>
                    <?php else: ?>
                        <p style="color: var(--text-muted-alt);">Nenhum projeto ainda.</p>
                    <?php endif; ?>
                </div>

                <div class="panel-section">
                    <div class="section-title">📌 Tarefas Pendentes</div>
                    <?php if (count($recent_tasks) > 0): ?>
                        <?php foreach ($recent_tasks as $t): ?>
                            <div class="list-row"><span><?php echo htmlspecialchars($t['title']); ?></span><span class="mini-badge" style="background:#fef3c7; color:#92400e;"><?php echo $t['status']; ?></span></div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted-alt); font-size:0.9rem;">Tudo em dia!</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="calendar-widget">
                <div class="clock-display" id="dashboard-clock">00:00:00</div>
                <div class="calendar-grid" id="calendar-grid">
                    <div class="cal-day-header">D</div><div class="cal-day-header">S</div><div class="cal-day-header">T</div><div class="cal-day-header">Q</div><div class="cal-day-header">Q</div><div class="cal-day-header">S</div><div class="cal-day-header">S</div>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    function updateDashboardClock() {
        const now = new Date();
        const el = document.getElementById('dashboard-clock');
        if(el) el.innerText = now.toLocaleTimeString();
    }
    setInterval(updateDashboardClock, 1000);
    updateDashboardClock();

    function generateCalendar() {
        const grid = document.getElementById('calendar-grid');
        const now = new Date();
        const currentDate = now.getDate();
        const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
        const firstDayIndex = new Date(now.getFullYear(), now.getMonth(), 1).getDay();
        const oldDays = grid.querySelectorAll('.cal-day');
        oldDays.forEach(day => day.remove());
        for (let i = 0; i < firstDayIndex; i++) { const empty = document.createElement('div'); empty.className = 'cal-day empty'; grid.appendChild(empty); }
        for (let i = 1; i <= daysInMonth; i++) { const dayDiv = document.createElement('div'); dayDiv.className = 'cal-day'; dayDiv.innerText = i; if (i === currentDate) dayDiv.classList.add('today'); grid.appendChild(dayDiv); }
    }
    generateCalendar();

    const ctx = document.getElementById('projectsChart').getContext('2d');
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const textColor = isDark ? '#cbd5e1' : '#334155';

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pendente', 'Produção', 'Aprovação', 'Concluído'],
            datasets: [{
                data: [<?php echo $stats['pending']; ?>, <?php echo $stats['in_progress']; ?>, <?php echo $stats['approval']; ?>, <?php echo $stats['completed']; ?>],
                backgroundColor: ['#94a3b8', '#3b82f6', '#f59e0b', '#10b981'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right', labels: { color: textColor, boxWidth: 12, padding: 15, font: { size: 12 } } } },
            layout: { padding: { top: 10, bottom: 10 } },
            cutout: '75%'
        }
    });
</script>
</body>
</html>