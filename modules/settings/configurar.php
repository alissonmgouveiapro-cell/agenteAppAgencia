<?php
session_start();
require '../../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') { header("Location: ../../login.php"); exit; }

// Processar Salvamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pColor = $_POST['primary_color'];
    $bColor = $_POST['bg_color'];
    $font   = $_POST['font_family'];
    $app    = $_POST['app_name'];

    // Update (considerando que só tem 1 linha na tabela settings)
    $sql = "UPDATE system_settings SET primary_color=?, bg_color=?, font_family=?, app_name=? WHERE id=1";
    $pdo->prepare($sql)->execute([$pColor, $bColor, $font, $app]);
    
    $msg = "Configurações atualizadas com sucesso!";
}

// Buscar Dados Atuais
$sett = $pdo->query("SELECT * FROM system_settings WHERE id=1")->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Configurações do Sistema</title>
    <link rel="stylesheet" href="../../assets/css/style.css"> <?php include '../../includes/theme_config.php'; ?> </head>
<body>
<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <h1>⚙️ Identidade Visual</h1>
        <p>Personalize as cores e fontes do sistema e da área do cliente.</p>

        <?php if(isset($msg)): ?><div style="background:#dcfce7; color:green; padding:15px; border-radius:8px; margin:20px 0;"><?php echo $msg; ?></div><?php endif; ?>

        <form method="POST" style="max-width:600px; background:#fff; padding:30px; border-radius:12px; margin-top:20px; box-shadow:0 4px 10px rgba(0,0,0,0.05);">
            
            <div style="margin-bottom:20px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Nome do Sistema</label>
                <input type="text" name="app_name" value="<?php echo htmlspecialchars($sett['app_name']); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Cor de Destaque (Botões/Links)</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input type="color" name="primary_color" value="<?php echo $sett['primary_color']; ?>" style="width:50px; height:50px; border:none; cursor:pointer;">
                        <span><?php echo $sett['primary_color']; ?></span>
                    </div>
                </div>
                <div>
                    <label style="display:block; font-weight:bold; margin-bottom:5px;">Cor de Fundo (Área Cliente)</label>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <input type="color" name="bg_color" value="<?php echo $sett['bg_color']; ?>" style="width:50px; height:50px; border:none; cursor:pointer;">
                        <span><?php echo $sett['bg_color']; ?></span>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:30px;">
                <label style="display:block; font-weight:bold; margin-bottom:5px;">Fonte Principal</label>
                <select name="font_family" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:5px;">
                    <option value="Manrope" <?php if($sett['font_family']=='Manrope') echo 'selected'; ?>>Manrope (Moderna)</option>
                    <option value="Inter" <?php if($sett['font_family']=='Inter') echo 'selected'; ?>>Inter (Padrão Interface)</option>
                    <option value="Roboto" <?php if($sett['font_family']=='Roboto') echo 'selected'; ?>>Roboto (Google)</option>
                    <option value="Poppins" <?php if($sett['font_family']=='Poppins') echo 'selected'; ?>>Poppins (Geométrica)</option>
                    <option value="Cinzel" <?php if($sett['font_family']=='Cinzel') echo 'selected'; ?>>Cinzel (Luxo/Serifa)</option>
                </select>
            </div>

            <button type="submit" class="btn-primary" style="padding:15px 30px; font-size:1rem;">Salvar Alterações</button>
        </form>
    </main>
</div>
</body>
</html>