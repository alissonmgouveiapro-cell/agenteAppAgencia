/* ARQUIVO: assets/js/client-view.js */

document.addEventListener('DOMContentLoaded', () => {
    // Seleção de Elementos
    const modal = document.getElementById('mainModal');
    const modalVisual = document.getElementById('modalVisual');
    const modalTitle = document.getElementById('mTitle');
    const modalDesc = document.getElementById('mDesc');
    const inputPostId = document.getElementById('mPostId');
    const inputStatus = document.getElementById('mStatus');
    const actionBtns = document.getElementById('actionBtns');
    const fbArea = document.getElementById('fbArea');
    const approvalForm = document.getElementById('approvalForm');
    
    // Botões do Carrossel
    const btnPrev = document.getElementById('btnPrev');
    const btnNext = document.getElementById('btnNext');
    
    let currentFiles = [];
    let currentIndex = 0;

    // Configuração base de URL (fallback)
    const basePath = window.UPLOADS_BASE_URL || '../uploads/';

    // --- RENDERIZAR MÍDIA ---
    function renderCurrentFile() {
        if (!currentFiles || currentFiles.length === 0) return;

        const file = currentFiles[currentIndex];
        
        // Verifica extensões
        const ext = file.file_path.split('.').pop().toLowerCase();
        const isVideo = ['mp4', 'mov', 'webm', 'ogg'].includes(ext);
        const fullPath = basePath + file.file_path;

        // Limpa conteúdo anterior
        modalVisual.innerHTML = '';
        
        // Injeta novo conteúdo
        if (isVideo) {
            modalVisual.innerHTML = `<video src="${fullPath}" controls autoplay class="modal-obj"></video>`;
        } else {
            modalVisual.innerHTML = `<img src="${fullPath}" class="modal-obj">`;
        }

        // --- LÓGICA DOS BOTÕES (CORRIGIDA) ---
        // Se tiver mais de 1 arquivo, MOSTRA os botões sempre (Loop)
        if (currentFiles.length > 1) {
            if(btnPrev) btnPrev.style.display = 'flex';
            if(btnNext) btnNext.style.display = 'flex';
        } else {
            // Se tiver só 1 arquivo, ESCONDE
            if(btnPrev) btnPrev.style.display = 'none';
            if(btnNext) btnNext.style.display = 'none';
        }
    }

    // --- ABRIR MODAL ---
    window.openModal = function(postData, fileData) {
        inputPostId.value = postData.id;
        modalTitle.innerText = postData.title;
        modalDesc.innerText = postData.caption || "Sem descrição.";

        // Prepara Carrossel
        currentFiles = fileData;
        currentIndex = 0; // Começa sempre do primeiro
        
        renderCurrentFile();

        hideFeedback();
        if(modal) modal.classList.add('open');
        document.body.style.overflow = 'hidden'; // Trava scroll da página
    };

    // --- NAVEGAÇÃO COM LOOP (INFINITO) ---
    window.nextSlide = function() {
        if (currentFiles.length <= 1) return;
        
        currentIndex++;
        // Se passou do último, volta para o primeiro
        if (currentIndex >= currentFiles.length) {
            currentIndex = 0;
        }
        renderCurrentFile();
    };

    window.prevSlide = function() {
        if (currentFiles.length <= 1) return;

        currentIndex--;
        // Se voltou antes do primeiro, vai para o último
        if (currentIndex < 0) {
            currentIndex = currentFiles.length - 1;
        }
        renderCurrentFile();
    };

    // --- FECHAR MODAL ---
    window.closeModal = function() {
        if(modal) modal.classList.remove('open');
        document.body.style.overflow = '';
        // Limpa o vídeo para parar o som
        setTimeout(() => { 
            if(modalVisual) modalVisual.innerHTML = ''; 
        }, 300);
    };

    // --- AÇÕES DE APROVAÇÃO ---
    window.showFeedback = function() {
        if(actionBtns) actionBtns.style.display = 'none';
        if(fbArea) fbArea.style.display = 'block';
    };

    window.hideFeedback = function() {
        if(actionBtns) actionBtns.style.display = 'block';
        if(fbArea) fbArea.style.display = 'none';
        const txt = document.querySelector('.fb-textarea');
        if(txt) txt.value = '';
    };

    window.submitStatus = function(status) {
        if (status === 'approved') {
            if (!confirm("Confirmar aprovação?")) return;
        } else if (status === 'changes') {
            const txt = document.querySelector('.fb-textarea');
            if (txt && txt.value.trim() === "") {
                alert("Descreva o ajuste necessário.");
                return;
            }
        }
        inputStatus.value = status;
        approvalForm.submit();
    };

    // --- TECLADO ---
    document.addEventListener('keydown', (e) => {
        if (modal && !modal.classList.contains('open')) return;
        
        if (e.key === 'Escape') window.closeModal();
        if (e.key === 'ArrowRight') window.nextSlide();
        if (e.key === 'ArrowLeft') window.prevSlide();
    });
});