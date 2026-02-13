<?php
/* Arquivo: /modules/projects/editar_post.php */
/* Versão: UI Renovada (Estilo Modal Moderno) */

session_start();
require '../../config/db.php';

// Verificações de segurança
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) { header("Location: projetos.php"); exit; }

$post_id = $_GET['id'];
$project_id = $_GET['pid'];
$tenant_id = $_SESSION['tenant_id'];

// 1. ATUALIZAR DADOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_post'])) {
    $title = $_POST['title'];
    $type = $_POST['type'];
    $caption = $_POST['caption'];

    $stmt = $pdo->prepare("UPDATE project_deliverables SET title = ?, type = ?, caption = ? WHERE id = ?");
    $stmt->execute([$title, $type, $caption, $post_id]);

    // Upload de Novos Arquivos
    if (isset($_FILES['midias']) && count($_FILES['midias']['name']) > 0) {
        $uploadDir = '../../uploads/';
        $allowed = ['jpg', 'jpeg', 'png', 'mp4', 'mov', 'avi', 'webp'];
        
        for ($i = 0; $i < count($_FILES['midias']['name']); $i++) {
            $fileName = $_FILES['midias']['name'][$i];
            $fileTmp = $_FILES['midias']['tmp_name'][$i];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed) && !empty($fileTmp)) {
                $newName = uniqid() . '_' . time() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($fileTmp, $uploadDir . $newName)) {
                    $pdo->prepare("INSERT INTO project_files (tenant_id, project_id, deliverable_id, uploader_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$tenant_id, $project_id, $post_id, $_SESSION['user_id'], $fileName, $newName, $ext]);
                }
            }
        }
    }
    
    header("Location: detalhes.php?id=$project_id&msg=post_atualizado"); exit;
}

