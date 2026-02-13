<?php
/* Arquivo: /public/ver.php */
/* Versão: FINAL INTEGRADA (Resultados Reais + Visual Final + Mobile Fix) */

require '../config/db.php';

if (!isset($_GET['t'])) { die("Acesso inválido."); }
$token = $_GET['t'];

// --- DADOS DO PROJETO ---
$stmt = $pdo->prepare("SELECT p.*, c.name as client_name, p.tenant_id FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.share_token = ?");
$stmt->execute([$token]);
$projeto = $stmt->fetch();

if (!$projeto) { die("Projeto não encontrado."); }
$project_id = $projeto['id'];
$tenant_id = $projeto['tenant_id'];

// Capa
$coverUrl = !empty($projeto['cover_image']) 
    ? "../uploads/covers/" . $projeto['cover_image'] 
    : "https://images.unsplash.com/photo-1497215728101-856f4ea42174?q=80&w=1920&auto=format&fit=crop";

$meses_pt = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
$semana_pt = ['Sun'=>'Dom','Mon'=>'Seg','Tue'=>'Ter','Wed'=>'Qua','Thu'=>'Qui','Fri'=>'Sex','Sat'=>'Sáb'];

// --- AÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_month_key'])) {
        $targetMonth = $_POST['approve_month_key'];
        $pdo->prepare("UPDATE project_calendar SET status = 'approved' WHERE project_id = ? AND DATE_FORMAT(post_date, '%Y-%m') = ? AND status = 'pending'")->execute([$project_id, $targetMonth]);
        $pdo->prepare("INSERT INTO notifications (tenant_id, project_id, message, type, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenant_id, $project_id, "✅ Mês Aprovado", 'success']);
        header("Location: ver.php?t=$token&m=".$_GET['m']."#calendario"); exit;
    }
    if (isset($_POST['cal_event_id'])) {
        $st = $_POST['cal_status'];
        $pdo->prepare("UPDATE project_calendar SET status = ?, feedback = ? WHERE id = ?")->execute([$st, $_POST['cal_feedback'] ?? null, $_POST['cal_event_id']]);
        $pdo->prepare("INSERT INTO notifications (tenant_id, project_id, message, type, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenant_id, $project_id, ($st=='approved'?"✅ Pauta OK":"⚠️ Ajuste Pauta"), ($st=='approved'?'success':'warning')]);
        header("Location: ver.php?t=$token&m=".$_GET['m']."#calendario"); exit;
    }
    if (isset($_POST['post_id'])) {
        $st = $_POST['status'];
        $pdo->prepare("UPDATE project_deliverables SET approval_status = ?, internal_status = ?, feedback = ? WHERE id = ?")
            ->execute([$st, ($st=='approved'?'int_approved':'int_working'), $_POST['feedback'] ?? null, $_POST['post_id']]);
        $stmtT = $pdo->prepare("SELECT title FROM project_deliverables WHERE id=?"); $stmtT->execute([$_POST['post_id']]); $tit=$stmtT->fetchColumn();
        $pdo->prepare("INSERT INTO notifications (tenant_id, project_id, message, type, created_at) VALUES (?, ?, ?, ?, NOW())")->execute([$tenant_id, $project_id, ($st=='approved'?"✅ Arte OK: $tit":"⚠️ Ajuste Arte: $tit"), ($st=='approved'?'success':'warning')]);
        header("Location: ver.php?t=$token&m=".$_GET['m']."#entregas"); exit;
    }
}

// --- LEITURA DE DADOS ---

// 1. Analytics (Resultados) - NOVO!
$stmtAnalytics = $pdo->prepare("SELECT * FROM project_analytics WHERE project_id = ? ORDER BY month_year DESC LIMIT 1");
$stmtAnalytics->execute([$project_id]);
$analyticsData = $stmtAnalytics->fetch(PDO::FETCH_ASSOC);

// Formata o mês de referência dos resultados
$analyticsLabel = "Aguardando dados";
if ($analyticsData) {
    $parts = explode('-', $analyticsData['month_year']); // 2023-10
    if(count($parts) == 2) {
        $analyticsLabel = $meses_pt[$parts[1]] . " de " . $parts[0];
    }
}

// 2. Meses Disponíveis
$stmtMonths = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(post_date, '%Y-%m') as mes_ano FROM project_calendar WHERE project_id = ? ORDER BY mes_ano ASC");
$stmtMonths->execute([$project_id]);
$availableMonths = $stmtMonths->fetchAll(PDO::FETCH_COLUMN);
$currentDateRef = date('Y-m');
$filterMonth = (isset($_GET['m'])) ? $_GET['m'] : ((in_array($currentDateRef, $availableMonths)) ? $currentDateRef : ($availableMonths[0] ?? date('Y-m')));

// 3. Calendário
$cronograma = $pdo->prepare("SELECT * FROM project_calendar WHERE project_id = ? ORDER BY post_date ASC");
$cronograma->execute([$project_id]);
$rawCalendar = $cronograma->fetchAll(PDO::FETCH_ASSOC);

