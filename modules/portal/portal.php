<?php
/* Arquivo: /modules/portal/portal.php */
/* Visual: Lista Minimalista "Luxury" (Dark/Light) */

session_start();
require '../../config/db.php';

// Redireciona se não for cliente
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: ../../login.php"); exit;
}

$client_id = $_SESSION['related_client_id'];
$tenant_id = $_SESSION['tenant_id'];

// Busca Projetos do Cliente
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE client_id = :cid AND tenant_id = :tid ORDER BY id DESC");
    $stmt->execute(['cid' => $client_id, 'tid' => $tenant_id]);
    $projetos = $stmt->fetchAll();
} catch (PDOException $e) { die("Erro: " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Portal do Cliente</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* Ajuste específico para o Portal ficar sempre limpo/centralizado */
        body { background: var(--bg-body); }
        .portal-wrapper { max-width: 1000px; margin: 0 auto; padding: 4rem 2rem; }
        .portal-header { text-align: center; margin-bottom: 4rem; }
        .client-badge { 
            background: rgba(255,255,255,0.1); border: 1px solid var(--border-color); 
            padding: 5px 15px; border-radius: 50px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;
        }
    </style>
</head>
<body>

<a href="../../logout.php" style="position: fixed; top: 20px; right: 20px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">Sair ✕</a>

<button onclick="toggleTheme()" style="position: fixed; top: 20px; left: 20px; background: none; border: none; font-size: 1.2rem; cursor: pointer;">🌓</button>

<div class="portal-wrapper">
    
    <div class="portal-header animate-enter">
        <span class="client-badge">Área do Cliente</span>
        <h1 style="font-size: 1.5rem; margin-top: 1rem; font-weight: 400; color: var(--text-muted);">
            Bem-vindo, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
        </h1>
    </div>

    <div class="luxury-list animate-enter" style="animation-delay: 0.2s;">
        
        <?php if (count($projetos) > 0): ?>
            <?php 
                $contador = 1; // Inicia contador para o "01, 02, 03..."
                foreach ($projetos as $proj): 
                    // Formata o número com zero à esquerda (01, 02...)
                    $num = str_pad($contador, 2, '0', STR_PAD_LEFT);
            ?>
                <a href="detalhes.php?id=<?php echo $proj['id']; ?>" class="luxury-item">
                    <div class="luxury-index"><?php echo $num; ?></div>
                    
                    <div class="luxury-title"><?php echo htmlspecialchars($proj['title']); ?></div>
                    
                    <div class="luxury-arrow">→</div>
                </a>
            <?php 
                $contador++; 
                endforeach; 
            ?>
        <?php else: ?>
            <div style="text-align: center; padding: 4rem; color: var(--text-muted);">
                Nenhum projeto ativo no momento.
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
    const root = document.documentElement;
    if(localStorage.getItem('theme') === 'dark') { root.setAttribute('data-theme', 'dark'); }
    
    function toggleTheme() {
        if (root.getAttribute('data-theme') === 'dark') {
            root.removeAttribute('data-theme'); localStorage.setItem('theme', 'light');
        } else {
            root.setAttribute('data-theme', 'dark'); localStorage.setItem('theme', 'dark');
        }
    }
</script>

</body>
</html>