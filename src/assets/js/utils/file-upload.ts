import { escapeHtml } from './html';

const API_BASE = window.APP_API_BASE || (window.BASE_URL || '/') + 'index.php?page=api/'

const UPLOAD_URL = API_BASE + 'api_upload_anexo'
const LIST_URL = API_BASE + 'api_listar_anexos'
const DELETE_URL = API_BASE + 'api_excluir_anexo'

function getCsrf(): string {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    return meta ? meta.getAttribute('content') ?? '' : ''
}

function formatSize(bytes: number): string {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2).replace('.', ',') + ' MB'
    if (bytes >= 1024) return Math.round(bytes / 1024) + ' KB'
    return bytes + ' B'
}

function getFileIcon(mimeType: string | null): string {
    if (!mimeType) return 'fa-file'
    if (mimeType.indexOf('image/') === 0) return 'fa-file-image'
    if (mimeType === 'application/pdf') return 'fa-file-pdf'
    if (mimeType.indexOf('word') !== -1 || mimeType.indexOf('document') !== -1) return 'fa-file-word'
    if (mimeType.indexOf('excel') !== -1 || mimeType.indexOf('spreadsheet') !== -1) return 'fa-file-excel'
    return 'fa-file'
}

function getPreviewHtml(anexo: AnexoData): HTMLElement {
    const isImage = anexo.mime_type && anexo.mime_type.indexOf('image/') === 0
    const icon = getFileIcon(anexo.mime_type)

    if (isImage) {
        const img = document.createElement('img')
        img.src = anexo.url
        img.alt = anexo.nome_original || ''
        img.className = 'anexo-preview-img'
        img.loading = 'lazy'
        img.onerror = function () {
            this.onerror = null
            const fallback = document.createElement('i')
            fallback.className = 'fas ' + icon + ' fa-3x'
            ;(this.parentElement as HTMLElement).replaceChild(fallback, this)
        }
        return img
    }
    const wrapper = document.createElement('i')
    wrapper.className = 'fas ' + icon + ' fa-3x'
    return wrapper
}

function loadAnexos(container: HTMLElement): void {
    const listEl = container && container.querySelector<HTMLElement>('.anexos-list')
    if (!listEl) return

    const equipamentoId = listEl.getAttribute('data-equipamento-id')
    const alarmeId = listEl.getAttribute('data-alarme-id')
    const manutencaoCameraId = listEl.getAttribute('data-manutencao-camera-id')
    const manutencaoAlarmeId = listEl.getAttribute('data-manutencao-alarme-id')
    const emptyMsg = listEl.getAttribute('data-empty-msg') || 'Nenhum anexo cadastrado.'

    if (!equipamentoId && !alarmeId && !manutencaoCameraId && !manutencaoAlarmeId) {
        const p = document.createElement('p')
        p.className = 'text-muted text-center mb-0'
        p.textContent = emptyMsg
        listEl.appendChild(p)
        return
    }

    let params = ''
    if (equipamentoId) params = 'equipamento_id=' + equipamentoId
    else if (alarmeId) params = 'alarme_id=' + alarmeId
    else if (manutencaoCameraId) params = 'manutencao_camera_id=' + manutencaoCameraId
    else if (manutencaoAlarmeId) params = 'manutencao_alarme_id=' + manutencaoAlarmeId

    listEl.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>'

    fetchWithTimeout(LIST_URL + '&' + params, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (r: Response) { if (!r.ok) throw new Error('Falha ao carregar anexos.'); return r.json() })
        .then(function (res: ApiResponse<unknown>) {
            const anexos = Array.isArray(res.data) ? res.data as AnexoData[] : (res.data && (res.data as any).data ? (res.data as any).data as AnexoData[] : [])
            if (!res.success || !anexos.length) {
                listEl.innerHTML = '<p class="text-muted text-center mb-0">' + emptyMsg + '</p>'
                return
            }

            let html = '<div class="row g-2">'
            anexos.forEach(function (a: AnexoData) {
                html += '<div class="col-6 col-md-4 col-lg-3">'
                html += '<div class="anexo-item card card-body p-2 text-center" data-id="' + a.id + '">'
                html += '<a href="' + escapeHtml(a.url) + '" target="_blank" class="anexo-link d-block mb-1" title="' + escapeHtml(a.nome_original) + '">'
                html += '<span class="anexo-preview-placeholder"></span>'
                html += '</a>'
                html += '<small class="anexo-nome text-truncate d-block" title="' + escapeHtml(a.nome_original) + '">' + escapeHtml(a.nome_original) + '</small>'
                html += '<small class="text-muted">' + (a.tamanho_formatado || formatSize(a.tamanho)) + '</small>'
                if (container.classList.contains('anexos-editable')) {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger mt-1 anexo-btn-excluir" data-id="' + a.id + '" data-nome="' + escapeHtml(a.nome_original) + '" data-url="' + escapeHtml(a.url) + '" data-tipo="' + escapeHtml(a.mime_type || '') + '"><i class="fas fa-trash-alt"></i></button>'
                }
                html += '</div></div>'
            })
            html += '</div>'
            listEl.innerHTML = html

            const placeholders = listEl.querySelectorAll<HTMLElement>('.anexo-preview-placeholder')
            anexos.forEach(function (a: AnexoData, index: number) {
                if (placeholders[index]) {
                    const preview = getPreviewHtml(a)
                    placeholders[index].replaceWith(preview)
                }
            })
        })
        .catch(function () {
            listEl.innerHTML = '<p class="text-danger text-center mb-0">Erro ao carregar anexos.</p>'
        })
}

