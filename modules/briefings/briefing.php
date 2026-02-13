<?php
/* Arquivo: modules/briefings/briefing.php */
/* Versão: Upload de Capa Estilizado (Premium) */

session_start();
require '../../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

$tenant_id = $_SESSION['tenant_id'];

// --- AUTO-MIGRAÇÃO ---
try {
    $check = $pdo->query("SHOW COLUMNS FROM briefing_forms LIKE 'cover_image'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE briefing_forms ADD COLUMN cover_image VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {}

// --- SALVAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_briefing'])) {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $fields = $_POST['fields_data'] ?? '[]'; 
    $edit_id = $_POST['edit_id'] ?? ''; 

    // Upload
    $coverName = null;
    if (!empty($_FILES['cover_image']['name'])) {
        $uploadDir = '../../uploads/briefings/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        $coverName = uniqid('cover_') . '.' . $ext;
        move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadDir . $coverName);
    }

    if (!empty($edit_id)) {
        $sql = "UPDATE briefing_forms SET title = ?, description = ?, fields_json = ?";
        $params = [$title, $desc, $fields];

        if ($coverName) {
            $sql .= ", cover_image = ?";
            $params[] = $coverName;
        }

        $sql .= " WHERE id = ? AND tenant_id = ?";
        $params[] = $edit_id;
        $params[] = $tenant_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $token = bin2hex(random_bytes(16)); 
        $stmt = $pdo->prepare("INSERT INTO briefing_forms (tenant_id, title, description, fields_json, public_token, cover_image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $title, $desc, $fields, $token, $coverName]);
    }
    header("Location: briefing.php"); exit;
}

// --- EXCLUIR ---
if (isset($_GET['del'])) {
    $pdo->prepare("DELETE FROM briefing_forms WHERE id=? AND tenant_id=?")->execute([$_GET['del'], $tenant_id]);
    header("Location: briefing.php"); exit;
}

// --- LISTAR ---
$stmt = $pdo->prepare("SELECT * FROM briefing_forms WHERE tenant_id = ? ORDER BY id DESC");
$stmt->execute([$tenant_id]);
$briefings = $stmt->fetchAll();

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?"https":"http") . "://" . $_SERVER['HTTP_HOST'] . str_replace("/modules/briefings", "", dirname($_SERVER['PHP_SELF']));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Briefings</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <style>
        .modal-overlay { background-color: rgba(0, 0, 0, 0.7) !important; backdrop-filter: blur(3px); }
        .modal-content { background-color: #ffffff !important; color: #333333 !important; box-shadow: 0 20px 50px rgba(0,0,0,0.3); border-radius: 12px; border: 1px solid #e2e8f0; }
        
        .header-input { width: 100%; padding: 12px 15px; margin-bottom: 12px; background-color: #ffffff !important; border: 1px solid #cbd5e1 !important; border-radius: 8px; color: #1e293b !important; font-size: 1rem; transition: all 0.2s; }
        .header-input:focus { border-color: #000000 !important; box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1); outline: none; }

        /* === ESTILO DO BOTÃO DE UPLOAD === */
        .upload-area {
            position: relative;
            margin-bottom: 20px;
        }
        
        /* Oculta o input real */
        input[type="file"]#coverInput {
            display: none;
        }

        /* O botão bonito */
        .custom-file-upload {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 20px;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            background-color: #f8fafc;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .custom-file-upload:hover {
            background-color: #f1f5f9;
            border-color: #000;
            color: #333;
        }

        .cover-preview { 
            width: 100%; 
            height: 180px; 
            object-fit: cover; 
            border-radius: 8px; 
            margin-bottom: 15px; 
            border: 1px solid #e2e8f0; 
            display: none; /* Escondido por padrão */
        }

        /* --- RESTO DOS ESTILOS --- */
        .builder-container { background-color: #f8fafc !important; padding: 25px; border-radius: 8px; border: 2px dashed #cbd5e1; min-height: 250px; margin-top: 20px; }
        .builder-item { background-color: #ffffff !important; color: #333333 !important; border: 1px solid #e2e8f0; border-left: 5px solid #000000; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .item-header { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; align-items: center; }
        .b-input { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 6px; background-color: #ffffff !important; color: #333 !important; min-width: 200px; }
        .type-select { padding: 10px; border-radius: 6px; border: 1px solid #ccc; background-color: #ffffff !important; color: #333 !important; cursor: pointer; }
        .tools-bar { display: flex; gap: 12px; overflow-x: auto; padding: 15px 20px; background-color: #ffffff !important; border-bottom: 1px solid #e2e8f0; margin: -20px -20px 20px -20px; }
        .tool-btn { display: flex; align-items: center; gap: 6px; padding: 8px 16px; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 20px; cursor: pointer; white-space: nowrap; font-size: 0.85rem; font-weight: 600; transition: all 0.2s; }
        .tool-btn:hover { background: #000000; color: #fff; border-color: #000000; transform: translateY(-1px); }
        .handle { cursor: move; color: #94a3b8; font-size: 1.5rem; padding: 5px; margin-right: 10px; }
        .options-list { margin-top: 10px; padding-left: 10px; border-left: 2px solid #e2e8f0; }
        .option-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid #e2e8f0; background: #ffffff; text-align: right; display: flex; justify-content: flex-end; gap: 12px; align-items: center; }
        .btn-modal-cancel { background-color: #ffffff; color: #64748b; border: 1px solid #cbd5e1; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; font-size: 0.95rem; }
        .btn-modal-cancel:hover { background-color: #f1f5f9; color: #334155; border-color: #94a3b8; }
        .btn-modal-save { background: #000000; color: white; border: 1px solid #1f2937; padding: 12px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; font-size: 0.95rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2); display: flex; align-items: center; gap: 8px; }
        .btn-modal-save:hover { background: #1f1f1f; transform: translateY(-2px); box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.3); }
        .icon-btn { cursor:pointer; font-size: 1.2rem; text-decoration: none; margin-left: 10px; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>
    <main class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>📝 Briefings & Formulários</h1>
            <button onclick="openModal('new')" class="btn-primary">+ Novo Briefing</button>
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
            <?php foreach($briefings as $b): 
                $link = $base_url . "/public/form_briefing.php?t=" . $b['public_token'];
                $fields = json_decode($b['fields_json'], true);
                $countQ = is_array($fields) ? count($fields) : 0;
                $jsonData = htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="card">
                <?php if(!empty($b['cover_image'])): ?>
                    <div style="height: 120px; margin: -20px -20px 15px -20px; border-radius: 8px 8px 0 0; overflow: hidden; position: relative;">
                        <img src="../../uploads/briefings/<?php echo $b['cover_image']; ?>" style="width:100%; height:100%; object-fit:cover;">
                        <div style="position:absolute; inset:0; background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.5));"></div>
                    </div>
                <?php endif; ?>

                <div style="display:flex; justify-content:space-between; align-items: flex-start;">
                    <h3 style="margin:0; font-size: 1.1rem; line-height:1.4;"><?php echo htmlspecialchars($b['title']); ?></h3>
                    <div style="display:flex;">
                        <button onclick='openModal("edit", <?php echo $jsonData; ?>)' class="icon-btn" title="Editar" style="border:none; background:none;">✏️</button>
                        <a href="?del=<?php echo $b['id']; ?>" onclick="return confirm('Excluir este formulário?')" class="icon-btn" style="color:#ef4444;" title="Excluir">🗑️</a>
                    </div>
                </div>
                <p style="color:var(--text-muted-alt); font-size:0.85rem; margin: 5px 0 15px 0;"><?php echo $countQ; ?> perguntas.</p>
                
                <div style="background:var(--bg-body-alt); padding:8px; border-radius:6px; display:flex; gap:5px; align-items:center;">
                    <input type="text" value="<?php echo $link; ?>" class="form-input" readonly id="link_<?php echo $b['id']; ?>" style="margin:0; font-size:0.75rem; flex:1;">
                    <button class="btn-primary" onclick="copiar('link_<?php echo $b['id']; ?>')" style="padding: 8px;">📋</button>
                    <a href="<?php echo $link; ?>" target="_blank" class="btn-secondary" style="padding: 8px;">🔗</a>
                </div>
                <div style="margin-top: 10px; text-align:right;">
                   <a href="respostas.php?id=<?php echo $b['id']; ?>" style="font-size: 0.85rem; color: var(--accent-color); font-weight: bold;">Ver Respostas &rarr;</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<div id="modalNew" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; justify-content:center; align-items:center;">
    <div class="modal-content" style="width:95%; max-width:900px; height:90vh; display:flex; flex-direction:column; border-radius:12px; overflow:hidden;">
        
        <div style="padding: 15px 25px; border-bottom: 1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background: #ffffff;">
            <h2 id="modalTitle" style="margin:0; font-size: 1.3rem; color: #1e293b; font-weight: 700;">Criar Novo Formulário</h2>
            <button onclick="document.getElementById('modalNew').style.display='none'" style="background:none; border:none; font-size:2rem; cursor:pointer; color: #94a3b8; line-height: 1; transition:0.2s;">&times;</button>
        </div>

        <div style="flex:1; overflow-y:auto; padding:0; background: #ffffff;">
            <form method="POST" id="formBuilderForm" enctype="multipart/form-data" style="padding: 25px;">
                <input type="hidden" name="save_briefing" value="1">
                <input type="hidden" name="fields_data" id="fieldsData">
                <input type="hidden" name="edit_id" id="editId"> 

                <div style="margin-bottom: 25px;">
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155;">Capa Personalizada</label>
                    
                    <div class="upload-area">
                        <img id="coverPreview" class="cover-preview" src="">
                        
                        <label for="coverInput" class="custom-file-upload">
                            <span style="font-size: 1.5rem; margin-right: 5px;">📷</span> 
                            <span id="uploadText">Clique para enviar uma capa</span>
                        </label>
                        <input type="file" id="coverInput" name="cover_image" accept="image/*" onchange="previewCover(this)">
                    </div>

                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155; margin-top:20px;">Título do Formulário</label>
                    <input type="text" name="title" id="inputTitle" class="header-input" required placeholder="Ex: Briefing de Marketing" style="font-size: 1.1rem; font-weight: 600;">
                    
                    <label style="display:block; margin-bottom:6px; font-weight:600; color:#334155; margin-top:15px;">Descrição / Instruções</label>
                    <input type="text" name="description" id="inputDesc" class="header-input" placeholder="Ex: Preencha com os dados da sua empresa...">
                </div>

                <div class="tools-bar">
                    <div class="tool-btn" onclick="addField('text')">🔤 Texto Curto</div>
                    <div class="tool-btn" onclick="addField('textarea')">¶ Parágrafo</div>
                    <div class="tool-btn" onclick="addField('radio')">🔘 Múltipla Escolha</div>
                    <div class="tool-btn" onclick="addField('checkbox')">☑️ Seleção</div>
                    <div class="tool-btn" onclick="addField('select')">▼ Lista</div>
                    <div class="tool-btn" onclick="addField('date')">📅 Data</div>
                </div>

                <div id="builderCanvas" class="builder-container">
                    <div style="text-align:center; color:#64748b; padding:40px;">
                        Clique nas opções acima para adicionar perguntas.
                    </div>
                </div>
            </form>
        </div>

        <div class="modal-footer">
            <button type="button" onclick="document.getElementById('modalNew').style.display='none'" class="btn-modal-cancel">Cancelar</button>
            <button type="button" class="btn-modal-save" onclick="submitBuilder()">
                <span>💾</span> Salvar Formulário
            </button>
        </div>
    </div>
</div>

<script>
    let formItems = []; 

    function openModal(mode, data = null) {
        formItems = [];
        const titleEl = document.getElementById('modalTitle');
        const inputTitle = document.getElementById('inputTitle');
        const inputDesc = document.getElementById('inputDesc');
        const editId = document.getElementById('editId');
        const imgPrev = document.getElementById('coverPreview');
        const uploadText = document.getElementById('uploadText');

        if (mode === 'edit' && data) {
            titleEl.innerText = 'Editar Formulário';
            inputTitle.value = data.title;
            inputDesc.value = data.description || '';
            editId.value = data.id;
            
            if (data.cover_image) {
                imgPrev.src = '../../uploads/briefings/' + data.cover_image;
                imgPrev.style.display = 'block';
                uploadText.innerText = 'Alterar Capa';
            } else {
                imgPrev.style.display = 'none';
                uploadText.innerText = 'Clique para enviar uma capa';
            }
            
            try {
                formItems = JSON.parse(data.fields_json);
            } catch(e) { formItems = []; }

        } else {
            titleEl.innerText = 'Criar Novo Formulário';
            inputTitle.value = '';
            inputDesc.value = '';
            editId.value = '';
            imgPrev.style.display = 'none';
            imgPrev.src = '';
            uploadText.innerText = 'Clique para enviar uma capa';
            formItems = [];
        }

        document.getElementById('modalNew').style.display = 'flex';
        renderBuilder();
    }

    function previewCover(input) {
        const preview = document.getElementById('coverPreview');
        const uploadText = document.getElementById('uploadText');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                uploadText.innerText = 'Capa Selecionada (Alterar)';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function addField(type) {
        const id = Date.now().toString(); 
        formItems.push({
            id: id,
            type: type,
            label: 'Nova Pergunta',
            required: false,
            options: (type === 'radio' || type === 'checkbox' || type === 'select') ? ['Opção 1'] : []
        });
        renderBuilder();
    }

    function deleteField(index) {
        if(confirm('Remover esta pergunta?')) {
            formItems.splice(index, 1);
            renderBuilder();
        }
    }

    function updateField(index, key, value) {
        formItems[index][key] = value;
    }

    function addOption(index) {
        formItems[index].options.push(`Opção ${formItems[index].options.length + 1}`);
        renderBuilder();
    }

    function removeOption(fieldIndex, optionIndex) {
        formItems[fieldIndex].options.splice(optionIndex, 1);
        renderBuilder();
    }

    function updateOption(fieldIndex, optionIndex, value) {
        formItems[fieldIndex].options[optionIndex] = value;
    }

    function renderBuilder() {
        const container = document.getElementById('builderCanvas');
        container.innerHTML = '';

        if (formItems.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:var(--text-muted-alt); padding:40px;">Este formulário está vazio.<br>Adicione perguntas usando a barra acima.</div>';
            return;
        }

        formItems.forEach((item, index) => {
            let optionsHTML = '';
            
            if (['radio', 'checkbox', 'select'].includes(item.type)) {
                optionsHTML = `<div class="options-list">`;
                item.options.forEach((opt, optIndex) => {
                    let icon = item.type === 'radio' ? 'radio-circle' : (item.type === 'select' ? '' : 'check-square');
                    let visualIcon = icon ? `<div class="${icon}"></div>` : `<span style="font-size:0.8rem; color:#333;">▼</span>`;
                    
                    optionsHTML += `
                        <div class="option-row">
                            ${visualIcon}
                            <input type="text" class="b-input" style="padding:6px; font-size:0.9rem;" value="${opt}" oninput="updateOption(${index}, ${optIndex}, this.value)">
                            <button type="button" onclick="removeOption(${index}, ${optIndex})" style="color:#94a3b8; background:none; border:none; cursor:pointer; font-weight:bold;">✕</button>
                        </div>
                    `;
                });
                optionsHTML += `
                    <div class="option-row" style="margin-top:10px;">
                        <span style="color:#4338ca; cursor:pointer; font-size:0.9rem; font-weight:600;" onclick="addOption(${index})">+ Adicionar opção</span>
                    </div>
                </div>`;
            }

            const cardHTML = `
                <div class="builder-item" data-id="${item.id}">
                    <div class="item-header">
                        <div class="handle">⋮⋮</div>
                        <input type="text" class="b-input" value="${item.label}" oninput="updateField(${index}, 'label', this.value)" placeholder="Digite a pergunta">
                        <select class="type-select" onchange="changeType(${index}, this.value)">
                            <option value="text" ${item.type === 'text' ? 'selected' : ''}>Texto Curto</option>
                            <option value="textarea" ${item.type === 'textarea' ? 'selected' : ''}>Parágrafo</option>
                            <option value="radio" ${item.type === 'radio' ? 'selected' : ''}>Múltipla Escolha</option>
                            <option value="checkbox" ${item.type === 'checkbox' ? 'selected' : ''}>Caixas de Seleção</option>
                            <option value="select" ${item.type === 'select' ? 'selected' : ''}>Lista Suspensa</option>
                            <option value="date" ${item.type === 'date' ? 'selected' : ''}>Data</option>
                        </select>
                    </div>
                    
                    ${optionsHTML}

                    <div style="display:flex; justify-content:flex-end; gap:15px; margin-top:10px; border-top:1px solid #e2e8f0; padding-top:10px;">
                        <label style="font-size:0.9rem; display:flex; align-items:center; gap:5px; cursor:pointer; color:#333;">
                            <input type="checkbox" ${item.required ? 'checked' : ''} onchange="updateField(${index}, 'required', this.checked)">
                            Obrigatória
                        </label>
                        <button type="button" onclick="deleteField(${index})" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:1.2rem;">🗑️</button>
                    </div>
                </div>
            `;
            container.innerHTML += cardHTML;
        });

        new Sortable(container, {
            handle: '.handle',
            animation: 150,
            onEnd: function (evt) {
                const newOrder = [];
                const items = container.querySelectorAll('.builder-item');
                items.forEach(el => {
                    const id = el.getAttribute('data-id');
                    const originalItem = formItems.find(i => i.id === id);
                    newOrder.push(originalItem);
                });
                formItems = newOrder;
            }
        });
    }

    function changeType(index, newType) {
        formItems[index].type = newType;
        if(['radio', 'checkbox', 'select'].includes(newType) && (!formItems[index].options || formItems[index].options.length === 0)) {
            formItems[index].options = ['Opção 1'];
        }
        renderBuilder();
    }

    function submitBuilder() {
        const title = document.querySelector('input[name="title"]').value;
        if(!title) {
            alert("Por favor, dê um título ao formulário.");
            return;
        }
        if(formItems.length === 0) {
            alert("O formulário precisa ter pelo menos uma pergunta.");
            return;
        }

        document.getElementById('fieldsData').value = JSON.stringify(formItems);
        document.getElementById('formBuilderForm').submit();
    }

    function copiar(id) {
        var copyText = document.getElementById(id);
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(copyText.value).then(() => alert("Link copiado!"));
    }
</script>
</body>
</html>