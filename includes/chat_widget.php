<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>

<audio id="chatSound" preload="auto">
    <source src="https://cdn.pixabay.com/download/audio/2022/03/24/audio_ff132d79ba.mp3?filename=pop-alert-notify.mp3" type="audio/mpeg">
</audio>

<style>
    /* --- WRAPPER --- */
    .chat-widget-wrapper {
        position: fixed; bottom: 25px; right: 25px; width: 60px; height: 60px; z-index: 9990;
    }

    /* BOTÃO REDONDO */
    .chat-fab {
        width: 100%; height: 100%; background: #0f172a; color: #fff; border-radius: 50%;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;
        font-size: 28px; cursor: pointer; transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .chat-fab:hover { transform: scale(1.1); }
    
    /* BADGE */
    .chat-badge-total {
        position: absolute; top: -2px; right: -2px; background: #ef4444; color: white;
        font-size: 11px; font-weight: 800; min-width: 22px; height: 22px; padding: 0 4px;
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.3); z-index: 9995;
        pointer-events: none; animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    @keyframes popIn { from { transform: scale(0); } to { transform: scale(1); } }
    
    .chat-widget-wrapper.has-new .chat-fab { background: #ef4444; animation: pulse-shadow 2s infinite; }
    @keyframes pulse-shadow {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    /* --- JANELA DO CHAT --- */
    .chat-window {
        position: fixed; bottom: 100px; right: 25px; width: 380px; height: 600px; max-height: 80vh;
        background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.25);
        display: none; flex-direction: column; z-index: 9999; overflow: hidden;
        border: 1px solid #e2e8f0; font-family: 'Inter', sans-serif;
    }
    
    /* EMOJIS */
    emoji-picker { width: 100%; height: 320px; --background: #fff; --border-color: #e2e8f0; }
    .emoji-popover { position: absolute; bottom: 70px; left: 0; width: 100%; display: none; z-index: 10001; box-shadow: 0 -5px 20px rgba(0,0,0,0.1); }
    .emoji-popover.active { display: block; animation: slideUp 0.2s ease; }

    /* ESTILOS GERAIS */
    .cw-header { background: #0f172a; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; font-weight: 600; }
    .cw-body { flex: 1; overflow-y: auto; background: #f1f5f9; position: relative; }
    .cw-conversation { display: none; flex-direction: column; height: 100%; background: #efeae2; }
    
    .cw-user-item { display: flex; align-items: center; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; cursor: pointer; background: #fff; transition:0.2s; }
    .cw-user-item:hover { background: #f8fafc; }
    .cw-avatar { width: 40px; height: 40px; border-radius: 50%; background: #cbd5e1; margin-right: 12px; display: flex; align-items: center; justify-content: center; color:#fff; font-weight:bold; overflow:hidden; }
    .cw-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .cw-info { flex:1; overflow:hidden; }
    .cw-name { font-size:0.95rem; font-weight:600; color:#334155; }
    .cw-unread { background:#ef4444; color:#fff; font-size:0.7rem; padding:2px 8px; border-radius:10px; margin-left:10px; }

    .cw-msgs-area { flex:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:8px; }
    .cw-msg { max-width:80%; padding:8px 12px; border-radius:8px; font-size:0.9rem; line-height:1.4; box-shadow:0 1px 1px rgba(0,0,0,0.1); }
    .cw-msg-me { align-self:flex-end; background:#0f172a; color:#fff; border-radius:8px 0 8px 8px; }
    .cw-msg-other { align-self:flex-start; background:#fff; color:#111; border-radius:0 8px 8px 8px; }

    .cw-input-area { padding:10px; background:#f0f2f5; display:flex; gap:8px; align-items:center; }
    .cw-input-wrapper { flex:1; background:#fff; border-radius:24px; padding:8px 15px; display:flex; align-items:center; }
    .cw-input { flex:1; border:none; outline:none; font-size:0.95rem; }
    .cw-btn-send { background:#0f172a; color:#fff; border:none; width:40px; height:40px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .cw-emoji-btn { background:none; border:none; font-size:1.4rem; cursor:pointer; opacity:0.6; padding:0; margin-right:5px; }

    /* BOTÃO LIMPAR */
    #chatClearBtn { background:none; border:none; color:rgba(255,255,255,0.7); cursor:pointer; font-size:1.1rem; margin-right:10px; display:none; transition:0.2s; }
    #chatClearBtn:hover { color:#ef4444; }

    [data-theme="dark"] .chat-window { background: #1e293b; border-color: #334155; }
    [data-theme="dark"] .cw-header { background: #0f172a; }
    [data-theme="dark"] .cw-user-item, [data-theme="dark"] .cw-input-area { background: #1e293b; border-color: #334155; }
    [data-theme="dark"] .cw-name, [data-theme="dark"] .cw-input { color: #fff; }
    [data-theme="dark"] .cw-conversation { background: #0b1120; }
    [data-theme="dark"] .cw-input-wrapper { background: #0f172a; }
    [data-theme="dark"] .cw-msg-other { background: #334155; color: #fff; }
    [data-theme="dark"] emoji-picker { --background: #1e293b; --border-color: #334155; --indicator-color: #fff; }

    @media (max-width: 500px) { .chat-window { width: 94%; height: 80vh; bottom: 90px; right: 3%; } }
</style>

<div id="chatWidgetWrapper" class="chat-widget-wrapper" onclick="toggleChatWindow()">
    <div class="chat-fab" title="Chat da Equipe">💬</div>
    <div id="chatTotalBadge" class="chat-badge-total" style="display:none;">0</div>
</div>

<div id="chatWindow" class="chat-window">
    <div class="cw-header">
        <div style="display: flex; align-items: center;">
            <button id="chatBackBtn" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer; margin-right:15px; display:none;" onclick="goBackToList(event)">←</button>
            <span id="chatTitle">Equipe</span>
        </div>
        <div style="display: flex; align-items: center;">
            <button id="chatClearBtn" onclick="clearCwChat(event)" title="Limpar conversa">🗑️</button>
            <button onclick="toggleChatWindow()" style="background:none; border:none; color:rgba(255,255,255,0.8); cursor:pointer; font-size:1.5rem;">&times;</button>
        </div>
    </div>

    <div class="cw-body">
        <div id="cwUserList">
            <div style="padding:30px; text-align:center; color:#94a3b8;">Carregando...</div>
        </div>

        <div id="cwConversation" class="cw-conversation">
            <div id="cwMsgs" class="cw-msgs-area"></div>
            
            <div id="emojiPopover" class="emoji-popover">
                <emoji-picker></emoji-picker>
            </div>

            <div class="cw-input-area">
                <div class="cw-input-wrapper">
                    <button id="cwEmojiBtn" class="cw-emoji-btn" onclick="toggleEmojiPicker(event)">😀</button>
                    <input type="text" id="cwInput" class="cw-input" placeholder="Mensagem" autocomplete="off" onkeypress="handleCwEnter(event)">
                </div>
                <button class="cw-btn-send" onclick="sendCwMessage()">➤</button>
            </div>
        </div>
    </div>
</div>

<script>
    const baseUrl = window.BASE_URL || ''; 
    const API_URL = baseUrl + 'modules/chat/api.php';
    const MY_ID_CW = <?php echo $_SESSION['user_id'] ?? 0; ?>;
    
    let cwReceiverId = null;
    let cwInterval = null;
    let lastTotalBadge = 0;
    let lastMsgCount = 0;
    let audioUnlocked = false;

    function unlockAudio() {
        if (audioUnlocked) return;
        const audio = document.getElementById('chatSound');
        if(audio) { audio.play().then(() => { audio.pause(); audio.currentTime = 0; audioUnlocked = true; }).catch(e => {}); }
        document.removeEventListener('click', unlockAudio);
    }
    document.addEventListener('click', unlockAudio);

    function playChatSound() {
        const audio = document.getElementById('chatSound');
        if(audio) { audio.currentTime = 0; audio.volume = 0.5; audio.play().catch(e => {}); }
    }

    function toggleEmojiPicker(e) {
        if(e) e.stopPropagation();
        const popover = document.getElementById('emojiPopover');
        popover.classList.toggle('active');
        if(popover.classList.contains('active')) document.getElementById('cwMsgs').scrollTop = document.getElementById('cwMsgs').scrollHeight;
    }
    document.querySelector('emoji-picker').addEventListener('emoji-click', event => {
        const input = document.getElementById('cwInput'); input.value += event.detail.unicode; input.focus();
    });
    document.getElementById('cwMsgs').addEventListener('click', () => { document.getElementById('emojiPopover').classList.remove('active'); });

    function toggleChatWindow() {
        const win = document.getElementById('chatWindow');
        const wrapper = document.getElementById('chatWidgetWrapper');
        const badge = document.getElementById('chatTotalBadge');

        if (win.style.display === 'flex') {
            win.style.display = 'none';
        } else {
            win.style.display = 'flex';
            loadCwUsers();
            wrapper.classList.remove('has-new');
            badge.style.display = 'none';
            lastTotalBadge = 0;
        }
    }

    function loadCwUsers() {
        if(cwReceiverId) return;
        fetch(API_URL + '?action=list_users').then(res => res.json()).then(users => {
            const list = document.getElementById('cwUserList');
            let html = '';
            if (!users || users.length === 0) {
                html = '<div style="padding:30px; text-align:center; color:#94a3b8;">Nenhum usuário.</div>';
            } else {
                users.forEach(u => {
                    let badge = u.unread > 0 ? `<span class="cw-unread">${u.unread}</span>` : '';
                    let avatar = u.avatar ? (baseUrl + 'uploads/avatars/' + u.avatar) : null;
                    let avatarHtml = avatar ? `<img src="${avatar}">` : u.name[0].toUpperCase();
                    html += `<div class="cw-user-item" onclick="openCwChat(event, ${u.id}, '${u.name}')"><div class="cw-avatar">${avatarHtml}</div><div class="cw-info"><div class="cw-name">${u.name}</div><div class="cw-last-msg">${u.unread>0 ? 'Nova mensagem' : 'Conversar'}</div></div>${badge}</div>`;
                });
            }
            list.innerHTML = html;
        });
    }

    function openCwChat(e, id, name) {
        if(e) e.stopPropagation();
        cwReceiverId = id;
        lastMsgCount = 0;
        
        document.getElementById('chatTitle').innerText = name;
        document.getElementById('cwUserList').style.display = 'none';
        document.getElementById('cwConversation').style.display = 'flex';
        document.getElementById('chatBackBtn').style.display = 'block';
        document.getElementById('chatClearBtn').style.display = 'block'; // Mostra botão limpar
        
        loadCwMessages();
        if(cwInterval) clearInterval(cwInterval);
        cwInterval = setInterval(loadCwMessages, 3000);
        setTimeout(() => document.getElementById('cwInput').focus(), 300);
    }

    function goBackToList(e) {
        if(e) e.stopPropagation();
        cwReceiverId = null;
        if(cwInterval) clearInterval(cwInterval);
        document.getElementById('chatTitle').innerText = 'Equipe';
        document.getElementById('cwUserList').style.display = 'block';
        document.getElementById('cwConversation').style.display = 'none';
        document.getElementById('chatBackBtn').style.display = 'none';
        document.getElementById('chatClearBtn').style.display = 'none'; // Esconde botão limpar
        document.getElementById('emojiPopover').classList.remove('active');
        loadCwUsers();
    }

    function loadCwMessages() {
        if(!cwReceiverId) return;
        fetch(API_URL + `?action=get_messages&user_id=${cwReceiverId}`).then(res => res.json()).then(msgs => {
            const area = document.getElementById('cwMsgs');
            const isBottom = area.scrollHeight - area.scrollTop <= area.clientHeight + 150;
            let html = '';
            if(msgs) {
                msgs.forEach(m => {
                    let type = m.sender_id == MY_ID_CW ? 'cw-msg-me' : 'cw-msg-other';
                    html += `<div class="cw-msg ${type}">${m.message}</div>`;
                });
            }
            if(area.innerHTML !== html) {
                area.innerHTML = html;
                if(isBottom || !html) area.scrollTop = area.scrollHeight;
                if (msgs.length > lastMsgCount && lastMsgCount !== 0) {
                    const lastMsg = msgs[msgs.length - 1];
                    if (lastMsg.sender_id != MY_ID_CW) playChatSound();
                }
                lastMsgCount = msgs.length;
            }
        });
    }

    function sendCwMessage() {
        const input = document.getElementById('cwInput');
        const txt = input.value.trim();
        if(!txt || !cwReceiverId) return;
        
        const fd = new FormData();
        fd.append('receiver_id', cwReceiverId);
        fd.append('message', txt);
        
        document.getElementById('cwMsgs').innerHTML += `<div class="cw-msg cw-msg-me" style="opacity:0.6">${txt}</div>`;
        document.getElementById('cwMsgs').scrollTop = document.getElementById('cwMsgs').scrollHeight;
        input.value = '';
        input.focus();
        document.getElementById('emojiPopover').classList.remove('active');
        
        fetch(API_URL + '?action=send', { method:'POST', body:fd }).then(res => res.json()).then(d => loadCwMessages());
    }
    
    function handleCwEnter(e) { if(e.key==='Enter') sendCwMessage(); }

    // --- NOVA FUNÇÃO: LIMPAR CHAT ---
    function clearCwChat(e) {
        if(e) e.stopPropagation();
        if(!cwReceiverId) return;
        if(!confirm('Tem certeza que deseja apagar todo o histórico dessa conversa?')) return;

        const fd = new FormData();
        fd.append('action', 'clear_chat'); // Necessário se a API usar POST action param
        fd.append('receiver_id', cwReceiverId);

        fetch(API_URL + '?action=clear_chat', { // Query param para API switch
            method: 'POST', 
            body: fd 
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('cwMsgs').innerHTML = '<div style="text-align:center; padding:20px; color:#ccc; font-size:0.8rem;">Conversa limpa.</div>';
            lastMsgCount = 0;
        })
        .catch(err => console.error("Erro ao limpar", err));
    }

    function checkTotalUnread() {
        if(cwReceiverId) return;
        fetch(API_URL + '?action=check_total_unread').then(res => res.json()).then(d => {
            const total = parseInt(d.total);
            const badge = document.getElementById('chatTotalBadge');
            const wrapper = document.getElementById('chatWidgetWrapper');
            const isClosed = document.getElementById('chatWindow').style.display !== 'flex';

            if(total > 0 && isClosed) {
                badge.style.display = 'flex';
                badge.innerText = total;
                if(total > lastTotalBadge) { playChatSound(); wrapper.classList.add('has-new'); }
            } else {
                badge.style.display = 'none'; wrapper.classList.remove('has-new');
            }
            lastTotalBadge = total;
        }).catch(()=>{});
    }
    
    if(MY_ID_CW > 0) {
        setInterval(checkTotalUnread, 5000);
        checkTotalUnread();
    }
</script>