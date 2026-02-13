<?php
/* Arquivo: public/form_briefing.php */
/* Versão: Com Capa Personalizada */

require '../config/db.php';

$token = $_GET['t'] ?? '';
$msg = '';

if (!$token) { die("Link inválido."); }

$stmt = $pdo->prepare("SELECT * FROM briefing_forms WHERE public_token = ?");
$stmt->execute([$token]);
$form = $stmt->fetch();

if (!$form) { die("Formulário não encontrado."); }

$fields = json_decode($form['fields_json'], true);

// PROCESSAR ENVIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $answers = [];
    $client_identifier = "Resposta Anônima"; 
    $found_name = false;

    foreach($fields as $k => $f) {
        $field_id = $f['id'];
        $val = '';

        if ($f['type'] === 'checkbox') {
            $val = isset($_POST[$field_id]) ? implode(', ', $_POST[$field_id]) : '';
        } else {
            $val = $_POST[$field_id] ?? '';
        }

        $answers[] = [
            'question' => $f['label'],
            'type' => $f['type'],
            'answer' => $val
        ];

        if (!$found_name && $f['type'] === 'text' && !empty($val)) {
            $labelLower = mb_strtolower($f['label']);
            if (strpos($labelLower, 'nome') !== false || strpos($labelLower, 'cliente') !== false || strpos($labelLower, 'empresa') !== false) {
                $client_identifier = $val;
                $found_name = true;
            }
        }
    }

    if (!$found_name) {
        foreach($answers as $ans) {
            if ($ans['type'] === 'text' && !empty($ans['answer'])) {
                $client_identifier = $ans['answer'];
                break;
            }
        }
    }
    
    $json_answers = json_encode($answers);
    
    $stmtInsert = $pdo->prepare("INSERT INTO briefing_responses (form_id, client_name, answers_json) VALUES (?, ?, ?)");
    if($stmtInsert->execute([$form['id'], $client_identifier, $json_answers])) {
        
        $msgNotif = "📝 Novo Briefing recebido de <strong>$client_identifier</strong> no formulário: {$form['title']}";
        try {
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (tenant_id, type, message, is_read, created_at) VALUES (?, 'success', ?, 0, NOW())");
            $stmtNotif->execute([$form['tenant_id'], $msgNotif]);
        } catch(Exception $e) {}

        $msg = "Obrigado! Suas respostas foram enviadas com sucesso.";
        $showForm = false;
    } else {
        $msg = "Erro ao enviar. Tente novamente.";
        $showForm = true;
    }
} else {
    $showForm = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['title']); ?></title>
    <style>
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 20px; display: flex; justify-content: center; }
        .container { width: 100%; max-width: 640px; }
        
        .header-card { background: white; border-top: 8px solid #000; border-radius: 8px; padding: 25px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .cover-image { width: 100%; height: 200px; object-fit: cover; border-radius: 8px 8px 0 0; margin-bottom: -5px; }
        
        .form-card { background: white; border-radius: 8px; padding: 25px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; }
        
        h1 { margin: 0 0 10px 0; color: #1f2937; font-size: 2rem; }
        .description { color: #4b5563; line-height: 1.5; }
        .question-label { display: block; font-weight: 500; font-size: 1.1rem; margin-bottom: 15px; color: #111; }
        .req-star { color: #ef4444; margin-left: 3px; }
        .input-text, .input-area, .input-select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
        .input-text:focus, .input-area:focus { border-bottom: 2px solid #000; outline: none; background: #f9fafb; }
        .option-label { display: flex; align-items: center; gap: 10px; padding: 8px 0; cursor: pointer; color: #374151; }
        input[type="radio"], input[type="checkbox"] { transform: scale(1.2); cursor: pointer; accent-color: #000; }
        .btn-submit { background: #000; color: white; border: none; padding: 12px 24px; border-radius: 4px; font-size: 1rem; font-weight: 600; cursor: pointer; float: right; }
        .btn-submit:hover { background: #333; }
        .success-box { background: white; padding: 40px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="container">
    <?php if ($msg): ?>
        <div class="success-box">
            <h2 style="color: #166534;">✅ Resposta Enviada!</h2>
            <p><?php echo $msg; ?></p>
        </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
        <form method="POST">
            
            <?php if(!empty($form['cover_image'])): ?>
                <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 15px;">
                    <img src="../uploads/briefings/<?php echo $form['cover_image']; ?>" class="cover-image">
                </div>
            <?php endif; ?>

            <div class="header-card" style="<?php echo !empty($form['cover_image']) ? 'border-top:none;' : ''; ?>">
                <h1><?php echo htmlspecialchars($form['title']); ?></h1>
                <?php if(!empty($form['description'])): ?>
                    <div class="description"><?php echo nl2br(htmlspecialchars($form['description'])); ?></div>
                <?php endif; ?>
                <div style="margin-top:15px; font-size:0.85rem; color:#ef4444;">* Obrigatório</div>
            </div>

            <?php if(is_array($fields) && count($fields) > 0): ?>
                <?php foreach($fields as $f): ?>
                    <div class="form-card">
                        <label class="question-label">
                            <?php echo htmlspecialchars($f['label']); ?>
                            <?php if(!empty($f['required'])): ?><span class="req-star">*</span><?php endif; ?>
                        </label>
                        <?php if($f['type'] === 'text'): ?>
                            <input type="text" name="<?php echo $f['id']; ?>" class="input-text" placeholder="Sua resposta" <?php echo !empty($f['required']) ? 'required' : ''; ?>>
                        <?php elseif($f['type'] === 'textarea'): ?>
                            <textarea name="<?php echo $f['id']; ?>" class="input-area" rows="3" placeholder="Sua resposta" <?php echo !empty($f['required']) ? 'required' : ''; ?>></textarea>
                        <?php elseif($f['type'] === 'radio'): ?>
                            <?php foreach($f['options'] as $opt): ?>
                                <label class="option-label"><input type="radio" name="<?php echo $f['id']; ?>" value="<?php echo htmlspecialchars($opt); ?>" <?php echo !empty($f['required']) ? 'required' : ''; ?>><span><?php echo htmlspecialchars($opt); ?></span></label>
                            <?php endforeach; ?>
                        <?php elseif($f['type'] === 'checkbox'): ?>
                            <?php foreach($f['options'] as $opt): ?>
                                <label class="option-label"><input type="checkbox" name="<?php echo $f['id']; ?>[]" value="<?php echo htmlspecialchars($opt); ?>"><span><?php echo htmlspecialchars($opt); ?></span></label>
                            <?php endforeach; ?>
                        <?php elseif($f['type'] === 'select'): ?>
                            <select name="<?php echo $f['id']; ?>" class="input-select" <?php echo !empty($f['required']) ? 'required' : ''; ?>>
                                <option value="">Selecione...</option>
                                <?php foreach($f['options'] as $opt): ?><option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option><?php endforeach; ?>
                            </select>
                        <?php elseif($f['type'] === 'date'): ?>
                            <input type="date" name="<?php echo $f['id']; ?>" class="input-text" style="max-width: 200px;" <?php echo !empty($f['required']) ? 'required' : ''; ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="form-card" style="text-align:center; color:#666;">Este formulário ainda não tem perguntas.</div>
            <?php endif; ?>

            <div style="padding-bottom: 50px;"><button type="submit" class="btn-submit">Enviar</button></div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>