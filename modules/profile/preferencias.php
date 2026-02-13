<?php
/* Arquivo: /modules/profile/preferencias.php */
/* Versão: Corrigida - Tratamento de Erros de Array */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// --- 1. AUTO-MIGRAÇÃO ---
function addCol($pdo, $table, $col, $type) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $col $type");
        }
    } catch (Exception $e) {}
}

addCol($pdo, 'users', 'pref_language', "VARCHAR(10) DEFAULT 'pt_BR'");
addCol($pdo, 'users', 'pref_font', "VARCHAR(50) DEFAULT 'default'");
addCol($pdo, 'users', 'pref_timezone', "VARCHAR(50) DEFAULT 'America/Sao_Paulo'");
addCol($pdo, 'users', 'pref_timezone_auto', "TINYINT(1) DEFAULT 1"); 

// --- 2. PROCESSAR SALVAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = $_POST['pref_language'];
    $font = $_POST['pref_font'];
    $tz_auto = isset($_POST['pref_timezone_auto']) ? 1 : 0;
    $tz = ($tz_auto == 1) ? $_POST['detected_timezone'] : $_POST['manual_timezone'];

    $stmt = $pdo->prepare("UPDATE users SET pref_language=?, pref_font=?, pref_timezone=?, pref_timezone_auto=? WHERE id=?");
    $stmt->execute([$lang, $font, $tz, $tz_auto, $user_id]);

    // Atualiza Sessão
    $_SESSION['user_font'] = $font;
    $_SESSION['user_timezone'] = $tz;
    $_SESSION['user_lang'] = $lang;
    
    header("Location: preferencias.php?msg=saved");
    exit;
}

// --- 3. BUSCAR DADOS ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$currentFont = $user['pref_font'] ?? 'default';
$currentTimezone = $user['pref_timezone'] ?? 'America/Sao_Paulo';
$isAutoTz = $user['pref_timezone_auto'] == 1;
// Garante que existe um valor padrão válido
$currentLang = (!empty($user['pref_language'])) ? $user['pref_language'] : 'pt_BR';

// --- 4. SISTEMA DE TRADUÇÃO (DICIONÁRIO ROBUSTO) ---
$dict = [
    'pt_BR' => [
        'title' => 'Preferências',
        'subtitle' => 'Personalize a sua experiência no sistema.',
        'msg_success' => '✅ Preferências atualizadas com sucesso!',
        'sec_general' => 'Geral',
        'lbl_lang' => 'Idioma do Sistema',
        'desc_lang' => 'Escolha o idioma principal da interface.',
        'lbl_font' => 'Tipografia',
        'desc_font' => 'Altere a fonte para melhorar a legibilidade.',
        'lbl_tz' => 'Fuso Horário',
        'desc_tz' => 'Defina o horário para notificações e calendário.',
        'opt_auto' => 'Automático',
        'opt_manual' => 'Modo Manual',
        'txt_detected' => 'Detetado',
        'btn_save' => '💾 Salvar',
        'font_def' => 'Padrão (Inter)',
        'font_mod' => 'Moderna (Montserrat)',
        'font_serif' => 'Clássica (Serif)',
        'font_mono' => 'Técnica (Mono)',
        'preview_txt' => 'A rápida raposa marrom pula... 123'
    ],
    'en_US' => [
        'title' => 'Settings',
        'subtitle' => 'Customize your system experience.',
        'msg_success' => '✅ Settings updated successfully!',
        'sec_general' => 'General',
        'lbl_lang' => 'System Language',
        'desc_lang' => 'Choose the main interface language.',
        'lbl_font' => 'Typography',
        'desc_font' => 'Change the font for better readability.',
        'lbl_tz' => 'Timezone',
        'desc_tz' => 'Set time for notifications and calendar.',
        'opt_auto' => 'Automatic',
        'opt_manual' => 'Manual Mode',
        'txt_detected' => 'Detected',
        'btn_save' => '💾 Save',
        'font_def' => 'Default (Inter)',
        'font_mod' => 'Modern (Montserrat)',
        'font_serif' => 'Classic (Serif)',
        'font_mono' => 'Technical (Mono)',
        'preview_txt' => 'The quick brown fox jumps... 123'
    ],
    'es_ES' => [
        'title' => 'Preferencias',
        'subtitle' => 'Personaliza tu experiencia en el sistema.',
        'msg_success' => '✅ ¡Preferencias actualizadas con éxito!',
        'sec_general' => 'General',
        'lbl_lang' => 'Idioma del Sistema',
        'desc_lang' => 'Elige el idioma principal de la interfaz.',
        'lbl_font' => 'Tipografía',
        'desc_font' => 'Cambia la fuente para mejorar la legibilidad.',
        'lbl_tz' => 'Zona Horaria',
        'desc_tz' => 'Configura la hora para notificaciones y calendario.',
        'opt_auto' => 'Automático',
        'opt_manual' => 'Modo Manual',
        'txt_detected' => 'Detectado',
        'btn_save' => '💾 Guardar',
        'font_def' => 'Estándar (Inter)',
        'font_mod' => 'Moderna (Montserrat)',
        'font_serif' => 'Clásica (Serif)',
        'font_mono' => 'Técnica (Mono)',
        'preview_txt' => 'El veloz zorro marrón salta... 123'
    ]
];

