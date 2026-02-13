<?php
/* Arquivo: modules/chat/chat.php */
/* Versão: Com Som + Emojis + Notificações */

session_start();
require '../../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Chat Equipe - Bliss OS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/@joeattardi/emoji-button@4.6.4/dist/index.min.js"></script>

    <style>
        /* LAYOUT CHAT */
        .chat-container { display: grid; grid-template-columns: 300px 1fr; height: calc(100vh - 40px); background: var(--bg-card); border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); margin: 0; }
        
        /* SIDEBAR */
        .chat-sidebar { background: var(--bg-body-alt); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .chat-header-side { padding: 15px; border-bottom: 1px solid var(--border-color); font-weight: bold; color: var(--text-main); }
        .user-list { flex: 1; overflow-y: auto; }
        
        .user-item { display: flex; align-items: center; padding: 12px 15px; cursor: pointer; transition: 0.2s; border-bottom: 1px solid transparent; }
        .user-item:hover { background: rgba(0,0,0,0.05); }
        .user-item.active { background: #e0e7ff; border-left: 4px solid #4338ca; }
        [data-theme="dark"] .user-item.active { background: #1e293b; border-left-color: #3b82f6; }

        .u-avatar { width: 40px; height: 40px; border-radius: 50%; background: #ccc; margin-right: 10px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #fff; overflow: hidden; }
        .u-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .u-info { flex: 1; overflow: hidden; }
        .u-name { font-size: 0.95rem; font-weight: 500; display: block; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .u-badge { background: #ef4444; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; font-weight: bold; }

        /* MAIN */
        .chat-main { display: flex; flex-direction: column; background: var(--bg-card); position: relative; }
        .chat-header-main { padding: 10px 20px; border-bottom: 1px solid var(--border-color); background: var(--bg-body-alt); display: flex; align-items: center; height: 60px; color: var(--text-main); }
        
        .messages-area { flex: 1; padding: 20px; overflow-y: auto; background: var(--bg-body); display: flex; flex-direction: column; gap: 10px; }
        
        /* MENSAGENS */
        .msg { max-width: 70%; padding: 10px 15px; border-radius: 8px; font-size: 0.95rem; line-height: 1.4; position: relative; word-wrap: break-word; }
        
        .msg-me { align-self: flex-end; background: #000; color: #fff; border-radius: 8px 0 8px 8px; }
        .msg-other { align-self: flex-start; background: #e2e8f0; color: #333; border-radius: 0 8px 8px 8px; }
        
        [data-theme="dark"] .msg-other { background: #334155; color: #fff; }
        [data-theme="dark"] .msg-me { background: #4f46e5; } 
        
        .msg-time { font-size: 0.7rem; opacity: 0.7; display: block; text-align: right; margin-top: 5px; }

        /* INPUT AREA */
        .input-area { padding: 15px; background: var(--bg-body-alt); border-top: 1px solid var(--border-color); display: flex; gap: 10px; align-items: center; }
        .chat-input { flex: 1; padding: 12px; border-radius: 20px; border: 1px solid var(--border-color); outline: none; background: var(--bg-card); color: var(--text-main); font-size: 1rem; }
        
        .btn-icon { background: none; border: none; font-size: 1.5rem; cursor: pointer; padding: 5px; transition: 0.2s; color: var(--text-muted-alt); }
        .btn-icon:hover { transform: scale(1.1); color: var(--accent-color); }

        .btn-send { background: #000; color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-send:hover { transform: scale(1.05); }

        /* RESPONSIVO */
        @media (max-width: 768px) {
            .chat-container { grid-template-columns: 1fr; }
            .chat-sidebar { display: flex; }
            .chat-main { display: none; }
            .chat-container.chat-active .chat-sidebar { display: none; }
            .chat-container.chat-active .chat-main { display: flex; }
        }
    </style>
</head>
<body style="overflow: hidden;"> 

<audio id="chatSound" src="https://cdn.pixabay.com/audio/2022/10/30/audio_5b323c2a68.mp3" preload="auto"></audio>

<div class="app-container" style="padding: 0; display:flex;">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content" style="padding: 10px; height: 100vh; overflow: hidden;">
        
        <div id="chatApp" class="chat-container">
            
            <div class="chat-sidebar">
                <div class="chat-header-side">💬 Equipe</div>
                <div id="usersList" class="user-list">
                    <div style="padding:20px; text-align:center; color:var(--text-muted-alt);">Carregando...</div>
                </div>
            </div>

            <div class="chat-main">
                <div id="chatHeader" class="chat-header-main" style="display:none;">
                    <button onclick="closeChatMobile()" style="margin-right:10px; background:none; border:none; font-size:1.2rem; cursor:pointer; color:var(--text-main);" class="mobile-only">←</button>
                    <div id="chatUserName" style="font-weight:bold;">Nome do Usuário</div>
                </div>

                <div id="emptyState" style="flex:1; display:flex; align-items:center; justify-content:center; color:var(--text-muted-alt); flex-direction:column;">
                    <div style="font-size:3rem; margin-bottom:10px;">👋</div>
                    Selecione alguém para conversar
                </div>

                <div id="messagesList" class="messages-area" style="display:none;"></div>

                <div id="inputFooter" class="input-area" style="display:none;">
                    <button id="emojiBtn" class="btn-icon">😀</button>
                    
                    <input type="text" id="msgInput" class="chat-input" placeholder="Digite uma mensagem..." onkeypress="handleEnter(event)">
                    <button class="btn-send" onclick="sendMessage()">➤</button>
                </div>
            </div>

        </div>

    </main>
</div>

<script>
    const MY_ID = <?php echo $_SESSION['user_id']; ?>;
    let currentReceiverId = null;
    let pollingInterval = null;
    
    // Variáveis para controle de notificação sonora
    let lastMsgCountInChat = 0; 
    let lastTotalUnread = 0;

    // --- 0. CONFIGURAÇÃO DE EMOJIS ---
    const picker = new EmojiButton({
        position: 'top-start',
        theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
    });
    const trigger = document.querySelector('#emojiBtn');
    const inputField = document.querySelector('#msgInput');

    picker.on('emoji', selection => {
        inputField.value += selection.emoji;
        inputField.focus();
    });

    trigger.addEventListener('click', () => picker.togglePicker(trigger));


    // --- 1. CARREGAR LISTA DE USUÁRIOS (COM SOM DE NOTIFICAÇÃO EXTERNA) ---
    function loadUsers() {
        fetch('api.php?action=list_users')
            .then(res => res.json())
            .then(users => {
                const list = document.getElementById('usersList');
                let html = '';
                let currentTotalUnread = 0;
                
                if(users.length === 0) {
                     list.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted-alt);">Nenhum outro membro na equipe.</div>';
                     return;
                }

                users.forEach(u => {
                    const activeClass = (u.id == currentReceiverId) ? 'active' : '';
                    const unreadQty = parseInt(u.unread);
                    currentTotalUnread += unreadQty;

                    const badge = (unreadQty > 0) ? `<span class="u-badge">${unreadQty}</span>` : '';
                    
                    let avatarHtml = u.avatar 
                        ? `<img src="../../uploads/avatars/${u.avatar}">` 
                        : u.name.charAt(0).toUpperCase();

                    html += `
                        <div class="user-item ${activeClass}" onclick="openChat(${u.id}, '${u.name}')">
                            <div class="u-avatar">${avatarHtml}</div>
                            <div class="u-info">
                                <span class="u-name">${u.name}</span>
                            </div>
                            ${badge}
                        </div>
                    `;
                });
                list.innerHTML = html;

                // Toca som se houver novas mensagens não lidas e eu NÃO estiver no chat dessa pessoa
                if (currentTotalUnread > lastTotalUnread) {
                    playSound();
                }
                lastTotalUnread = currentTotalUnread;
            });
    }

    // --- 2. ABRIR CONVERSA ---
    function openChat(userId, userName) {
        currentReceiverId = userId;
        document.getElementById('chatUserName').innerText = userName;
        
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('chatHeader').style.display = 'flex';
        document.getElementById('messagesList').style.display = 'flex';
        document.getElementById('inputFooter').style.display = 'flex';
        document.getElementById('chatApp').classList.add('chat-active'); 

        lastMsgCountInChat = 0; // Reseta contador local
        loadUsers(); 
        loadMessages();
        
        if(pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(loadMessages, 3000);
        
        setTimeout(() => document.getElementById('msgInput').focus(), 100);
    }

    // --- 3. CARREGAR MENSAGENS (COM SOM INTERNO) ---
    function loadMessages() {
        if(!currentReceiverId) return;

        fetch(`api.php?action=get_messages&user_id=${currentReceiverId}`)
            .then(res => res.json())
            .then(msgs => {
                const container = document.getElementById('messagesList');
                // Lógica simples para scroll: se estava perto do fim, desce.
                const isScrolledToBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 150;
                
                let html = '';
                msgs.forEach(m => {
                    const type = (m.sender_id == MY_ID) ? 'msg-me' : 'msg-other';
                    const time = new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    html += `
                        <div class="msg ${type}">
                            ${m.message}
                            <span class="msg-time">${time}</span>
                        </div>
                    `;
                });
                
                // Só atualiza se mudou algo
                if (container.innerHTML !== html) {
                    container.innerHTML = html;
                    if(isScrolledToBottom || msgs.length === 0) { 
                        container.scrollTop = container.scrollHeight;
                    }

                    // Toca som se chegou mensagem nova de OUTRA pessoa enquanto estou no chat
                    if (msgs.length > lastMsgCountInChat && lastMsgCountInChat !== 0) {
                        const lastMsg = msgs[msgs.length - 1];
                        if (lastMsg.sender_id != MY_ID) {
                            playSound();
                        }
                    }
                    lastMsgCountInChat = msgs.length;
                }
            });
    }

    // --- 4. ENVIAR MENSAGEM ---
    function sendMessage() {
        const input = document.getElementById('msgInput');
        const text = input.value.trim();
        if(!text || !currentReceiverId) return;

        const formData = new FormData();
        formData.append('receiver_id', currentReceiverId);
        formData.append('message', text);

        // Optimistic UI
        const container = document.getElementById('messagesList');
        const now = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        container.innerHTML += `
            <div class="msg msg-me" style="opacity:0.7">
                ${text}
                <span class="msg-time">${now} (enviando...)</span>
            </div>
        `;
        container.scrollTop = container.scrollHeight;
        input.value = '';

        fetch('api.php?action=send', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                loadMessages(); 
            });
    }

    function handleEnter(e) {
        if(e.key === 'Enter') sendMessage();
    }

    function closeChatMobile() {
        document.getElementById('chatApp').classList.remove('chat-active');
        currentReceiverId = null;
        if(pollingInterval) clearInterval(pollingInterval);
    }

    function playSound() {
        try {
            const audio = document.getElementById('chatSound');
            audio.volume = 0.5;
            audio.currentTime = 0;
            audio.play().catch(e => console.log("Interação necessária para tocar som"));
        } catch(e){}
    }

    setInterval(loadUsers, 5000); 
    loadUsers();

</script>
</body>
</html>