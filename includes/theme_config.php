<?php
/* Arquivo: includes/theme_config.php */
/* Versão: Aplicação Forçada de Estilos (Correção) */

// 1. Tenta carregar conexão se não existir
if (!isset($pdo)) { 
    $paths = [__DIR__ . '/../config/db.php', '../../config/db.php', '../config/db.php'];
    foreach($paths as $p) { if(file_exists($p)) { require_once $p; break; } }
}

// 2. Busca Configurações
if (!isset($globalThemeConfig)) {
    try {
        $stmtTheme = $pdo->prepare("SELECT * FROM system_settings LIMIT 1");
        $stmtTheme->execute();
        $globalThemeConfig = $stmtTheme->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// 3. Define Variáveis com Fallback (Padrão)
// Se o banco estiver vazio ou faltar coluna, usa o valor da direita (??)
$pColor   = $globalThemeConfig['primary_color'] ?? '#d4af37';
$btnText  = $globalThemeConfig['btn_text_color'] ?? '#ffffff';
$radius   = $globalThemeConfig['btn_border_radius'] ?? '8px';
$fontFam  = $globalThemeConfig['font_family']   ?? 'Manrope';

// Cores de Texto e Fundo
$heroTxt    = $globalThemeConfig['hero_text_color'] ?? '#ffffff';
$welcomeTxt = $globalThemeConfig['welcome_text_color'] ?? '#333333';
$gridTxt    = $globalThemeConfig['grid_text_color'] ?? '#ffffff';
$bgColor    = $globalThemeConfig['bg_color'] ?? '#050505';

$appLogo    = $globalThemeConfig['custom_logo'] ?? null;
$appName    = $globalThemeConfig['app_name'] ?? 'BLISS OS';

// Mapa de Fontes
$fontsMap = [
    'Manrope' => 'https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;700&display=swap',
    'Inter' => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700&display=swap',
    'Roboto' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap',
    'Poppins' => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;700&display=swap',
    'Montserrat' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;700&display=swap',
    'Lato' => 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap',
    'Open Sans' => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;700&display=swap',
    'Raleway' => 'https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;700&display=swap',
    'Nunito' => 'https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;700&display=swap',
    'Playfair Display' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap',
    'Cinzel' => 'https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&display=swap',
    'Oswald' => 'https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&display=swap',
    'Merriweather' => 'https://fonts.googleapis.com/css2?family=Merriweather:wght@300;700&display=swap',
    'Lora' => 'https://fonts.googleapis.com/css2?family=Lora:wght@400;700&display=swap'
];
$fontUrl = $fontsMap[$fontFam] ?? $fontsMap['Manrope'];
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?php echo $fontUrl; ?>" rel="stylesheet">

<style>
    :root {
        --accent: <?php echo $pColor; ?> !important;
        --btn-text: <?php echo $btnText; ?> !important;
        --radius: <?php echo $radius; ?> !important;
        --font-body: '<?php echo $fontFam; ?>', sans-serif !important;
        --bg-grid: <?php echo $bgColor; ?> !important;
        
        /* Variáveis de Texto */
        --txt-hero: <?php echo $heroTxt; ?> !important;
        --txt-welcome: <?php echo $welcomeTxt; ?> !important;
        --txt-grid: <?php echo $gridTxt; ?> !important;
    }

    /* APLICAÇÃO GLOBAL */
    body { font-family: var(--font-body); }
    h1, h2, h3, .section-title { font-family: var(--font-body); }

    /* Botões */
    .btn-primary, .btn-approve, .btn-large, .scroll-indicator, .status-dot.st-approved, .nav-btn:hover {
        background-color: var(--accent);
        border-color: var(--accent);
        color: var(--btn-text);
        border-radius: var(--radius);
    }
    .client-tag { color: var(--accent); }
    
    /* Arredondamento Geral */
    .card, .modal-content, .form-input, .btn { border-radius: var(--radius); }

    /* --- CORREÇÃO DAS CORES ESPECÍFICAS --- */
    
    /* 1. Hero (Capa) */
    #hero h1 { color: var(--txt-hero) !important; }
    #hero .client-tag { color: var(--accent) !important; } 
    #hero .scroll-indicator { color: var(--txt-hero) !important; }
    #hero .brand-text-fallback { color: var(--txt-hero) !important; }

    /* 2. Welcome (Boas-vindas) */
    #welcome { background-color: #ffffff !important; } /* Fundo branco fixo para contraste ou variável se quiser */
    #welcome .welcome-text { color: var(--txt-welcome) !important; }

    /* 3. Grid (Timeline) */
    #work { background-color: var(--bg-grid) !important; }
    .grid-header .section-title, .grid-header span { color: var(--txt-grid) !important; }
    .card-title { color: var(--txt-grid) !important; }
    
    /* Ajuste para o Card ficar legível se o fundo for claro */
    .card { 
        background: rgba(255,255,255,0.05); 
        border: 1px solid rgba(255,255,255,0.1);
    }
    /* Se o fundo do grid for muito claro, escurece o card automaticamente */
    <?php if($bgColor == '#ffffff' || $bgColor == '#fff'): ?>
        .card { background: #f8f9fa; border: 1px solid #ddd; }
        .card-title { color: #333 !important; }
    <?php endif; ?>
</style>