// Fallback manual para pt_PT (usa pt_BR)
$dict['pt_PT'] = $dict['pt_BR'];

// --- LÓGICA DE SELEÇÃO SEGURA ---
// Verifica se o idioma salvo existe no array. Se não, usa pt_BR.
if (!array_key_exists($currentLang, $dict)) {
    $currentLang = 'pt_BR';
}
$t = $dict[$currentLang];

// Lista de Fusos
$timezones = [
    'America/Sao_Paulo' => '(GMT-03:00) Brasília',
    'America/Manaus' => '(GMT-04:00) Manaus',
    'America/New_York' => '(GMT-05:00) New York',
    'Europe/Lisbon' => '(GMT+00:00) Lisboa',
    'Europe/London' => '(GMT+00:00) London',
    'Europe/Paris' => '(GMT+01:00) Paris',
    'Asia/Tokyo' => '(GMT+09:00) Tokyo',
];
?>

<!DOCTYPE html>
<html lang="<?php echo substr($currentLang, 0, 2); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $t['title'] ?? 'Preferências'; ?> | Bliss OS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .settings-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            max-width: 700px;
            margin: 0 auto;
            overflow: hidden;
        }
        .st-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-body-alt);
        }
        .st-body { padding: 30px; }
        
        .setting-row {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px dashed var(--border-color);
        }
        .setting-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        
        .st-label h4 { margin: 0 0 5px 0; color: var(--text-main); font-size: 1rem; }
        .st-label p { margin: 0; color: var(--text-muted); font-size: 0.85rem; max-width: 300px; }
        .st-control { flex: 1; max-width: 300px; display: flex; flex-direction: column; align-items: flex-end; }
        
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--accent-color); }
        input:checked + .slider:before { transform: translateX(24px); }

        .preview-text {
            margin-top: 10px; padding: 10px; background: var(--bg-body-alt); 
            border-radius: 6px; font-size: 0.9rem; border: 1px solid var(--border-color);
            width: 100%; text-align: center;
        }
        
        .font-default { font-family: 'Manrope', sans-serif; }
        .font-serif { font-family: 'Georgia', serif; }
        .font-mono { font-family: 'Courier New', monospace; }
        .font-modern { font-family: 'Montserrat', sans-serif; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        
        <div style="margin-bottom: 2rem;">
            <h1 style="margin:0;"><?php echo $t['title'] ?? 'Preferências'; ?></h1>
            <p style="color:var(--text-muted);"><?php echo $t['subtitle'] ?? ''; ?></p>
        </div>

        <?php if(isset($_GET['msg']) && $_GET['msg']=='saved'): ?>
            <div class="alert-box alert-success" style="max-width:700px; margin:0 auto 20px auto; background:#dcfce7; color:#166534; padding:10px; border-radius:8px; text-align:center;">
                <?php echo $t['msg_success'] ?? 'Salvo!'; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="settings-card">
            <div class="st-header">
                <h3 style="margin:0;"><?php echo $t['sec_general'] ?? 'Geral'; ?></h3>
            </div>
            <div class="st-body">
                
                <div class="setting-row">
                    <div class="st-label">
                        <h4><?php echo $t['lbl_lang'] ?? 'Idioma'; ?></h4>
                        <p><?php echo $t['desc_lang'] ?? 'Selecione o idioma.'; ?></p>
                    </div>
                    <div class="st-control">
                        <select name="pref_language" class="form-input">
                            <option value="pt_BR" <?php echo ($currentLang=='pt_BR')?'selected':''; ?>>🇧🇷 Português (Brasil)</option>
                            <option value="pt_PT" <?php echo ($currentLang=='pt_PT')?'selected':''; ?>>🇵🇹 Português (Portugal)</option>
                            <option value="en_US" <?php echo ($currentLang=='en_US')?'selected':''; ?>>🇺🇸 English (US)</option>
                            <option value="es_ES" <?php echo ($currentLang=='es_ES')?'selected':''; ?>>🇪🇸 Español</option>
                        </select>
                    </div>
                </div>

                <div class="setting-row">
                    <div class="st-label">
                        <h4><?php echo $t['lbl_font'] ?? 'Fonte'; ?></h4>
                        <p><?php echo $t['desc_font'] ?? 'Estilo de fonte.'; ?></p>
                    </div>
                    <div class="st-control">
                        <select name="pref_font" id="fontSelect" class="form-input" onchange="previewFont()">
                            <option value="default" <?php echo ($currentFont=='default')?'selected':''; ?>><?php echo $t['font_def'] ?? 'Padrão'; ?></option>
                            <option value="modern" <?php echo ($currentFont=='modern')?'selected':''; ?>><?php echo $t['font_mod'] ?? 'Moderna'; ?></option>
                            <option value="serif" <?php echo ($currentFont=='serif')?'selected':''; ?>><?php echo $t['font_serif'] ?? 'Serif'; ?></option>
                            <option value="mono" <?php echo ($currentFont=='mono')?'selected':''; ?>><?php echo $t['font_mono'] ?? 'Mono'; ?></option>
                        </select>
                        <div id="fontPreview" class="preview-text"><?php echo $t['preview_txt'] ?? 'Preview 123'; ?></div>
                    </div>
                </div>

                <div class="setting-row">
                    <div class="st-label">
                        <h4><?php echo $t['lbl_tz'] ?? 'Fuso Horário'; ?></h4>
                        <p><?php echo $t['desc_tz'] ?? 'Hora local.'; ?></p>
                    </div>
                    <div class="st-control">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                            <span style="font-size:0.85rem;"><?php echo $t['opt_auto'] ?? 'Auto'; ?></span>
                            <label class="switch">
                                <input type="checkbox" name="pref_timezone_auto" id="autoTzToggle" <?php echo $isAutoTz ? 'checked' : ''; ?> onchange="toggleTzInputs()">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <input type="hidden" name="detected_timezone" id="detectedTz">

                        <select name="manual_timezone" id="manualTzSelect" class="form-input" <?php echo $isAutoTz ? 'disabled' : ''; ?>>
                            <?php foreach($timezones as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($currentTimezone==$val)?'selected':''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="tzStatus" style="color:var(--accent-color); font-size:0.75rem; margin-top:5px; text-align:right;">
                            <?php echo $isAutoTz ? ($t['txt_detected']??'Detectado').': ' . $currentTimezone : ($t['opt_manual']??'Manual'); ?>
                        </small>
                    </div>
                </div>

                <div style="margin-top:30px; text-align:right;">
                    <button type="submit" class="btn-primary" style="width:150px;"><?php echo $t['btn_save'] ?? 'Salvar'; ?></button>
                </div>

            </div>
        </form>

    </main>
</div>

<script>
    // --- LÓGICA DE FUSO HORÁRIO ---
    const userTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    document.getElementById('detectedTz').value = userTz;

    const txtDetected = "<?php echo $t['txt_detected'] ?? 'Detectado'; ?>";
    const txtManual = "<?php echo $t['opt_manual'] ?? 'Manual'; ?>";

    function toggleTzInputs() {
        const isAuto = document.getElementById('autoTzToggle').checked;
        const select = document.getElementById('manualTzSelect');
        const status = document.getElementById('tzStatus');

        select.disabled = isAuto;
        
        if (isAuto) {
            select.style.opacity = '0.5';
            status.innerText = txtDetected + ": " + userTz;
        } else {
            select.style.opacity = '1';
            status.innerText = txtManual;
        }
    }

    // --- LÓGICA DE FONTE (PREVIEW) ---
    function previewFont() {
        const val = document.getElementById('fontSelect').value;
        const prev = document.getElementById('fontPreview');
        
        prev.className = 'preview-text'; // Reset
        if(val === 'default') prev.classList.add('font-default');
        if(val === 'modern') prev.classList.add('font-modern');
        if(val === 'serif') prev.classList.add('font-serif');
        if(val === 'mono') prev.classList.add('font-mono');
    }
    
    previewFont();
</script>

</body>
</html>