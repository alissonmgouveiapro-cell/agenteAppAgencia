<?php
/* Arquivo: /modules/branding/personalizar.php */
/* Versão: Estável (Sem Page Builder) */

session_start();
require '../../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

// --- SALVAR CONFIGURAÇÕES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cores e Estilo
    $pColor = $_POST['primary_color'];
    $btnTxt = $_POST['btn_text_color'] ?? '#ffffff';
    $radius = $_POST['btn_border_radius'] ?? '8px';
    
    // Cores de Texto (Com fallback para não dar erro)
    $heroTxt    = $_POST['hero_text_color'] ?? '#ffffff';
    $welcomeTxt = $_POST['welcome_text_color'] ?? '#333333';
    $gridTxt    = $_POST['grid_text_color'] ?? '#ffffff';
    $bColor     = $_POST['bg_color'] ?? '#050505';
    
    $font = $_POST['font_family'];
    $app  = $_POST['app_name'];

    // Upload Logo
    $logoPath = null;
    $uploadDir = '../../uploads/logos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (!empty($_FILES['app_logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['app_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'])) {
            $newName = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['app_logo']['tmp_name'], $uploadDir . $newName)) $logoPath = $newName;
        }
    }

    // Verifica ID 1
    $check = $pdo->query("SELECT id FROM system_settings WHERE id=1")->fetch();
    if (!$check) $pdo->query("INSERT INTO system_settings (id, tenant_id) VALUES (1, 1)");

    $sql = "UPDATE system_settings SET 
            primary_color=?, btn_text_color=?, btn_border_radius=?, 
            hero_text_color=?, welcome_text_color=?, grid_text_color=?, bg_color=?, 
            font_family=?, app_name=?";
    $params = [$pColor, $btnTxt, $radius, $heroTxt, $welcomeTxt, $gridTxt, $bColor, $font, $app];

    if ($logoPath) { $sql .= ", custom_logo=?"; $params[] = $logoPath; }

    $sql .= " WHERE id=1";
    
    // Tenta executar (se faltar coluna no banco, ignora para não crashar)
    try {
        $pdo->prepare($sql)->execute($params);
        $msg = "✨ Aparência salva!";
    } catch(Exception $e) {
        $msg = "Erro ao salvar. Verifique o banco.";
    }
}