$calendarByMonth = []; $monthsList = [];
foreach($rawCalendar as $ev) {
    $ts = strtotime($ev['post_date']);
    $mKey = date('Y-m', $ts);
    $mLabel = $meses_pt[date('m', $ts)] . " " . date('Y', $ts);
    if(!isset($monthsList[$mKey])) $monthsList[$mKey] = $mLabel;
    $calendarByMonth[$mKey][] = $ev;
}
$tsDisplay = strtotime($filterMonth . '-01');
$displayMonthTitle = $meses_pt[date('m', $tsDisplay)] . " de " . date('Y', $tsDisplay);
$activeMonth = $filterMonth;
if(!isset($monthsList[$activeMonth]) && !empty($monthsList)) { $activeMonth = array_key_first($monthsList); }

// 4. Posts e Galeria
$posts = $pdo->prepare("SELECT * FROM project_deliverables WHERE project_id = ? AND approval_status != 'hidden' ORDER BY created_at DESC");
$posts->execute([$project_id]);
$all_posts = $posts->fetchAll();
$filesQuery = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY id ASC");
$filesQuery->execute([$project_id]);
$all_files = $filesQuery->fetchAll();
$post_files = []; 
foreach($all_files as $f) { if(!empty($f['deliverable_id'])) $post_files[$f['deliverable_id']][] = $f; }

