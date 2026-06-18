function showSuccessModal(title?: string, message?: string): void {
    try {
        let modalEl = document.getElementById('modalSucessoCadastro');
        if (!modalEl) {
            const div = document.createElement('div');
            div.innerHTML = [
                '<div class="modal fade" id="modalSucessoCadastro" tabindex="-1" data-bs-backdrop="static">',
                '<div class="modal-dialog modal-dialog-centered">',
                '<div class="modal-content border-0">',
                '<div class="modal-body py-4">',
                '<div class="d-flex align-items-center gap-3">',
                '<div><span class="success-icon"><i class="fas fa-check-circle fa-3x text-success"></i></span></div>',
                '<div class="flex-grow-1">',
                '<h5 class="fw-bold mb-1" id="sucessoModalTitle">Cadastro Realizado!</h5>',
                '<p class="text-muted mb-0" id="sucessoModalMessage">O registro foi concluído com sucesso.</p>',
                '</div>',
                '<button type="button" class="btn btn-success px-3 flex-shrink-0" data-bs-dismiss="modal"><i class="fas fa-check me-2"></i>OK</button>',
                '</div></div></div></div></div>'
            ].join('\n');
            const firstChild = div.firstElementChild;
            if (firstChild) document.body.appendChild(firstChild);
            modalEl = document.getElementById('modalSucessoCadastro');
        }

        const titleEl = document.getElementById('sucessoModalTitle');
        const msgEl = document.getElementById('sucessoModalMessage');
        if (titleEl) titleEl.textContent = title || 'Cadastro Realizado!';
        if (msgEl) msgEl.textContent = message || 'O registro foi concluído com sucesso.';

        if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal && modalEl) {
            const modal = new window.bootstrap.Modal(modalEl);
            modal.show();
        }
    } catch (e) {
        console.warn('showSuccessModal fallback:', e);
        if (typeof window.showToast === 'function') {
            window.showToast(message || title || 'Cadastro realizado com sucesso!', 'success');
        }
    }
}

window.showSuccessModal = showSuccessModal;
export {};