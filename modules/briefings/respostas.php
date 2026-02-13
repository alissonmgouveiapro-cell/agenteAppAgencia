<?php
/* Arquivo: modules/briefings/respostas.php */
session_start();
require '../../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

$tenant_id = $_SESSION['tenant_id'];
$form_id = $_GET['id'] ?? null;

if (!$form_id) { header("Location: briefing.php"); exit; }

// Busca info do formulário
$stmtForm = $pdo->prepare("SELECT * FROM briefing_forms WHERE id = ? AND tenant_id = ?");
$stmtForm->execute([$form_id, $tenant_id]);
$form = $stmtForm->fetch();

if (!$form) { header("Location: briefing.php"); exit; }

// Busca respostas
$stmtResp = $pdo->prepare("SELECT * FROM briefing_responses WHERE form_id = ? ORDER BY created_at DESC");
$stmtResp->execute([$form_id]);
$responses = $stmtResp->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Respostas: <?php echo htmlspecialchars($form['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .resp-list { display: grid; grid-template-columns: 300px 1fr; gap: 20px; height: calc(100vh - 100px); }
        .resp-sidebar { background: #fff; border-right: 1px solid #eee; overflow-y: auto; padding-right: 10px; }
        .resp-content { background: #fff; border-radius: 8px; padding: 30px; border: 1px solid #e2e8f0; overflow-y: auto; height: 100%; }
        
        .client-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: 0.2s; border-radius: 6px; margin-bottom: 5px; }
        .client-item:hover, .client-item.active { background: #f8fafc; border-left: 4px solid #000; }
        .client-name { font-weight: bold; color: #333; display: block; }
        .client-date { font-size: 0.8rem; color: #888; }
        
        .answer-block { margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #f1f1f1; }
        .question-title { font-weight: bold; color: #000; margin-bottom: 8px; display: block; }
        .answer-text { color: #444; line-height: 1.6; white-space: pre-wrap; }
        
        @media (max-width: 768px) {
            .resp-list { grid-template-columns: 1fr; height: auto; }
            .resp-sidebar { height: 200px; border-bottom: 1px solid #eee; margin-bottom: 20px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
            <a href="briefing.php" class="btn-secondary">← Voltar</a>
            <h1>Respostas: <?php echo htmlspecialchars($form['title']); ?></h1>
        </div>

        <?php if (count($responses) == 0): ?>
            <div style="text-align: center; padding: 50px; background: #fff; border-radius: 8px;">
                <h3>Nenhuma resposta ainda.</h3>
                <p>Envie o link do formulário para seus clientes.</p>
            </div>
        <?php else: ?>
            <div class="resp-list">
                <div class="resp-sidebar">
                    <?php foreach($responses as $idx => $r): ?>
                        <div class="client-item <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="showResponse(<?php echo $r['id']; ?>, this)">
                            <span class="client-name"><?php echo htmlspecialchars($r['client_name']); ?></span>
                            <span class="client-date"><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="displayArea">
                    </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
    const allResponses = <?php echo json_encode($responses); ?>;

    function showResponse(id, element) {
        // Atualiza visual da lista
        document.querySelectorAll('.client-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');

        // Busca dados
        const resp = allResponses.find(r => r.id == id);
        const container = document.getElementById('displayArea');
        
        if (!resp) return;

        // Formata data
        const date = new Date(resp.created_at).toLocaleString('pt-BR');
        
        // Decodifica as respostas JSON
        let answersHTML = '';
        try {
            const data = JSON.parse(resp.answers_json);
            data.forEach(item => {
                answersHTML += `
                    <div class="answer-block">
                        <span class="question-title">${item.question}</span>
                        <div class="answer-text">${item.answer || '<em style="color:#999">Sem resposta</em>'}</div>
                    </div>
                `;
            });
        } catch(e) {
            answersHTML = '<p>Erro ao ler dados da resposta.</p>';
        }

        container.innerHTML = `
            <div style="border-bottom:2px solid #000; padding-bottom:15px; margin-bottom:20px;">
                <h2 style="margin:0;">${resp.client_name}</h2>
                <small style="color:#666;">Enviado em: ${date}</small>
            </div>
            <div class="resp-content-body">
                ${answersHTML}
            </div>
        `;
    }

    // Carrega o primeiro automaticamente
    if (allResponses.length > 0) {
        const first = document.querySelector('.client-item');
        if(first) showResponse(allResponses[0].id, first);
    }
</script>
</body>
</html>