$jsGallery = [];
foreach($all_posts as $post) {
    $files = $post_files[$post['id']] ?? [];
    if(empty($files)) continue;
    $mediaList = [];
    foreach($files as $f) {
        $ext = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
        $mediaList[] = ['src' => "../uploads/" . $f['file_path'], 'type' => in_array($ext, ['mp4','mov','webm']) ? 'video' : 'image'];
    }
    $jsGallery[] = [
        'id' => $post['id'], 'title' => $post['title'], 'desc' => nl2br(htmlspecialchars($post['caption'] ?? '')), 'status' => $post['approval_status'], 'created' => date('d/m/Y', strtotime($post['created_at'])), 'media' => $mediaList
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($projeto['client_name']); ?> | Portal</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Manrope:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>

    <style>
        :root {
            --bg: #ffffff; --text-main: #111; --text-sec: #555; --card-bg: #fff;
            --gold: #D4AF37; --black: #000; --border: #e0e0e0;
            --success: #2e8b57; --warning: #cc3300;
            --shadow: 0 10px 30px rgba(0,0,0,0.05);
            --skeleton-base: #e0e0e0; --skeleton-highlight: #f5f5f5;
        }
        [data-theme="dark"] {
            --bg: #121212; --text-main: #f5f5f5; --text-sec: #a0a0a0; --card-bg: #1e1e1e;
            --black: #ffffff; --border: #333; --shadow: 0 10px 30px rgba(0,0,0,0.3);
            --skeleton-base: #333; --skeleton-highlight: #444;
        }

        html { scroll-behavior: smooth; }
        body { margin: 0; padding: 0; background-color: var(--bg); color: var(--text-main); font-family: 'Manrope', sans-serif; -webkit-font-smoothing: antialiased; padding-top: 70px; transition: background-color 0.3s, color 0.3s; }
        * { box-sizing: border-box; outline: none; -webkit-tap-highlight-color: transparent; }
        a { text-decoration: none; color: inherit; transition: 0.3s; }
        h1, h2, h3 { font-family: 'Playfair Display', serif; margin: 0; }
        button { cursor: pointer; }

        /* NAVBAR & MENU */
        .navbar { position: fixed; top: 0; left: 0; width: 100%; height: 70px; z-index: 1000; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); padding: 0 5%; display: flex; justify-content: space-between; align-items: center; transition: background 0.3s; }
        [data-theme="dark"] .navbar { background: rgba(18, 18, 18, 0.95); }
        .brand { font-family: 'Playfair Display', serif; font-weight: 700; font-size: 1.2rem; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; }
        .brand span { color: var(--gold); }
        
        .nav-links { display: flex; gap: 30px; align-items: center; }
        .nav-link { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--text-main); cursor: pointer; }
        .nav-link:hover { color: var(--gold); }
        
        .theme-toggle { cursor: pointer; color: var(--text-main); font-size: 1.2rem; display: flex; align-items: center; }
        .menu-btn { display: none; font-size: 2rem; color: var(--text-main); cursor: pointer; z-index: 3000; }
        .close-menu-btn { display: none; }

        /* HERO */
        .hero { position: relative; height: 60vh; min-height: 450px; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; color: #fff; overflow: hidden; }
        .hero-bg { position: absolute; inset: 0; background-image: url('<?php echo $coverUrl; ?>'); background-size: cover; background-position: center; filter: brightness(0.4); transform: scale(1.05); }
        .hero-content { position: relative; z-index: 2; padding: 20px; max-width: 900px; }
        .hero-tag { color: var(--gold); font-weight: 700; letter-spacing: 3px; text-transform: uppercase; font-size: 0.8rem; margin-bottom: 20px; display: block; }
        .typewriter { font-size: 3rem; line-height: 1.1; margin-bottom: 20px; font-weight: 400; color: #fff; }
        .hero p { font-size: 1.1rem; color: rgba(255,255,255,0.9); font-weight: 300; max-width: 600px; margin: 0 auto; line-height: 1.6; }

        /* SECTIONS & UTILS */
        .section { padding: 80px 5%; max-width: 1400px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 50px; }
        .section-header h2 { font-size: 2.2rem; color: var(--text-main); margin-bottom: 10px; }
        .divider { width: 60px; height: 3px; background: var(--gold); margin: 20px auto; }
        
        .intro-box { background: var(--card-bg); padding: 40px; border-left: 4px solid var(--gold); font-size: 1.1rem; color: var(--text-sec); line-height: 1.8; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border); border-radius: 8px; }
        
        /* Links Úteis Grid - Centralizado */
        .useful-links { display: flex; justify-content: center; margin-top: 30px; }
        .link-card { background: var(--card-bg); padding: 20px; border: 1px solid var(--border); border-radius: 8px; text-align: center; transition: 0.3s; cursor: pointer; min-width: 200px; }
        .link-card:hover { border-color: var(--gold); transform: translateY(-5px); }
        .link-card i { font-size: 2rem; color: var(--gold); margin-bottom: 10px; }
        .link-card h4 { font-size: 0.9rem; margin: 0; color: var(--text-main); }

        /* CALENDÁRIO */
        .month-scroller { display: flex; justify-content: center; gap: 10px; margin-bottom: 40px; overflow-x: auto; padding-bottom: 10px; }
        .month-btn { padding: 10px 25px; border: 1px solid var(--border); border-radius: 30px; font-size: 0.85rem; color: var(--text-sec); background: var(--card-bg); white-space: nowrap; transition: 0.2s; }
        .month-btn:hover, .month-btn.active { background: var(--text-main); color: var(--bg); border-color: var(--text-main); }
        .cal-container { display: none; animation: fadeIn 0.5s ease; } .cal-container.active { display: block; }
        .cal-list { display: flex; flex-direction: column; gap: 15px; }
        .cal-item { display: flex; align-items: center; justify-content: space-between; padding: 25px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; transition: 0.3s; cursor: pointer; position: relative; overflow: hidden; box-shadow: var(--shadow); }
        .cal-item:before { content:''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--gold); opacity: 0; transition: 0.3s; }
        .cal-item:hover { transform: translateY(-3px); } .cal-item:hover:before { opacity: 1; }
        .cal-date { width: 60px; text-align: center; font-weight: 700; font-family: 'Playfair Display'; font-size: 1.6rem; color: var(--text-main); margin-right: 30px; line-height: 1; }
        .cal-info { flex: 1; }
        .cal-plat { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--gold); letter-spacing: 1px; margin-bottom: 5px; display: block; }
        .cal-title { font-size: 1rem; font-weight: 600; color: var(--text-main); }
        .cal-status { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 6px 12px; border-radius: 4px; background: #f5f5f5; color: #999; }
        .cal-item.approved .cal-status { background: #e8f5e9; color: var(--success); } .cal-item.changes .cal-status { background: #fff3e0; color: var(--warning); }
        .btn-approve-all { display: block; margin: 30px auto 0; background: var(--success); color: #fff; border: none; padding: 12px 30px; border-radius: 30px; font-weight: bold; cursor: pointer; }

        /* GALERIA FILTER BAR */
        .gallery-tools { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px; margin-bottom: 25px; }
        .filter-pills { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 5px; }
        .filter-btn { padding: 8px 16px; border-radius: 20px; border: 1px solid var(--border); background: var(--card-bg); color: var(--text-sec); font-size: 0.8rem; font-weight: 600; cursor: pointer; white-space: nowrap; transition: 0.2s; }
        .filter-btn:hover, .filter-btn.active { background: var(--text-main); color: var(--bg); border-color: var(--text-main); }
        .search-box { position: relative; flex: 1; min-width: 200px; }
        .search-input { width: 100%; padding: 10px 15px 10px 35px; border-radius: 20px; border: 1px solid var(--border); background: var(--card-bg); color: var(--text-main); font-family: inherit; }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-sec); font-size: 1.1rem; }

        /* GRID INSTAGRAM (MOSAICO 4 COLUNAS DESKTOP) */
        .insta-grid { display: grid; width: 100%; margin: 0 auto; grid-template-columns: repeat(4, 1fr); gap: 0; }
        .insta-item { position: relative; aspect-ratio: 1 / 1; background: var(--card-bg); cursor: pointer; overflow: hidden; border-radius: 0; }
        .insta-thumb { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; opacity: 0; }
        .insta-thumb.loaded { opacity: 1; }
        
        .skeleton { position: absolute; inset: 0; background-color: var(--skeleton-base); z-index: 1; }
        .skeleton::after { content: ""; position: absolute; top: 0; right: 0; bottom: 0; left: 0; transform: translateX(-100%); background-image: linear-gradient(90deg, rgba(255, 255, 255, 0) 0, rgba(255, 255, 255, 0.2) 20%, rgba(255, 255, 255, 0.5) 60%, rgba(255, 255, 255, 0)); animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 100% { transform: translateX(100%); } }

        .insta-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); display: flex; justify-content: center; align-items: center; opacity: 0; transition: all 0.3s ease; z-index: 2; }
        .insta-item:hover .insta-overlay { opacity: 1; }
        .insta-item:hover .insta-thumb { transform: scale(1.1); }
        .insta-meta { display: flex; flex-direction: column; align-items: center; gap: 10px; transform: translateY(30px); opacity: 0; transition: all 0.4s cubic-bezier(0.19, 1, 0.22, 1); }
        .insta-item:hover .insta-meta { transform: translateY(0); opacity: 1; }
        .meta-icon { font-size: 2.2rem; color: #fff; transition: 0.3s; }
        .meta-text { font-size: 0.9rem; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 1px; transition: color 0.3s; }
        .insta-item:hover .meta-icon, .insta-item:hover .meta-text { color: var(--gold); }

        .type-icon-insta { position: absolute; top: 10px; right: 10px; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,0.5); z-index: 5; }
        .status-dot { position: absolute; top: 10px; left: 10px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3); z-index: 5; }
        .sd-ok { background: var(--success); } .sd-wait { background: var(--gold); } .sd-change { background: var(--warning); }

        /* ANALYTICS TAB */
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: var(--card-bg); padding: 25px; border-radius: 12px; border: 1px solid var(--border); text-align: center; }
        .metric-val { font-size: 2.5rem; font-weight: 700; color: var(--text-main); margin: 10px 0; font-family: 'Playfair Display'; }
        .metric-label { font-size: 0.85rem; text-transform: uppercase; color: var(--text-sec); letter-spacing: 1px; }
        .chart-placeholder { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; height: 300px; display: flex; align-items: center; justify-content: center; color: var(--text-sec); flex-direction: column; gap: 15px; }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 2000; justify-content: center; align-items: center; padding: 20px; }
        .modal-container { display: flex; width: 100%; max-width: 1200px; height: 90vh; background: var(--card-bg); border-radius: 8px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.7); }
        .modal-media-box { flex: 1.6; background: #000; position: relative; display: flex; align-items: center; justify-content: center; }
        .modal-media-file { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
        .modal-sidebar { flex: 1; min-width: 350px; max-width: 400px; display: flex; flex-direction: column; border-left: 1px solid var(--border); background: var(--card-bg); }
        .modal-sb-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--gold); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .post-meta-info h4 { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-main); }
        .post-meta-info span { font-size: 0.75rem; color: var(--text-sec); }
        .modal-sb-body { flex: 1; padding: 20px; overflow-y: auto; font-size: 0.95rem; line-height: 1.6; color: var(--text-main); }
        .modal-sb-footer { padding: 15px 20px; border-top: 1px solid var(--border); background: var(--card-bg); }
        
        .action-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .btn-act { flex: 1; padding: 12px; border-radius: 8px; border: 1px solid var(--border); font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        
        .btn-approve { background: var(--text-main); color: var(--bg); border-color: var(--text-main); }
        .btn-approve:hover { opacity: 0.9; color: var(--gold); }
        
        .btn-change { background: var(--bg); color: var(--text-main); }
        .btn-change:hover { background: var(--border); }
        
        .btn-download { background: var(--bg); color: var(--text-main); border-color: var(--text-sec); }
        .btn-download:hover { background: var(--border); }

        .close-modal { position: absolute; top: 15px; right: 15px; color: #fff; font-size: 2rem; cursor: pointer; z-index: 2005; }
        .nav-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 35px; height: 35px; background: rgba(255,255,255,0.8); border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; transition: 0.2s; color: #000; }
        .nav-btn:hover { background: #fff; }
        .nb-prev { left: 15px; } .nb-next { right: 15px; }
        .carousel-dots { position: absolute; bottom: 20px; display: flex; gap: 6px; }
        .dot { width: 6px; height: 6px; background: rgba(255,255,255,0.4); border-radius: 50%; }
        .dot.active { background: #fff; }
        .feedback-box { display: none; margin-top: 10px; }
        .input-feed { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; margin-bottom: 10px; font-family: inherit; background: var(--bg); color: var(--text-main); }
        .modal-cal { background: var(--card-bg); padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border); }

        /* FOOTER & UI */
        .app-footer { margin-top: 50px; padding: 30px 20px; border-top: 1px solid var(--border); background: var(--card-bg); text-align: center; }
        .footer-content p { color: var(--text-sec); font-size: 0.8rem; margin: 0; }
        .footer-brand { display: block; margin-top: 10px; color: var(--gold); font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 1px; }
        .back-to-top { position: fixed; bottom: 30px; right: 30px; width: 50px; height: 50px; background: var(--text-main); color: var(--bg); border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 1500; opacity: 0; pointer-events: none; transition: 0.3s; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .back-to-top.visible { opacity: 1; pointer-events: all; }
        .back-to-top:hover { transform: translateY(-5px); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* --- MOBILE OPTIMIZATION --- */
        @media (max-width: 768px) {
            .navbar { padding: 0 20px; }
            .nav-links { position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100%; height: 100dvh; background: var(--bg); display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 40px; z-index: 9999; transform: translateX(100%); transition: transform 0.4s cubic-bezier(0.77, 0, 0.175, 1); }
            .nav-links.active { transform: translateX(0); }
            .nav-link { font-size: 1.5rem; }
            .menu-btn { display: block; }
            .close-menu-btn { display: block; position: absolute; top: 25px; right: 25px; font-size: 2.5rem; color: var(--text-main); cursor: pointer; }
            
            .section { padding: 60px 20px; }
            
            /* GRID MOBILE 3 COLS */
            .insta-grid { grid-template-columns: repeat(3, 1fr); gap: 2px; }
            
            /* MOBILE MODAL */
            .modal-overlay { padding: 0; background: var(--bg); overflow-y: auto; align-items: flex-start; }
            .modal-container { flex-direction: column; width: 100%; max-width: 100%; min-height: 100%; height: auto; border-radius: 0; box-shadow: none; }
            .modal-media-box { flex: none; width: 100%; min-height: 50vh; background: #000; }
            .modal-media-file { width: 100%; height: auto; max-height: 70vh; object-fit: contain; }
            .modal-sidebar { flex: none; width: 100%; border-left: none; position: relative; }
            .modal-sb-body { max-height: none; overflow: visible; padding-bottom: 100px; }
            
            .close-modal { left: 15px; right: auto; top: 15px; background: rgba(0,0,0,0.6); color: white; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; z-index: 2005; }
            .modal-sb-footer { position: sticky; bottom: 0; left: 0; right: 0; padding: 15px; box-shadow: 0 -5px 20px rgba(0,0,0,0.05); z-index: 10; }
            .back-to-top { bottom: 20px; right: 20px; width: 45px; height: 45px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="brand"><?php echo htmlspecialchars($projeto['client_name']); ?> <span>.</span></div>
        <div class="nav-links" id="navMenu">
            <span class="material-icons-round close-menu-btn" onclick="toggleMenu()">close</span>
            <div class="nav-link" onclick="scrollToSec('intro'); toggleMenu()">Sobre</div>
            <div class="nav-link" onclick="scrollToSec('calendario'); toggleMenu()">Calendário</div>
            <div class="nav-link" onclick="scrollToSec('entregas'); toggleMenu()">Entregas</div>
            <div class="nav-link" onclick="scrollToSec('resultados'); toggleMenu()">Resultados</div>
        </div>
        <div style="display:flex; align-items:center; gap:20px;">
            <div class="theme-toggle" onclick="toggleTheme()">
                <span class="material-icons-round" id="themeIcon">dark_mode</span>
            </div>
            <div class="menu-btn" onclick="toggleMenu()"><span class="material-icons-round">menu</span></div>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-bg"></div>
        <div class="hero-content">
            <span class="hero-tag">ÁREA EXCLUSIVA</span>
            <div class="typewriter" id="typewriter"></div>
            <p>Acompanhe a estratégia e aprove os materiais produzidos para sua marca.</p>
        </div>
    </header>

    <div class="divider"></div>

    <section id="intro" class="section">
        <div class="section-header">
            <h2>Visão Geral</h2>
            <p>Estratégia do Mês</p>
        </div>
        <div class="intro-box">
            "Este espaço foi desenhado para facilitar nossa comunicação. Abaixo você encontra o planejamento estratégico e as peças criativas para validação."
        </div>
        
        <div class="useful-links">
            <div class="link-card" onclick="alert('Funcionalidade placeholder: Link para o Drive')">
                <i class="material-icons-round">folder_open</i>
                <h4>Drive de Arquivos</h4>
            </div>
        </div>
    </section>

    <section id="calendario" class="section" style="background: var(--card-bg);">
        <div class="section-header">
            <h2>Pauta de Conteúdo</h2>
            <p>Cronograma de postagens</p>
        </div>
        <div class="month-scroller">
            <?php foreach($monthsList as $key => $label): ?>
                <button class="month-btn <?php echo $key==$activeMonth?'active':''; ?>" onclick="switchMonth('<?php echo $key; ?>')"><?php echo $label; ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach($monthsList as $key => $label): $items = $calendarByMonth[$key] ?? []; $hasPending = false; ?>
            <div id="month-<?php echo $key; ?>" class="cal-container <?php echo $key==$activeMonth?'active':''; ?>">
                <?php if(empty($items)): ?>
                    <p style="text-align:center; color:var(--text-sec); padding:20px;">Sem pautas.</p>
                <?php else: ?>
                    <div class="cal-list">
                    <?php foreach($items as $ev): 
                        if($ev['status'] == 'pending') $hasPending = true;
                        $day = date('d', strtotime($ev['post_date']));
                        $wDay = date('D', strtotime($ev['post_date']));
                        $wk = isset($semana_pt[$wDay]) ? $semana_pt[$wDay] : $wDay;
                        $stClass = ($ev['status']=='approved') ? 'approved' : (($ev['status']=='changes') ? 'changes' : 'pending');
                        $stLabel = ($ev['status']=='approved') ? 'APROVADO' : (($ev['status']=='changes') ? 'AJUSTE' : 'PENDENTE');
                    ?>
                        <div class="cal-item <?php echo $stClass; ?>" onclick="openCalModal(<?php echo $ev['id']; ?>, '<?php echo addslashes($ev['title']); ?>', '<?php echo $day; ?>', '<?php echo $ev['status']; ?>')">
                            <div class="cal-date"><?php echo $day; ?><small><?php echo $wk; ?></small></div>
                            <div class="cal-info">
                                <span class="cal-plat"><?php echo htmlspecialchars($ev['platform']); ?></span>
                                <div class="cal-title"><?php echo htmlspecialchars($ev['title']); ?></div>
                            </div>
                            <div class="cal-status"><?php echo $stLabel; ?></div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <?php if($hasPending): ?>
                        <form method="POST" style="text-align:center; margin-top:30px;">
                            <input type="hidden" name="approve_month_key" value="<?php echo $key; ?>">
                            <button type="submit" class="btn-approve-all">APROVAR MÊS INTEIRO</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <section id="entregas" class="section">
        <div class="section-header">
            <h2>Galeria</h2>
            <span class="section-subtitle">Materiais Recentes</span>
        </div>

        <div class="gallery-tools">
            <div class="filter-pills">
                <button class="filter-btn active" onclick="filterGallery('all')">Todos</button>
                <button class="filter-btn" onclick="filterGallery('pending')">Pendentes</button>
                <button class="filter-btn" onclick="filterGallery('changes')">Ajustes</button>
                <button class="filter-btn" onclick="filterGallery('approved')">Aprovados</button>
            </div>
            <div class="search-box">
                <i class="material-icons-round search-icon">search</i>
                <input type="text" class="search-input" placeholder="Buscar post..." onkeyup="searchGallery(this.value)">
            </div>
        </div>

        <?php if(count($jsGallery) > 0): ?>
            <div class="insta-grid" id="galleryGrid">
                <?php foreach($jsGallery as $index => $item): 
                    $main = (!empty($item['media'])) ? $item['media'][0] : ['src'=>'','type'=>'none'];
                    $mediaCount = count($item['media']);
                    $typeIcon = ($main['type'] == 'video') ? 'play_arrow' : ($mediaCount > 1 ? 'collections' : '');
                    $stDot = ($item['status']=='approved') ? 'sd-ok' : (($item['status']=='changes') ? 'sd-change' : 'sd-wait');
                ?>
                <div class="insta-item" data-status="<?php echo $item['status']; ?>" data-title="<?php echo strtolower($item['title'] . ' ' . $item['desc']); ?>" onclick="openModal(<?php echo $index; ?>)">
                    <div class="skeleton"></div>
                    <?php if($main['type'] == 'video'): ?>
                        <video src="<?php echo $main['src']; ?>" class="insta-thumb" muted preload="metadata" playsinline webkit-playsinline loop onloadeddata="this.classList.add('loaded'); this.previousElementSibling.remove()"></video>
                    <?php else: ?>
                        <img src="<?php echo $main['src']; ?>" class="insta-thumb" loading="lazy" onload="this.classList.add('loaded'); this.previousElementSibling.remove()">
                    <?php endif; ?>
                    
                    <div class="insta-overlay">
                        <div class="insta-meta">
                            <span class="material-icons-round meta-icon">visibility</span>
                            <span class="meta-text">Visualizar</span>
                        </div>
                    </div>
                    
                    <div class="status-dot <?php echo $stDot; ?>"></div>
                    <?php if($typeIcon): ?><span class="material-icons-round type-indicator"><?php echo $typeIcon; ?></span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center; color:var(--text-sec); padding:40px;">Nenhum material.</p>
        <?php endif; ?>
    </section>

    <section id="resultados" class="section" style="background: var(--card-bg);">
        <div class="section-header">
            <h2>Resultados</h2>
            <p>Referência: <?php echo $analyticsLabel; ?></p>
        </div>
        <div class="analytics-grid">
            <div class="metric-card">
                <span class="metric-label">Alcance Total</span>
                <div class="metric-val"><?php echo htmlspecialchars($analyticsData['reach'] ?? '--'); ?></div>
            </div>
            <div class="metric-card">
                <span class="metric-label">Engajamento</span>
                <div class="metric-val"><?php echo htmlspecialchars($analyticsData['engagement'] ?? '--'); ?></div>
            </div>
            <div class="metric-card">
                <span class="metric-label">Novos Seguidores</span>
                <div class="metric-val"><?php echo htmlspecialchars($analyticsData['new_followers'] ?? '--'); ?></div>
            </div>
        </div>
        <div class="chart-placeholder">
            <i class="material-icons-round" style="font-size:3rem;">bar_chart</i>
            <span>Gráficos de crescimento em breve.</span>
        </div>
    </section>

    <footer class="app-footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($projeto['client_name']); ?>. Todos os direitos reservados.</p>
            <span class="footer-brand">Portal do Cliente</span>
        </div>
    </footer>

    <button id="backToTop" class="back-to-top" onclick="scrollToTop()">
        <span class="material-icons-round">arrow_upward</span>
    </button>

    <div id="instaModal" class="modal-overlay">
        <span class="material-icons-round close-modal" onclick="closeModal()">close</span>
        <div class="modal-container">
            <div class="modal-media-box" id="mediaBox"></div>
            <div class="modal-sidebar">
                <div class="modal-sb-header">
                    <div class="user-avatar"><?php echo strtoupper(substr($projeto['client_name'], 0, 1)); ?></div>
                    <div class="post-meta-info">
                        <h4 id="mTitle">Titulo</h4>
                        <span id="mDate">Data</span>
                    </div>
                </div>
                <div class="modal-sb-body">
                    <div id="mDesc" style="margin-bottom:20px;"></div>
                    <div style="border-top:1px solid var(--border); padding-top:15px;">
                        <h5 style="margin:0 0 10px 0; color:var(--text-sec);">Histórico</h5>
                        <div style="font-size:0.8rem; color:var(--text-sec); font-style:italic;">Nenhum comentário anterior.</div>
                    </div>
                </div>
                <div class="modal-sb-footer">
                    <form method="POST" id="formAction">
                        <input type="hidden" name="post_id" id="mPostId">
                        <input type="hidden" name="status" id="mStatus">
                        
                        <div class="action-row" id="defaultActions">
                            <div class="btn-act btn-change" onclick="toggleFeed()"><span class="material-icons-round">edit</span> Ajustar</div>
                            
                            <div class="btn-act btn-download" onclick="downloadCurrent()"><span class="material-icons-round">download</span> Baixar</div>
                            
                            <div class="btn-act btn-approve" onclick="submitAct('approved')"><span class="material-icons-round">check</span> Aprovar</div>
                        </div>

                        <div class="feedback-box" id="feedBox">
                            <textarea name="feedback" class="input-feed" rows="3" placeholder="O que precisa ajustar?"></textarea>
                            <div class="action-row">
                                <div class="btn-act btn-change" onclick="toggleFeed()">Cancelar</div>
                                <div class="btn-act btn-approve" onclick="submitAct('changes')">Enviar</div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="calModal" class="modal-overlay">
        <div class="modal-cal">
            <h3 style="color:var(--text-main); margin-top:0;" id="cTitle"></h3>
            <p style="color:var(--text-sec); margin-bottom:20px;" id="cDate"></p>
            <form method="POST">
                <input type="hidden" name="cal_event_id" id="cId">
                <input type="hidden" name="cal_status" id="cStat">
                <div style="display:flex; gap:10px; justify-content:center;">
                    <button type="submit" class="btn-act btn-change" onclick="document.getElementById('cStat').value='changes'">Ajuste</button>
                    <button type="submit" class="btn-act btn-approve" onclick="document.getElementById('cStat').value='approved'">Aprovar</button>
                </div>
            </form>
            <button onclick="document.getElementById('calModal').style.display='none'" style="margin-top:15px; background:none; border:none; color:var(--text-sec);">Fechar</button>
        </div>
    </div>

    <script>
        gsap.registerPlugin(ScrollTrigger);
        const titleText = "<?php echo htmlspecialchars($projeto['title']); ?>";
        const typeTarget = document.getElementById('typewriter');
        let typeIdx = 0;
        function typeWriter() {
            if(typeIdx < titleText.length) { typeTarget.innerHTML += titleText.charAt(typeIdx); typeIdx++; setTimeout(typeWriter, 80); } 
            else { typeTarget.innerHTML += '<span class="cursor"></span>'; }
        }
        window.onload = () => {
            typeWriter();
            gsap.from(".hero-content", { y: 30, opacity: 0, duration: 1, delay: 0.2 });
            gsap.from(".insta-item", { scrollTrigger: ".insta-grid", y: 20, opacity: 0, stagger: 0.05, duration: 0.5 });
            
            const savedTheme = localStorage.getItem('theme');
            if(savedTheme === 'dark') { document.body.setAttribute('data-theme', 'dark'); document.getElementById('themeIcon').innerText = 'light_mode'; }
        };

        function toggleTheme() {
            const body = document.body;
            const icon = document.getElementById('themeIcon');
            if(body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                icon.innerText = 'dark_mode';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                icon.innerText = 'light_mode';
                localStorage.setItem('theme', 'dark');
            }
        }

        function filterGallery(status) {
            const items = document.querySelectorAll('.insta-item');
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');
            items.forEach(item => {
                if(status === 'all' || item.getAttribute('data-status') === status) { item.style.display = 'block'; } else { item.style.display = 'none'; }
            });
        }

        function searchGallery(term) {
            const items = document.querySelectorAll('.insta-item');
            term = term.toLowerCase();
            items.forEach(item => {
                const title = item.getAttribute('data-title');
                if(title.includes(term)) item.style.display = 'block';
                else item.style.display = 'none';
            });
        }

        function toggleMenu() { 
            const nav = document.getElementById('navMenu');
            nav.classList.toggle('active');
        }
        function scrollToSec(id) { 
            document.getElementById(id).scrollIntoView(); 
            document.getElementById('navMenu').classList.remove('active');
        }
        function switchMonth(key) {
            document.querySelectorAll('.month-btn').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');
            document.querySelectorAll('.cal-container').forEach(c => c.classList.remove('active'));
            document.getElementById('month-'+key).classList.add('active');
        }

        const gallery = <?php echo json_encode($jsGallery); ?>;
        let curIdx = 0; let slideIdx = 0;
        const modal = document.getElementById('instaModal');
        const mediaBox = document.getElementById('mediaBox');

        function openModal(idx) {
            curIdx = idx; slideIdx = 0;
            modal.style.display = 'flex';
            renderContent();
        }

        function closeModal() {
            modal.style.display = 'none';
            mediaBox.innerHTML = '';
        }

        function renderContent() {
            const item = gallery[curIdx];
            const media = item.media[slideIdx];
            const total = item.media.length;

            document.getElementById('mTitle').innerText = item.title;
            document.getElementById('mDate').innerText = item.created;
            document.getElementById('mDesc').innerHTML = item.desc || 'Sem legenda.';
            document.getElementById('mPostId').value = item.id;
            
            document.getElementById('feedBox').style.display = 'none';
            document.getElementById('defaultActions').style.display = 'flex';

            let mediaHtml = '';
            if(media.type === 'video') {
                mediaHtml = `<video src="${media.src}" class="modal-media-file" controls autoplay playsinline webkit-playsinline loop></video>`;
            } else {
                mediaHtml = `<img src="${media.src}" class="modal-media-file">`;
            }

            if(total > 1) {
                if(slideIdx > 0) mediaHtml += `<div class="nav-btn nb-prev" onclick="changeSlide(-1)"><span class="material-icons-round">chevron_left</span></div>`;
                if(slideIdx < total - 1) mediaHtml += `<div class="nav-btn nb-next" onclick="changeSlide(1)"><span class="material-icons-round">chevron_right</span></div>`;
                let dots = '<div class="carousel-dots">';
                for(let i=0; i<total; i++) dots += `<div class="dot ${i===slideIdx?'active':''}"></div>`;
                dots += '</div>';
                mediaHtml += dots;
            }
            mediaBox.innerHTML = mediaHtml;

            const vid = mediaBox.querySelector('video');
            if(vid) { vid.muted = false; vid.volume = 1.0; }
        }

        function changeSlide(dir) { slideIdx += dir; renderContent(); }

        // DOWNLOAD FUNCTION
        function downloadCurrent() {
            const item = gallery[curIdx];
            const media = item.media[slideIdx];
            const a = document.createElement('a');
            a.href = media.src;
            a.download = ''; 
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function toggleFeed() {
            const box = document.getElementById('feedBox');
            const acts = document.getElementById('defaultActions');
            if(box.style.display === 'block') { box.style.display='none'; acts.style.display='flex'; }
            else { box.style.display='block'; acts.style.display='none'; }
        }

        function submitAct(st) {
            document.getElementById('mStatus').value = st;
            if(st === 'approved' && !confirm('Confirmar aprovação?')) return;
            document.getElementById('formAction').submit();
        }

        function openCalModal(id, title, date, status) {
            if(status !== 'pending') return;
            document.getElementById('cId').value = id;
            document.getElementById('cTitle').innerText = title;
            document.getElementById('cDate').innerText = "Dia " + date;
            const m = document.getElementById('calModal');
            m.style.display = 'flex';
        }

        window.addEventListener('scroll', () => {
            const btn = document.getElementById('backToTop');
            if (window.scrollY > 300) btn.classList.add('visible');
            else btn.classList.remove('visible');
        });
        function scrollToTop() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
    </script>
</body>
</html>