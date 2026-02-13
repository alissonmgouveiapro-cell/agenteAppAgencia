/* ARQUIVO: assets/js/toast.js */

// Cria o container se não existir
function initToastContainer() {
    if (!document.getElementById('toast-container')) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
}

/**
 * Exibe uma notificação Toast
 * @param {string} message - O texto a ser exibido
 * @param {string} type - 'success', 'error', 'warning', 'info'
 */
function showToast(message, type = 'info') {
    initToastContainer();
    const container = document.getElementById('toast-container');

    // Ícones baseados no tipo
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    const icon = icons[type] || icons.info;

    // Cria o elemento HTML
    const toast = document.createElement('div');
    toast.className = `toast-card toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <span class="toast-icon">${icon}</span>
            <span>${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;

    // Adiciona ao container
    container.appendChild(toast);

    // Remove automaticamente após 4 segundos
    setTimeout(() => {
        toast.classList.add('hide');
        toast.addEventListener('animationend', () => {
            toast.remove();
        });
    }, 4000);
}

// Verifica se existe mensagem na URL (ex: ?msg=Sucesso&type=success) ao carregar a página
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('msg')) {
        const msg = urlParams.get('msg');
        const type = urlParams.get('type') || 'success';
        showToast(msg, type);
        
        // Limpa a URL para não mostrar a msg de novo se der F5
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});