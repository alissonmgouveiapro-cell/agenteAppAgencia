/* Arquivo: assets/js/notify.js */
/* Objetivo: Gerenciar notificações em tempo real e ações sem recarregar a página */

// Define a raiz do projeto baseada na variável injetada pelo PHP ou fallback
const APP_ROOT = (typeof window.BASE_URL !== 'undefined') ? window.BASE_URL : '../../';
const API_URL  = APP_ROOT + 'modules/notifications/api.php';

// --- 1. POLLING: Verifica notificações a cada 5 segundos ---
function checkNotifications() {
    fetch(API_URL + '?action=count')
        .then(response => {
            if (!response.ok) throw new Error('Erro na rede');
            return response.json();
        })
        .then(data => {
            const badge = document.getElementById('sidebar-notif-badge');
            
            if (data.count > 0) {
                if (badge) {
                    badge.innerText = data.count;
                    badge.style.display = 'inline-block';
                    // Adiciona uma animação suave se o número mudou
                    badge.style.transform = 'scale(1.2)';
                    setTimeout(() => badge.style.transform = 'scale(1)', 200);
                }
            } else {
                if (badge) badge.style.display = 'none';
            }
        })
        .catch(err => {
            // Silencia erros no console para não poluir, a menos que seja crítico
            // console.error('Polling error:', err); 
        });
}

// Inicia o ciclo (5000ms = 5 segundos)
setInterval(checkNotifications, 5000);

// Executa uma vez ao carregar para garantir sincronia imediata
document.addEventListener('DOMContentLoaded', checkNotifications);


// --- 2. AÇÃO: Marcar uma como lida ---
function markAsRead(id, btnElement) {
    const formData = new FormData();
    formData.append('id', id);

    fetch(API_URL + '?action=read', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // Atualiza visualmente o card
            const card = btnElement.closest('.notif-card');
            if (card) {
                card.classList.remove('notif-unread'); // Remove borda/cor
                card.style.opacity = '0.6'; // Deixa "apagado"
            }
            // Remove o botão de check
            btnElement.remove(); 
            
            // Atualiza o contador do menu imediatamente
            checkNotifications(); 
        }
    })
    .catch(err => alert("Erro ao atualizar notificação."));
}


// --- 3. AÇÃO: Limpar/Ler Todas ---
function clearAllNotifications() {
    if(!confirm("Deseja marcar todas as notificações como lidas?")) return;

    fetch(API_URL + '?action=clear_all', { method: 'POST' })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // Remove estilo de não lido de todos os cards visíveis
            document.querySelectorAll('.notif-card').forEach(card => {
                card.classList.remove('notif-unread');
                card.style.opacity = '0.6';
                
                // Remove botão de check individual se existir
                const btn = card.querySelector('.btn-check');
                if(btn) btn.remove();
            });

            // Zera o contador do menu
            checkNotifications(); 
            
            // Esconde o botão "Limpar Tudo"
            const clearBtn = document.getElementById('btnClearAll');
            if(clearBtn) clearBtn.style.display = 'none';
        }
    })
    .catch(err => alert("Erro ao limpar notificações."));
}