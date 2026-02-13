<?php
/* Arquivo: /modules/projects/novo_projeto.php */
/* Função: Cadastro de Projeto + Seleção de Squad (Tema Claro) */

session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }

$tenant_id = $_SESSION['tenant_id'];
$erro = '';

// 1. Busca Clientes
try {
    $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE tenant_id = :t ORDER BY name ASC");
    $stmt->execute(['t' => $tenant_id]);
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) { die("Erro ao carregar clientes."); }

// 2. Busca Usuários (Para o Squad)
try {
    $stmtUsers = $pdo->prepare("SELECT id, name, profile_pic, custom_title FROM users WHERE tenant_id = :t ORDER BY name ASC");
    $stmtUsers->execute(['t' => $tenant_id]);
    $users = $stmtUsers->fetchAll();
} catch (PDOException $e) { die("Erro ao carregar equipe."); }

// 3. Processa o Formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $client_id = $_POST['client_id'];
    $deadline = $_POST['deadline'];
    $description = $_POST['description'];

    // Processamento do Squad
    $squad_final = [];
    if (isset($_POST['squad']) && is_array($_POST['squad'])) {
        foreach ($_POST['squad'] as $uid => $data) {
            // Se o checkbox estiver marcado
            if (isset($data['selected']) && $data['selected'] == 1) {
                $squad_final[] = [
                    'user_id' => $uid,
                    'role' => $data['role'] ?? 'Colaborador'
                ];
            }
        }
    }
    $squad_json = json_encode($squad_final);

    if (empty($title) || empty($client_id) || empty($deadline)) {
        $erro = "Preencha todos os campos obrigatórios.";
    } else {
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO projects (tenant_id, client_id, title, description, deadline, status, squad_data, created_at) 
                VALUES (:tenant_id, :client_id, :title, :description, :deadline, 'pending', :squad, NOW())
            ");
            $stmtInsert->execute([
                'tenant_id' => $tenant_id,
                'client_id' => $client_id,
                'title' => $title,
                'description' => $description,
                'deadline' => $deadline,
                'squad' => $squad_json
            ]);

            header("Location: projetos.php?msg=criado");
            exit;

        } catch (PDOException $e) {
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Novo Projeto - Agência OS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        /* CSS Específico para o Seletor de Squad no Tema Claro */
        .squad-container {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            background: #f8fafc;
            margin-top: 5px;
        }
        
        .squad-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 5px;
        }

        /* Scrollbar fina para a lista */
        .squad-list::-webkit-scrollbar { width: 6px; }
        .squad-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

        .squad-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .squad-item:hover {
            border-color: #94a3b8;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        /* Item Selecionado */
        .squad-item.selected {
            border-color: #3b82f6; /* Azul */
            background: #eff6ff;   /* Azul bem clarinho */
        }

        .user-check { width: 16px; height: 16px; cursor: pointer; accent-color: #3b82f6; }

        .user-avatar {
            width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: bold; color: #64748b; overflow: hidden;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .user-info { flex: 1; display: flex; flex-direction: column; }
        .user-name { font-size: 0.85rem; font-weight: 600; color: #334155; }
        
        .role-input {
            border: none;
            border-bottom: 1px solid #cbd5e1;
            background: transparent;
            font-size: 0.75rem;
            color: #64748b;
            width: 100%;
            padding: 2px 0;
            margin-top: 2px;
        }
        .role-input:focus { outline: none; border-bottom-color: #3b82f6; color: #0f172a; }
        .role-input::placeholder { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>

<div class="app-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div style="max-width: 700px; margin: 0 auto;">
            
            <div style="margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                <a href="projetos.php" style="color: var(--text-muted); text-decoration: none;">&larr; Voltar</a>
                <h1>Criar Novo Projeto</h1>
            </div>

            <div class="login-wrapper" style="max-width: 100%; text-align: left; padding: 30px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                
                <?php if ($erro): ?>
                    <div class="alert"><?php echo $erro; ?></div>
                <?php endif; ?>

                <?php if (count($clientes) == 0): ?>
                    <div class="alert" style="background: #fff7ed; color: #c2410c; border-color: #ffedd5;">
                        ⚠️ Você precisa cadastrar um Cliente antes de criar um projeto.
                        <br><br>
                        <a href="../clients/novo_cliente.php" style="color: inherit; font-weight: bold;">Ir para Cadastro de Clientes</a>
                    </div>
                <?php else: ?>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Título do Projeto *</label>
                            <input type="text" name="title" class="form-input" placeholder="Ex: Campanha Black Friday" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label class="form-label">Cliente *</label>
                                <select name="client_id" class="form-input" required style="background: white;">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($clientes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Prazo de Entrega *</label>
                                <input type="date" name="deadline" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Descrição / Briefing</label>
                            <textarea name="description" class="form-input" rows="3" placeholder="O que precisa ser feito?"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="display:flex; justify-content:space-between;">
                                <span>Montar Equipe (Squad)</span>
                                <span style="font-weight:normal; color:#64748b; font-size:0.8rem;">Selecione quem participa</span>
                            </label>
                            
                            <div class="squad-container">
                                <div class="squad-list">
                                    <?php foreach($users as $u): 
                                        $avatar = $u['profile_pic'] ? "<img src='../../uploads/avatars/{$u['profile_pic']}'>" : strtoupper(substr($u['name'], 0, 2));
                                        $defaultRole = $u['custom_title'] ?: 'Membro';
                                    ?>
                                        <label class="squad-item" id="card-<?php echo $u['id']; ?>">
                                            <input type="checkbox" name="squad[<?php echo $u['id']; ?>][selected]" value="1" class="user-check" onchange="toggleUser(<?php echo $u['id']; ?>)">
                                            
                                            <div class="user-avatar"><?php echo $avatar; ?></div>
                                            
                                            <div class="user-info">
                                                <span class="user-name"><?php echo htmlspecialchars($u['name']); ?></span>
                                                <input type="text" name="squad[<?php echo $u['id']; ?>][role]" class="role-input" value="<?php echo htmlspecialchars($defaultRole); ?>" placeholder="Função...">
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 2rem; display: flex; gap: 1rem; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                            <button type="submit" class="btn-primary">Criar Projeto</button>
                            <a href="projetos.php" class="btn-primary" style="background: #f1f5f9; color: #475569; text-align: center; text-decoration: none; border: 1px solid #e2e8f0;">Cancelar</a>
                        </div>
                    </form>

                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
    function toggleUser(id) {
        const card = document.getElementById('card-' + id);
        const checkbox = card.querySelector('.user-check');
        
        if (checkbox.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
</script>

</body>
</html>