function uploadFile(
    container: HTMLElement,
    file: File,
    tipo: string,
    descricao: string,
    onProgress?: (pct: number) => void
): Promise<AnexoData> {
    return new Promise(function (resolve, reject) {
        const formData = new FormData()
        formData.append('file', file)
        formData.append('tipo', tipo || 'foto')
        if (descricao) formData.append('descricao', descricao)

        const listEl = container.querySelector<HTMLElement>('.anexos-list')
        if (listEl) {
            const eid = listEl.getAttribute('data-equipamento-id')
            const aid = listEl.getAttribute('data-alarme-id')
            const mcid = listEl.getAttribute('data-manutencao-camera-id')
            const maid = listEl.getAttribute('data-manutencao-alarme-id')
            if (eid) formData.append('equipamento_id', eid)
            if (aid) formData.append('alarme_id', aid)
            if (mcid) formData.append('manutencao_camera_id', mcid)
            if (maid) formData.append('manutencao_alarme_id', maid)
        }

        const xhr = new XMLHttpRequest()
        xhr.open('POST', UPLOAD_URL, true)
        xhr.setRequestHeader('X-CSRF-TOKEN', getCsrf())
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')

        xhr.upload.addEventListener('progress', function (e: ProgressEvent) {
            if (e.lengthComputable && onProgress) {
                onProgress(Math.round((e.loaded / e.total) * 100))
            }
        })

        xhr.addEventListener('load', function () {
            try {
                const res = JSON.parse(xhr.responseText) as ApiResponse<unknown>
                if (res.success) resolve((res as any).data?.resource?.anexo || (res as any).anexo)
                else reject(new Error(res.error || 'Erro no upload'))
            } catch (e) {
                reject(new Error('Resposta invalida do servidor'))
            }
        })

        xhr.addEventListener('error', function () {
            reject(new Error('Erro de conexao'))
        })

        xhr.send(formData)
    })
}

function excluirAnexo(anexoId: string): Promise<ApiResponse<unknown>> {
    const csrf = getCsrf()
    return fetchWithTimeout(DELETE_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: anexoId })
    }).then(function (r: Response) { if (!r.ok) throw new Error('Falha ao excluir anexo.'); return r.json() })
}

