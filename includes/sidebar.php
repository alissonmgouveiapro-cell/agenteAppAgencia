<?php
/* Arquivo: includes/sidebar.php */
/* Versão: Sidebar Responsiva (Menu Hambúrguer) */

// 1. Lógica de Caminhos
$path = '';
if (file_exists('config/db.php')) { $path = ''; } 
elseif (file_exists('../../config/db.php')) { $path = '../../'; } 
elseif (file_exists('../config/db.php')) { $path = '../'; }

// 2. Conexão e Sessão
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($pdo)) {
    $dbFile = $path . 'config/db.php';
    if(file_exists($dbFile)) require $dbFile;
}

// 3. Notificações e Avatar
$notifCount = 0;
if (isset($pdo) && isset($_SESSION['tenant_id'])) {
    try {
        $stmtN = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE tenant_id = ? AND is_read = 0");
        $stmtN->execute([$_SESSION['tenant_id']]);
        $notifCount = $stmtN->fetchColumn();
    } catch (Exception $e) {}
}

$avatar_path = "";
if (!empty($_SESSION['user_avatar'])) {
    $avatar_path = $path . "uploads/avatars/" . $_SESSION['user_avatar'];
}
?>

<link rel="manifest" href="<?php echo $path; ?>manifest.json">
<meta name="theme-color" content="#0f172a">
<link rel="apple-touch-icon" href="<?php echo $path; ?>assets/img/icon-192.png">

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => { navigator.serviceWorker.register('<?php echo $path; ?>sw.js').catch(err => {}); });
    }
</script>
<audio id="notifSound" src="https://cdn.pixabay.com/audio/2022/03/15/audio_736858a7da.mp3" preload="auto"></audio>

