<?php
/* Arquivo: modules/chat/api.php */
/* Versão: Completa + Função Limpar Conversa */

session_start();
require '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$action    = $_REQUEST['action'] ?? '';

// --- 1. LISTAR USUÁRIOS (COM CONTAGEM DE NÃO LIDAS) ---
if ($action === 'list_users') {
    // Busca todos os usuários do mesmo Tenant, exceto eu mesmo
    $stmt = $pdo->prepare("
        SELECT id, name, email, avatar 
        FROM users 
        WHERE tenant_id = ? AND id != ? 
        ORDER BY name ASC
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona contagem de mensagens não lidas para cada usuário
    foreach ($users as &$u) {
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM chat_messages 
            WHERE tenant_id = ? 
            AND sender_id = ? 
            AND receiver_id = ? 
            AND is_read = 0
        ");
        $stmtCount->execute([$tenant_id, $u['id'], $user_id]);
        $u['unread'] = $stmtCount->fetchColumn();
    }
    
    echo json_encode($users);
    exit;
}

// --- 2. OBTER MENSAGENS (CARREGAR CONVERSA) ---
if ($action === 'get_messages') {
    $other_id = $_GET['user_id'] ?? 0;

    if ($other_id) {
        // Marca mensagens deste usuário como lidas
        $upd = $pdo->prepare("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE tenant_id = ? 
            AND sender_id = ? 
            AND receiver_id = ?
        ");
        $upd->execute([$tenant_id, $other_id, $user_id]);

        // Busca histórico da conversa (Enviei OU Recebi)
        $stmt = $pdo->prepare("
            SELECT * FROM chat_messages 
            WHERE tenant_id = ? 
            AND (
                (sender_id = ? AND receiver_id = ?) 
                OR 
                (sender_id = ? AND receiver_id = ?)
            )
            ORDER BY created_at ASC
        ");
        $stmt->execute([$tenant_id, $user_id, $other_id, $other_id, $user_id]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($msgs);
    } else {
        echo json_encode([]);
    }
    exit;
}

// --- 3. ENVIAR MENSAGEM ---
if ($action === 'send') {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');

    if ($receiver_id && !empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (tenant_id, sender_id, receiver_id, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        if ($stmt->execute([$tenant_id, $user_id, $receiver_id, $message])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }
    exit;
}

// --- 4. CHECKAR TOTAL DE NÃO LIDAS (BADGE GLOBAL) ---
if ($action === 'check_total_unread') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM chat_messages 
        WHERE tenant_id = ? 
        AND receiver_id = ? 
        AND is_read = 0
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $total = $stmt->fetchColumn();
    
    echo json_encode(['total' => $total]);
    exit;
}

// --- 5. [NOVO] LIMPAR CONVERSA ---
if ($action === 'clear_chat') {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    
    if ($receiver_id) {
        // Deleta mensagens trocadas entre EU e o OUTRO (nos dois sentidos)
        // Garante que só apaga do tenant atual para segurança
        $stmt = $pdo->prepare("
            DELETE FROM chat_messages 
            WHERE tenant_id = ? 
            AND (
                (sender_id = ? AND receiver_id = ?) 
                OR 
                (sender_id = ? AND receiver_id = ?)
            )
        ");
        // Parâmetros: Tenant, (Eu -> Ele) OU (Ele -> Eu)
        $stmt->execute([$tenant_id, $user_id, $receiver_id, $receiver_id, $user_id]);
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    }
    exit;
}
?>