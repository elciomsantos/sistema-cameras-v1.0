let toastContainer: HTMLDivElement | null = null
let confirmModal: BootstrapModal | null = null
let confirmResolve: ((value: boolean) => void) | null = null

function ensureToastContainer(): HTMLDivElement {
    if (!toastContainer) {
        toastContainer = document.createElement('div')
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3'
        toastContainer.style.zIndex = '9999'
        document.body.appendChild(toastContainer)
    }
    return toastContainer
}

function showToast(message: string, type: 'success' | 'danger' | 'warning' | 'info' = 'success'): void {
    const iconMap: Record<string, string> = { success: 'fa-check-circle text-success', danger: 'fa-exclamation-circle text-danger', warning: 'fa-exclamation-triangle text-warning', info: 'fa-info-circle text-info' }
    const icon = iconMap[type] || iconMap.info
    const bgMap: Record<string, string> = { success: 'bg-success-subtle border-success', danger: 'bg-danger-subtle border-danger', warning: 'bg-warning-subtle border-warning', info: 'bg-info-subtle border-info' }
    const bgClass = bgMap[type] || bgMap.info

    const container = ensureToastContainer()
    const id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5)

    const html = '<div id="' + id + '" class="toast align-items-center border-2 ' + bgClass + '" role="alert" aria-live="assertive" aria-atomic="true">'
        + '<div class="d-flex">'
        + '<div class="toast-body"><i class="fas ' + icon + ' me-2"></i>' + escapeHtml(message) + '</div>'
        + '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>'
        + '</div></div>'

    container.insertAdjacentHTML('beforeend', html)
    const el = document.getElementById(id)
    if (el && typeof window.bootstrap !== 'undefined' && window.bootstrap!.Toast) {
        const toast = new window.bootstrap!.Toast(el, { delay: 3000 })
        toast.show()
        el.addEventListener('hidden.bs.toast', function () { el.remove() })
    } else {
        setTimeout(function () { if (el) el.remove() }, 3500)
    }
}

function ensureConfirmModal(): BootstrapModal {
    if (confirmModal) return confirmModal

    const modalHtml = document.createElement('div')
    modalHtml.innerHTML = [
        '<div class="modal fade" tabindex="-1" role="dialog" id="appConfirmModal">',
        '<div class="modal-dialog modal-dialog-centered">',
        '<div class="modal-content">',
        '<div class="modal-header">',
        '<h5 class="modal-title" id="appConfirmTitle">Confirmar</h5>',
        '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>',
        '</div>',
        '<div class="modal-body" id="appConfirmBody"></div>',
        '<div class="modal-footer">',
        '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="appConfirmCancel">Cancelar</button>',
        '<button type="button" class="btn btn-danger" id="appConfirmOk">Sim, excluir</button>',
        '</div></div></div></div>'
    ].join('\n')

    document.body.appendChild(modalHtml.firstElementChild!)

    confirmModal = new window.bootstrap!.Modal(document.getElementById('appConfirmModal')!, { backdrop: 'static', keyboard: false })

    document.getElementById('appConfirmOk')!.addEventListener('click', function () {
        if (confirmResolve) confirmResolve(true)
        confirmModal!.hide()
    })

    document.getElementById('appConfirmCancel')!.addEventListener('click', function () {
        if (confirmResolve) confirmResolve(false)
    })

    document.getElementById('appConfirmModal')!.addEventListener('hidden.bs.modal', function () {
        if (confirmResolve) confirmResolve(false)
    })

    return confirmModal
}

function showConfirm(title: string, htmlContent: string, confirmText?: string): Promise<boolean> {
    return new Promise(function (resolve) {
        confirmResolve = resolve
        const modal = ensureConfirmModal()

        const titleEl = document.getElementById('appConfirmTitle')
        if (titleEl) titleEl.textContent = title || 'Confirmar'

        const bodyEl = document.getElementById('appConfirmBody')
        if (bodyEl) bodyEl.innerHTML = (htmlContent || '').replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '').replace(/\son\w+\s*=\s*"[^"]*"/gi, '').replace(/\son\w+\s*=\s*'[^']*'/gi, '').replace(/href\s*=\s*"javascript:[^"]*"/gi, 'href="#"').replace(/href\s*=\s*'javascript:[^']*'/gi, "href='#'")

        const okBtn = document.getElementById('appConfirmOk')
        if (okBtn) okBtn.textContent = confirmText || 'Sim, excluir'

        modal.show()
    })
}

function handleApiError(error: unknown, fallbackMessage: string = 'Erro inesperado.'): string {
    const message = error instanceof Error ? error.message : fallbackMessage;
    console.error(fallbackMessage, error);
    showToast(message, 'danger');
    return message;
}

import { escapeHtml } from './html';

window.showToast = showToast;
window.showConfirm = showConfirm;
(window as any).handleApiError = handleApiError;
export {};