<style>
    /* CSS Específico da Sidebar (Complementa o style.css) */
    .sidebar-nav::-webkit-scrollbar { width: 4px; }
    .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
    .sidebar-nav::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.1); border-radius: 10px; }
    
    .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: inherit; text-decoration: none; transition: 0.2s; border-radius: 8px; margin-bottom: 2px; position: relative; border: none; background: none; width: 100%; text-align: left; cursor: pointer; }
    .nav-link:hover { background: rgba(255,255,255,0.05); color: #fff; }
    
    .nav-icon { width: 24px; height: 24px; text-align: center; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
    .nav-text { flex: 1; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    .badge-counter { background: #ef4444; color: white; font-size: 0.7rem; font-weight: bold; padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center; position: absolute; right: 10px; top: 12px; }

    /* --- MOBILE HEADER & OVERLAY (ESSENCIAL) --- */
    .mobile-header {
        display: none; /* Escondido no PC */
        position: fixed; top: 0; left: 0; width: 100%; height: 60px;
        background: #0a0a0a; /* Mesma cor da sidebar */
        border-bottom: 1px solid rgba(255,255,255,0.1);
        z-index: 9998;
        align-items: center; justify-content: space-between; padding: 0 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    .mobile-logo { font-family: 'Space Grotesk', sans-serif; font-weight: 700; color: #fff; font-size: 1.2rem; letter-spacing: 1px; }
    .btn-hamburger { background: none; border: none; color: #fff; font-size: 1.6rem; cursor: pointer; padding: 5px; }
    
    .sidebar-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
        z-index: 9998; display: none; opacity: 0; transition: opacity 0.3s;
    }
    .sidebar-overlay.active { display: block; opacity: 1; }

    /* MEDIA QUERY INCORPORADA PARA GARANTIR FUNCIONAMENTO */
    @media (max-width: 900px) {
        .mobile-header { display: flex; }
        
        .sidebar {
            position: fixed !important; top: 0 !important; left: 0 !important; bottom: 0 !important;
            width: 280px !important; height: 100vh !important;
            background: #0a0a0a !important; z-index: 9999 !important;
            transform: translateX(-100%); /* Escondido na esquerda */
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 5px 0 15px rgba(0,0,0,0.3);
            border-right: 1px solid rgba(255,255,255,0.1);
            display: flex !important; flex-direction: column !important;
        }
        
        .sidebar.active { transform: translateX(0); } /* Aparece */

        /* Ajustes visuais dentro do menu mobile */
        .sidebar-header { display: block !important; padding: 20px 15px !important; }
        .sidebar-nav { padding-top: 10px !important; }
        
        .nav-link { flex-direction: row !important; justify-content: flex-start !important; text-align: left !important; }
        .nav-icon { font-size: 1.2rem !important; margin: 0 !important; }
        .nav-text { display: block !important; font-size: 0.95rem !important; }
    }
</style>

<div id="mobileOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="mobile-header">
    <div class="mobile-logo">BLISS OS</div>
    <button class="btn-hamburger" onclick="toggleSidebar()">☰</button>
</div>

<aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <a href="<?php echo $path; ?>index.php" style="text-decoration:none; color:inherit; display:block;">
            <h3 class="brand" style="margin:0; letter-spacing: 1px;">BLISS OS</h3>
        </a>
        <span class="tenant-badge" style="opacity: 0.6; font-size: 0.75rem; display:block; margin-top:5px;"><?php echo htmlspecialchars($_SESSION['tenant_name'] ?? 'Agência'); ?></span>
    </div>
    
    <nav class="sidebar-nav" style="flex: 1; padding: 0 10px; overflow-y: auto; overflow-x: hidden;">
        <a href="<?php echo $path; ?>index.php" class="nav-link">
            <span class="nav-icon">📊</span><span class="nav-text">Dashboard</span>
        </a>
        <a href="<?php echo $path; ?>modules/notifications/notificacao.php" class="nav-link">
            <span class="nav-icon">🔔</span><span class="nav-text">Notificações</span>
            <span id="sidebar-notif-badge" class="badge-counter" style="<?php echo ($notifCount > 0) ? '' : 'display:none;'; ?>"><?php echo $notifCount; ?></span>
        </a>
        <a href="<?php echo $path; ?>modules/projects/projetos.php" class="nav-link">
            <span class="nav-icon">🚀</span><span class="nav-text">Projetos</span>
        </a>
        <a href="<?php echo $path; ?>modules/clients/clientes.php" class="nav-link">
            <span class="nav-icon">👥</span><span class="nav-text">Clientes</span>
        </a>
        <a href="<?php echo $path; ?>modules/team/equipe.php" class="nav-link">
            <span class="nav-icon">💼</span><span class="nav-text">Equipe</span>
        </a>
        <a href="<?php echo $path; ?>modules/team/radar.php" class="nav-link">
            <span class="nav-icon">📡</span><span class="nav-text">Radar Alocação</span>
        </a>
        <a href="<?php echo $path; ?>modules/briefings/briefing.php" class="nav-link">
            <span class="nav-icon">📝</span><span class="nav-text">Briefings</span>
        </a>
        <a href="<?php echo $path; ?>modules/meetings/reuniao.php" class="nav-link">
            <span class="nav-icon">📹</span><span class="nav-text">Reuniões</span>
        </a>
    </nav>

    <div class="sidebar-footer" style="border-top: 1px solid rgba(255,255,255,0.1); padding: 1rem 15px; flex-shrink: 0;">
        <div id="sidebar-time" style="font-family: 'Space Grotesk', monospace; font-size: 1.2rem; color: #fff; margin-bottom: 1rem; text-align: center; opacity:0.8;">00:00</div>

        <a href="<?php echo $path; ?>modules/profile/minha_conta.php" style="text-decoration: none; display: flex; align-items: center; gap: 10px; margin-bottom: 5px; padding: 5px 0;">
            <div style="width: 32px; height: 32px; border-radius: 50%; background: #333; display:flex; align-items:center; justify-content:center; color:#fff; overflow:hidden; border: 2px solid rgba(255,255,255,0.2); flex-shrink:0;">
                <?php if (!empty($_SESSION['user_avatar']) && !empty($avatar_path)): ?>
                    <img src="<?php echo $avatar_path; ?>?v=<?php echo time(); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div style="font-size: 0.85rem; color: #ccc;">Minha Conta</div>
        </a>

        <button id="theme-toggle" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 10px; width: 100%; padding: 5px 0; margin-bottom: 10px; color: inherit; text-align: left;">
            <div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <span id="theme-icon">🌙</span>
            </div>
            <div style="font-size: 0.85rem; color: #ccc;" id="theme-text">Tema Escuro</div>
        </button>
        
        <a href="<?php echo $path; ?>logout.php" style="display: block; margin-top: 5px; color: #ef4444; font-size: 0.8rem; text-decoration: none; text-align:center;">Sair do Sistema</a>
    </div>
</aside>

<script>
    window.BASE_URL = "<?php echo $path; ?>";

    // 1. MENU HAMBÚRGUER (TOGGLE)
    function toggleSidebar() {
        const sidebar = document.getElementById('appSidebar');
        const overlay = document.getElementById('mobileOverlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    // Fecha menu ao clicar em link no mobile
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if(window.innerWidth <= 900) toggleSidebar();
        });
    });

    // 2. TEMA
    const toggleBtn = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');
    const themeText = document.getElementById('theme-text');
    const htmlRoot = document.documentElement;
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme === 'dark') {
        htmlRoot.setAttribute('data-theme', 'dark');
        themeIcon.innerText = '☀️'; themeText.innerText = 'Tema Claro';
    }

    toggleBtn.addEventListener('click', () => {
        if (htmlRoot.getAttribute('data-theme') === 'dark') {
            htmlRoot.removeAttribute('data-theme'); localStorage.setItem('theme', 'light');
            themeIcon.innerText = '🌙'; themeText.innerText = 'Tema Escuro';
        } else {
            htmlRoot.setAttribute('data-theme', 'dark'); localStorage.setItem('theme', 'dark');
            themeIcon.innerText = '☀️'; themeText.innerText = 'Tema Claro';
        }
    });

    // 3. RELÓGIO
    function updateSidebarClock() {
        const now = new Date();
        const el = document.getElementById('sidebar-time');
        if(el) el.innerText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    setInterval(updateSidebarClock, 1000); updateSidebarClock();

    // 4. NOTIFICAÇÕES (Polling)
    let lastCount = <?php echo $notifCount; ?>;
    const checkApi = window.BASE_URL + 'modules/notifications/check_new.php';

    function checkNewNotifications() {
        fetch(checkApi).then(res => res.json()).then(data => {
            const current = parseInt(data.unread);
            const badge = document.getElementById('sidebar-notif-badge');
            if (current > 0) { badge.style.display = 'inline-block'; badge.innerText = current; } 
            else { badge.style.display = 'none'; }
            if (current > lastCount) { try { document.getElementById('notifSound').play().catch(e => {}); } catch(e) {} }
            lastCount = current;
        }).catch(err => {});
    }
    setInterval(checkNewNotifications, 15000);
</script>

<?php include __DIR__ . '/chat_widget.php'; ?>