function initAnexoSection(container: HTMLElement | null): void {
    if (!container) return

    loadAnexos(container)

    if (container.getAttribute('data-anexos-init')) return

    container.setAttribute('data-anexos-init', '1')

    const dropZone = container.querySelector<HTMLElement>('.anexos-dropzone')
    const fileInput = container.querySelector<HTMLInputElement>('.anexos-file-input')

    if (!dropZone || !fileInput) return

    dropZone.addEventListener('click', function () {
        fileInput.click()
    })

    dropZone.addEventListener('dragover', function (e: DragEvent) {
        e.preventDefault()
        dropZone.classList.add('anexos-dragover')
    })

    dropZone.addEventListener('dragleave', function () {
        dropZone.classList.remove('anexos-dragover')
    })

    dropZone.addEventListener('drop', function (e: DragEvent) {
        e.preventDefault()
        dropZone.classList.remove('anexos-dragover')
        if (e.dataTransfer!.files.length) {
            handleFiles(container, e.dataTransfer!.files)
        }
    })

    fileInput.addEventListener('change', function () {
        if (fileInput.files!.length) {
            handleFiles(container, fileInput.files!)
            fileInput.value = ''
        }
    })

    container.addEventListener('click', function (e: MouseEvent) {
        const btn = (e.target as HTMLElement).closest('.anexo-btn-excluir') as HTMLElement | null
        if (!btn) return

        const id = btn.getAttribute('data-id')!
        const nome = btn.getAttribute('data-nome') || 'este anexo'
        const url = btn.getAttribute('data-url') || ''
        const mimeType = btn.getAttribute('data-tipo') || ''
        const isImage = mimeType.indexOf('image/') === 0

        let htmlContent = '<p class="mb-2">Deseja realmente excluir este anexo?</p>'
        htmlContent += '<p class="fw-bold mb-2 text-break">' + escapeHtml(nome) + '</p>'

        const confirmContainer = document.createElement('div')
        confirmContainer.innerHTML = htmlContent

        if (isImage && url) {
            const img = document.createElement('img')
            img.src = url
            img.alt = nome
            img.className = 'img-thumbnail mb-2 upload-img-preview'
            img.addEventListener('error', function () {
                img.style.display = 'none'
            })
            confirmContainer.appendChild(img)
        }

        if (typeof showConfirm === 'function') {
            showConfirm('Excluir anexo?', confirmContainer.innerHTML, 'Sim, excluir').then(function (confirmed: boolean) {
                if (confirmed) doDelete(container, id, nome)
            })
        } else if (confirm('Excluir "' + nome + '"?')) {
            doDelete(container, id, nome)
        }
    })
}

function handleFiles(container: HTMLElement, files: FileList): void {
    const progressContainer = container.querySelector<HTMLElement>('.anexos-progress-container')
    const progressBar = container.querySelector<HTMLElement>('.anexos-progress')
    const progressText = container.querySelector<HTMLElement>('.anexos-progress-text')

    const MAX_SIZE = 10485760
    let errorEl = container.querySelector<HTMLElement>('.anexos-error-msg')
    if (!errorEl) {
        errorEl = document.createElement('div')
        errorEl.className = 'anexos-error-msg text-danger small mt-1'
        const target = progressContainer || container.querySelector('.anexos-dropzone')
        if (target) target.parentNode!.insertBefore(errorEl, target.nextSibling)
    }
    errorEl.textContent = ''

    Array.from(files).forEach(function (file: File) {
        if (file.size > MAX_SIZE) {
            errorEl!.textContent = 'Arquivo "' + file.name + '" excede o limite de 10 MB e foi ignorado.'
            return
        }

        if (progressContainer) progressContainer.classList.remove('d-none')
        if (progressText) progressText.textContent = 'Enviando ' + file.name + '...'

        uploadFile(container, file, 'foto', '', function (pct: number) {
            if (progressBar) {
                progressBar.style.width = pct + '%'
                progressBar.setAttribute('aria-valuenow', String(pct))
            }
        }).then(function () {
            if (progressBar) progressBar.style.width = '100%'
            setTimeout(function () {
                if (progressContainer) progressContainer.classList.add('d-none')
                if (progressBar) progressBar.style.width = '0%'
            }, 1000)
            loadAnexos(container)
        }).catch(function (err: Error) {
            if (progressContainer) progressContainer.classList.add('d-none')
            if (progressBar) progressBar.style.width = '0%'
            window.showToast!('Erro no upload: ' + err.message, 'danger')
            loadAnexos(container)
        })
    })
}

function doDelete(container: HTMLElement, id: string, nome: string): void {
    excluirAnexo(id).then(function (res: ApiResponse<unknown>) {
        if (res.success) {
            loadAnexos(container)
        } else {
            window.showToast!(res.error || 'Erro ao excluir anexo', 'danger')
        }
    }).catch(function () {
        window.showToast!('Erro de conexão ao excluir anexo.', 'danger')
    })
}

window.initAnexoSection = initAnexoSection
window.loadAnexos = loadAnexos
export {};