// Buscar Dados
$sett = [];
try {
    $stmt = $pdo->query("SELECT * FROM system_settings WHERE id=1");
    if($stmt) $sett = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Defaults
$defaults = [
    'primary_color'=>'#d4af37', 'btn_text_color'=>'#ffffff', 'btn_border_radius'=>'8px',
    'hero_text_color'=>'#ffffff', 'welcome_text_color'=>'#333333', 'grid_text_color'=>'#ffffff', 'bg_color'=>'#050505',
    'font_family'=>'Manrope', 'app_name'=>'Agência', 'custom_logo'=>null
];
$sett = array_merge($defaults, is_array($sett) ? $sett : []);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Personalizar Marca</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;700&family=Inter:wght@400;700&family=Roboto:wght@400;700&family=Poppins:wght@400;700&family=Cinzel:wght@400;700&display=swap" rel="stylesheet">

    <style>
        .config-layout { display: grid; grid-template-columns: 350px 1fr; gap: 30px; height: calc(100vh - 100px); overflow: hidden; }
        .sidebar-scroll { overflow-y: auto; padding-right: 10px; }
        .card-panel { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .form-label { font-weight: bold; display: block; margin-bottom: 5px; font-size: 0.9rem; }
        .form-input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .color-row { display: flex; gap: 10px; margin-bottom: 10px; }
        
        .preview-area {
            background: #e5e5e5; display: flex; flex-direction: column; align-items: center; justify-content: center;
            border-radius: 12px; position: relative;
        }
        
        /* Device Frame */
        .preview-frame {
            background: #fff; border: 10px solid #333; border-radius: 30px; 
            width: 375px; height: 750px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        .frame-desktop { width: 100%; height: 100%; border: none; border-radius: 0; }
        
        /* Client Scope (Vars) */
        .client-scope {
            height: 100%; overflow-y: auto;
            --c-primary: <?php echo $sett['primary_color']; ?>;
            --c-btn-text: <?php echo $sett['btn_text_color']; ?>;
            --c-radius: <?php echo $sett['btn_border_radius']; ?>;
            --c-hero-text: <?php echo $sett['hero_text_color']; ?>;
            --c-welcome-text: <?php echo $sett['welcome_text_color']; ?>;
            --c-grid-text: <?php echo $sett['grid_text_color']; ?>;
            --c-bg: <?php echo $sett['bg_color']; ?>;
            font-family: '<?php echo $sett['font_family']; ?>', sans-serif;
        }

        /* Mockup Styles */
        .mock-hero { height: 200px; background: #333; color: var(--c-hero-text); display: flex; align-items: center; justify-content: center; position: relative; }
        .mock-welcome { padding: 30px 20px; text-align: center; color: var(--c-welcome-text); background: #fff; }
        .mock-grid { background: var(--c-bg); padding: 20px; min-height: 400px; }
        .mock-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: var(--c-radius); margin-bottom: 15px; padding: 10px; }
        .mock-title { color: var(--c-grid-text); }
        .mock-btn { background: var(--c-primary); color: var(--c-btn-text); border-radius: var(--c-radius); border:none; padding:8px; width:100%; cursor:default; }
    </style>
</head>
<body style="overflow:hidden;">

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <h2 style="margin-top:0;">🎨 Personalizar Marca</h2>
        
        <form method="POST" enctype="multipart/form-data" class="config-layout">
            
            <div class="sidebar-scroll">
                <?php if(isset($msg)): ?><div style="color:green; margin-bottom:10px;"><?php echo $msg; ?></div><?php endif; ?>
                
                <div class="card-panel">
                    <label class="form-label">Nome Agência</label>
                    <input type="text" name="app_name" id="iAppName" value="<?php echo htmlspecialchars($sett['app_name']); ?>" class="form-input" oninput="updatePreview()">
                    
                    <label class="form-label">Logo</label>
                    <input type="file" name="app_logo" class="form-input">
                    
                    <label class="form-label">Fonte</label>
                    <select name="font_family" id="iFont" class="form-input" onchange="updatePreview()">
                        <option value="Manrope">Manrope</option>
                        <option value="Inter">Inter</option>
                        <option value="Roboto">Roboto</option>
                        <option value="Cinzel">Cinzel</option>
                    </select>
                </div>

                <div class="card-panel">
                    <div class="color-row">
                        <div><label class="form-label">Destaque</label><input type="color" name="primary_color" id="iPrimary" value="<?php echo $sett['primary_color']; ?>" oninput="updatePreview()"></div>
                        <div><label class="form-label">Txt Botão</label><input type="color" name="btn_text_color" id="iBtnText" value="<?php echo $sett['btn_text_color']; ?>" oninput="updatePreview()"></div>
                    </div>
                    <label class="form-label">Arredondamento</label>
                    <select name="btn_border_radius" id="iRadius" class="form-input" onchange="updatePreview()">
                        <option value="0px">Quadrado</option>
                        <option value="8px">Padrão</option>
                        <option value="20px">Redondo</option>
                    </select>
                </div>

                <div class="card-panel">
                    <label class="form-label">Cores de Texto</label>
                    <div class="color-row">
                        <div><label class="form-label">Capa</label><input type="color" name="hero_text_color" id="iHeroTxt" value="<?php echo $sett['hero_text_color']; ?>" oninput="updatePreview()"></div>
                        <div><label class="form-label">Welcome</label><input type="color" name="welcome_text_color" id="iWelTxt" value="<?php echo $sett['welcome_text_color']; ?>" oninput="updatePreview()"></div>
                    </div>
                    <div class="color-row">
                        <div><label class="form-label">Fundo Grid</label><input type="color" name="bg_color" id="iBg" value="<?php echo $sett['bg_color']; ?>" oninput="updatePreview()"></div>
                        <div><label class="form-label">Txt Grid</label><input type="color" name="grid_text_color" id="iGridTxt" value="<?php echo $sett['grid_text_color']; ?>" oninput="updatePreview()"></div>
                    </div>
                </div>

                <button class="btn-primary" style="width:100%; padding:10px;">Salvar Alterações</button>
            </div>

            <div class="preview-area">
                <div style="position:absolute; top:10px; z-index:10; background:#fff; padding:5px; border-radius:20px;">
                    <button type="button" onclick="setFrame('mobile')" style="border:none; background:none; cursor:pointer;">📱</button>
                    <button type="button" onclick="setFrame('desktop')" style="border:none; background:none; cursor:pointer;">💻</button>
                </div>

                <div id="previewFrame" class="preview-frame">
                    <div class="client-scope" id="scope">
                        <div class="mock-hero">
                            <div style="position:absolute; top:15px; left:15px; font-weight:bold;" id="pApp"><?php echo $sett['app_name']; ?></div>
                            <div style="text-align:center;">
                                <div style="color:var(--c-primary); font-size:0.7rem; letter-spacing:2px; font-weight:bold;">CLIENTE</div>
                                <h2 style="margin:5px 0;">Campanha Exemplo</h2>
                            </div>
                        </div>
                        <div class="mock-welcome">
                            "Bem-vindo à área de aprovação."
                        </div>
                        <div class="mock-grid">
                            <div class="mock-title" style="margin-bottom:10px;">Timeline</div>
                            <div class="mock-card">
                                <div style="height:100px; background:#222; margin-bottom:10px;"></div>
                                <div class="mock-title">Post Exemplo</div>
                                <button class="mock-btn">VER POST</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </main>
</div>

<script>
    function setFrame(mode) {
        const f = document.getElementById('previewFrame');
        if(mode==='mobile') { f.classList.remove('frame-desktop'); f.style.width='375px'; f.style.borderWidth='10px'; }
        else { f.classList.add('frame-desktop'); f.style.width='100%'; f.style.borderWidth='0'; }
    }

    function updatePreview() {
        const s = document.getElementById('scope');
        s.style.setProperty('--c-primary', document.getElementById('iPrimary').value);
        s.style.setProperty('--c-btn-text', document.getElementById('iBtnText').value);
        s.style.setProperty('--c-radius', document.getElementById('iRadius').value);
        s.style.setProperty('--c-hero-text', document.getElementById('iHeroTxt').value);
        s.style.setProperty('--c-welcome-text', document.getElementById('iWelTxt').value);
        s.style.setProperty('--c-grid-text', document.getElementById('iGridTxt').value);
        s.style.setProperty('--c-bg', document.getElementById('iBg').value);
        s.style.fontFamily = document.getElementById('iFont').value + ", sans-serif";
        document.getElementById('pApp').innerText = document.getElementById('iAppName').value;
    }
</script>
</body>
</html>