// 2. EXCLUIR ARQUIVO
if (isset($_GET['del_file'])) {
    $file_id = $_GET['del_file'];
    $stmt = $pdo->prepare("SELECT file_path FROM project_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $f = $stmt->fetch();
    
    if ($f && file_exists('../../uploads/' . $f['file_path'])) {
        @unlink('../../uploads/' . $f['file_path']);
    }
    
    $pdo->prepare("DELETE FROM project_files WHERE id = ?")->execute([$file_id]);
    header("Location: editar_post.php?id=$post_id&pid=$project_id"); exit;
}

// BUSCAR DADOS DO POST
$stmt = $pdo->prepare("SELECT * FROM project_deliverables WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) die("Post não encontrado.");

// BUSCAR ARQUIVOS
$files = $pdo->prepare("SELECT * FROM project_files WHERE deliverable_id = ?");
$files->execute([$post_id]);
$files = $files->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Post - <?php echo htmlspecialchars($post['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* Estilos específicos para esta página (Simulando um Modal Clean) */
        body { background-color: var(--bg-body-alt, #f3f4f6); }
        
        .edit-wrapper {
            max-width: 900px;
            margin: 2rem auto;
            background: var(--bg-card, #ffffff);
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color, #e5e7eb);
            overflow: hidden;
        }

        .edit-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--border-color, #e5e7eb);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-body-alt, #f9fafb);
        }

        .edit-header h2 { margin: 0; font-size: 1.25rem; color: var(--text-main, #111827); }

        .btn-back {
            text-decoration: none;
            color: var(--text-muted, #6b7280);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .btn-back:hover { background: #e5e7eb; color: #374151; }

        .edit-body { padding: 30px; }

        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .form-full { grid-column: 1 / -1; }

        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-main, #374151); font-size: 0.9rem; }
        
        .form-input, .form-select, textarea.form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color, #d1d5db);
            border-radius: 6px;
            font-size: 0.95rem;
            background: var(--input-bg, #fff);
            color: var(--text-main, #000);
            transition: border-color 0.2s;
        }
        .form-input:focus, textarea:focus { outline: none; border-color: var(--accent-color, #4338ca); }

        /* Área de Legenda melhorada */
        .caption-box {
            position: relative;
        }
        .caption-box textarea {
            resize: vertical;
            min-height: 150px;
            line-height: 1.5;
        }

        /* Área de Arquivos (Grid) */
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .file-card {
            position: relative;
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            aspect-ratio: 1/1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .file-card img, .file-card video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-type-icon { font-size: 2rem; }

        .btn-remove-file {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 14px;
            transition: transform 0.2s;
        }
        .btn-remove-file:hover { transform: scale(1.1); background: #dc2626; }

        /* Upload Area */
        .upload-area {
            border: 2px dashed var(--border-color, #d1d5db);
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.2s;
            background: var(--bg-body-alt, #f9fafb);
        }
        .upload-area:hover { border-color: var(--accent-color, #4338ca); background: #eef2ff; }
        .upload-area input { display: none; }

        /* Botão Salvar */
        .btn-save {
            background-color: var(--accent-color, #4338ca);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-save:hover { opacity: 0.9; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        
        <div class="edit-wrapper animate-enter">
            <div class="edit-header">
                <h2>✏️ Editar Post</h2>
                <a href="detalhes.php?id=<?php echo $project_id; ?>" class="btn-back">
                    &larr; Voltar / Cancelar
                </a>
            </div>

            <div class="edit-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_post" value="1">

                    <div class="form-grid">
                        <div class="form-full">
                            <div class="form-group">
                                <label class="form-label">Título Interno</label>
                                <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                            </div>
                        </div>

                        <div>
                            <div class="form-group">
                                <label class="form-label">Tipo de Post</label>
                                <select name="type" class="form-select">
                                    <option value="static" <?php echo $post['type']=='static'?'selected':''; ?>>📷 Imagem Única</option>
                                    <option value="carousel" <?php echo $post['type']=='carousel'?'selected':''; ?>>🖼️ Carrossel</option>
                                    <option value="reels" <?php echo $post['type']=='reels'?'selected':''; ?>>🎥 Reels</option>
                                    <option value="video" <?php echo $post['type']=='video'?'selected':''; ?>>🎬 Vídeo</option>
                                    <option value="stories" <?php echo $post['type']=='stories'?'selected':''; ?>>📱 Stories</option>
                                </select>
                            </div>
                        </div>
                        
                        <div></div> 

                        <div class="form-full">
                            <div class="form-group caption-box">
                                <label class="form-label">📝 Legenda do Post</label>
                                <textarea name="caption" class="form-textarea" placeholder="Escreva a legenda aqui..."><?php echo htmlspecialchars($post['caption']); ?></textarea>
                            </div>
                        </div>

                        <div class="form-full">
                            <label class="form-label">Galeria de Mídia</label>
                            
                            <?php if(count($files) > 0): ?>
                                <div class="files-grid">
                                    <?php foreach($files as $f): 
                                        $ext = strtolower($f['file_type']);
                                        $path = "../../uploads/" . $f['file_path'];
                                    ?>
                                        <div class="file-card" title="<?php echo $f['file_name']; ?>">
                                            <?php if(in_array($ext, ['jpg','jpeg','png','webp'])): ?>
                                                <img src="<?php echo $path; ?>">
                                            <?php elseif(in_array($ext, ['mp4','mov','webm'])): ?>
                                                <video src="<?php echo $path; ?>" muted></video>
                                            <?php else: ?>
                                                <div class="file-type-icon">📄</div>
                                            <?php endif; ?>

                                            <a href="editar_post.php?id=<?php echo $post_id; ?>&pid=<?php echo $project_id; ?>&del_file=<?php echo $f['id']; ?>" 
                                               class="btn-remove-file" 
                                               onclick="return confirm('Tem certeza que deseja excluir este arquivo?');">
                                               &times;
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="padding: 15px; background: #f9fafb; border-radius: 6px; color: #6b7280; font-size: 0.9rem; text-align:center; margin-bottom: 10px;">
                                    Nenhum arquivo anexado.
                                </div>
                            <?php endif; ?>

                            <label class="upload-area" style="margin-top: 15px; display:block;">
                                <input type="file" name="midias[]" multiple onchange="document.getElementById('upload-text').innerText = this.files.length + ' arquivos selecionados'">
                                <span style="font-size: 1.5rem;">☁️</span><br>
                                <span id="upload-text" style="color: var(--accent-color); font-weight: 500;">Clique para adicionar novos arquivos</span>
                            </label>
                        </div>
                    </div>

                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color); text-align: right;">
                        <button type="submit" class="btn-save">
                            💾 Salvar Alterações
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </main>
</div>

</body>
</html>