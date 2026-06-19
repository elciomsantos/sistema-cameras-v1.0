import { escapeHtml } from './utils/html';

(function() {
'use strict';

const listarCamerasConfigEl = document.getElementById('listarCamerasConfig') as HTMLElement;
const API_URL: string = listarCamerasConfigEl?.dataset.apiUrl || '';
const CAN_DELETE: boolean = listarCamerasConfigEl?.dataset.canDelete === '1';
const PER_PAGE = (window as any).PER_PAGE || 10;
let currentPage = (window as any).currentPage || 1;

function getFiltros(): { status: string; local: string; pesquisa: string } {
    const status = (document.getElementById('filtroStatus') as HTMLSelectElement).value;
    const local = (document.getElementById('filtroLocal') as HTMLSelectElement).value;
    const pesquisa = (document.getElementById('filtroPesquisa') as HTMLInputElement).value.trim();

    return {
        status,
        local,
        pesquisa
    };
}

function buildQueryParams(page: number): URLSearchParams {
    const filtros = getFiltros();
    const params = new URLSearchParams();
    params.set('page_num', String(page));
    params.set('per_page', String(PER_PAGE));

    if (filtros.status) {
        params.set('status', filtros.status);
    }
    if (filtros.local) {
        params.set('local_id', filtros.local);
    }
    if (filtros.pesquisa) {
        params.set('busca', filtros.pesquisa);
    }

    return params;
}

async function carregarListaCameras(page: number = 1): Promise<void> {
    try {
        currentPage = page;
        const container = document.getElementById("listaCameras")!;
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                <p>Carregando câmeras...</p>
            </div>
        `;

        const params = buildQueryParams(page);
        const response = await fetchWithTimeout(`${API_URL}&${params.toString()}`);

        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }

        const resultado = await response.json();

        if (resultado.success && Array.isArray(resultado.data)) {
            todasCameras = resultado.data;
            totalRecords = Number(resultado.pagination?.total || 0);
            totalPages = Number(resultado.pagination?.total_pages || 1);
            if (totalPages > 0 && currentPage > totalPages) {
                carregarListaCameras(totalPages);
                return;
            }
            if (todasCameras.length === 0 && totalRecords > 0 && currentPage > 1) {
                carregarListaCameras(currentPage - 1);
                return;
            }
            exibirCameras(todasCameras);
            renderPaginacao();

            showToast(totalRecords + ' câmeras encontradas', 'success');
        } else {
            throw new Error(resultado.error || 'Formato de dados inválido');
        }

    } catch (error) {
        console.error('Erro ao carregar câmeras:', error);
        mostrarErro('Erro ao carregar câmeras: ' + (error as Error).message);
    }
}

function mostrarDetalhesCamera(cameraId: string): void {
    const camera = todasCameras.find(c => String(c.id) === String(cameraId));
    if (!camera) return;

    const detalhesContent = document.getElementById('detalhesCameraContent')!;
    const id = escapeHtml(String(camera.id || 'N/A'));
    const descricao = escapeHtml((camera.descricao as string) || 'Não informado');
    const statusNome = escapeHtml((camera.status_nome as string) || 'Não informado');
    const ip = escapeHtml((camera.ip as string) || 'Não informado');
    const serieMac = escapeHtml((camera.serie_mac as string) || 'Não informado');
    const modeloNome = escapeHtml((camera.modelo_nome as string) || 'Não informado');
    const localNome = escapeHtml((camera.local_nome as string) || 'Não informado');
    const secretariaNome = escapeHtml((camera.secretaria_nome as string) || 'Não informado');
    const observacao = camera.observacao ? escapeHtml(String(camera.observacao)) : '';

    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2">Informações Básicas</h6>
                <p><strong>ID:</strong> ${id}</p>
                <p><strong>Descrição:</strong> ${descricao}</p>
                <p><strong>Status:</strong> <span class="badge ${getStatusBadgeClass(camera.status_nome as string)}">${statusNome}</span></p>
                <p><strong>Data Instalação:</strong> ${camera.data_instalacao ? formatarData(camera.data_instalacao as string) : 'Não informada'}</p>
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2">Configurações Técnicas</h6>
                <p><strong>IP:</strong> <code>${ip}</code></p>
                <p><strong>Série/MAC:</strong> ${serieMac}</p>
                <p><strong>Modelo:</strong> ${modeloNome}</p>
                <p><strong>Local:</strong> ${localNome}</p>
                <p><strong>Secretaria:</strong> ${secretariaNome}</p>
            </div>
        </div>
    `;

    if (observacao) {
        html += `
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="border-bottom pb-2">Observações</h6>
                    <p>${observacao}</p>
                </div>
            </div>
        `;
    }

    detalhesContent.innerHTML = html;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhesCamera')!);
    modal.show();
}

function formatarData(dataString: string): string {
    if (!dataString) return '';
    const data = new Date(dataString);
    return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR');
}

function getStatusBadgeClass(statusNome: string): string {
    const statusLower = statusNome ? statusNome.toLowerCase() : '';
    if (statusLower.includes('ativa') || statusLower.includes('funcionando')) {
        return 'bg-success';
    } else if (statusLower.includes('inativa') || statusLower.includes('desligado')) {
        return 'bg-danger';
    } else if (statusLower.includes('manutenção') || statusLower.includes('manutencao')) {
        return 'bg-warning';
    }
    return 'bg-secondary';
}

function aplicarFiltros(): void {
    carregarListaCameras(1);
}

function exibirCameras(cameras: Array<Record<string, unknown>>): void {
    const container = document.getElementById('listaCameras')!;
    const contador = totalRecords || cameras.length;
    document.getElementById('contadorCameras')!.textContent = String(contador);

    if (cameras.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-camera-slash fa-2x mb-2"></i>
                <p>Nenhuma câmera encontrada</p>
                <button type="button" class="btn btn-primary mt-2" data-action="limpar-filtros">
                    <i class="fas fa-broom me-1"></i>Limpar Filtros
                </button>
            </div>
        `;
        return;
    }

    let html = `
        <div class="table-responsive" aria-live="polite" aria-busy="false">
            <table class="table table-hover table-striped table-sm">
                <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">IP</th>
                        <th scope="col">Série/MAC</th>
                        <th scope="col">Local</th>
                        <th scope="col">Secretaria</th>
                        <th scope="col">Status</th>
                        <th scope="col">Modelo</th>
                        <th scope="col">Data Instalação</th>
                        <th scope="col" class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
    `;

    cameras.forEach(camera => {
        const idRaw = String(camera.id || '').trim();
        if (!idRaw) {
            return;
        }
        const id = escapeHtml(idRaw);
        const idAttr = escapeHtml(idRaw);
        const descricaoRaw = (camera.descricao as string) || 'Sem descrição';
        const descricao = escapeHtml(descricaoRaw);
        const ip = escapeHtml((camera.ip as string) || 'Não informado');
        const serieMac = escapeHtml((camera.serie_mac as string) || 'Não informado');
        const local = escapeHtml((camera.local_nome as string) || 'Não informado');
        const secretaria = escapeHtml((camera.secretaria_nome as string) || 'Não informado');
        const status = escapeHtml((camera.status_nome as string) || 'Desconhecido');
        const modelo = escapeHtml((camera.modelo_nome as string) || 'Não informado');
        const dataInstalacao = camera.data_instalacao as string || '';
        const dataFormatada = dataInstalacao ? formatarData(dataInstalacao) : 'Não informada';
        const observacao = camera.observacao ? escapeHtml(String(camera.observacao).substring(0, 50)) : '';
        const observacaoSufixo = camera.observacao && String(camera.observacao).length > 50 ? '...' : '';
        const descricaoAttr = escapeHtml(descricaoRaw);
        const statusBadge = getStatusBadgeClass(camera.status_nome as string);

        html += `
            <tr>
                <td><span class="badge bg-secondary">${id}</span></td>
                <td>
                    <div class="fw-bold">${descricao}</div>
                    ${observacao ? `<small class="text-muted d-block">${observacao}${observacaoSufixo}</small>` : ''}
                </td>
                <td><code class="text-primary">${ip}</code></td>
                <td><small class="text-muted">${serieMac}</small></td>
                <td>${local}</td>
                <td>${secretaria}</td>
                <td><span class="badge ${statusBadge}">${status}</span></td>
                <td>${modelo}</td>
                <td>${dataFormatada}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-info py-1 px-2" 
                            data-action="detalhes"
                            data-camera-id="${idAttr}"
                            title="Ver detalhes"
                            data-bs-toggle="tooltip">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a href="?page=editar_cameras&id=${encodeURIComponent(idRaw)}" class="btn btn-primary py-1 px-2"
                            title="Editar câmera" data-bs-toggle="tooltip">
                            <i class="fas fa-edit"></i>
                        </a>
                        ${CAN_DELETE ? `
                        <button class="btn btn-danger py-1 px-2" 
                            data-action="excluir"
                            data-camera-id="${idAttr}"
                            data-camera-descricao="${descricaoAttr}"
                            title="Excluir câmera"
                            data-bs-toggle="tooltip">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <small class="text-muted">Mostrando ${cameras.length} de ${contador} câmera(s)</small>
        </div>
    `;

    container.innerHTML = html;

    if ((window as any)._listarCamerasTooltips) {
        (window as any)._listarCamerasTooltips.forEach((t: any) => t.dispose());
    }
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    (window as any)._listarCamerasTooltips = tooltipTriggerList.map(function(tooltipTriggerEl: Element) {
        return new bootstrap.Tooltip(tooltipTriggerEl as HTMLElement);
    });
}

function limparFiltros(): void {
    (document.getElementById('filtroStatus') as HTMLSelectElement).value = '';
    (document.getElementById('filtroLocal') as HTMLSelectElement).value = '';
    (document.getElementById('filtroPesquisa') as HTMLInputElement).value = '';
    carregarListaCameras(1);
}

function renderPaginacao(): void {
    const paginacao = document.getElementById('paginacaoCameras');
    if (!paginacao) return;

    if (totalPages <= 1) {
        paginacao.innerHTML = '';
        return;
    }

    const prevDisabled = currentPage <= 1 ? 'disabled' : '';
    const nextDisabled = currentPage >= totalPages ? 'disabled' : '';

    paginacao.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <button class="btn btn-outline-secondary btn-sm" data-action="pagina-anterior" ${prevDisabled}>
                <i class="fas fa-chevron-left me-1"></i>Anterior
            </button>
            <span class="text-muted small">Página ${currentPage} de ${totalPages}</span>
            <button class="btn btn-outline-secondary btn-sm" data-action="pagina-proxima" ${nextDisabled}>
                Próxima<i class="fas fa-chevron-right ms-1"></i>
            </button>
        </div>
    `;
}

function abrirModalExclusao(cameraId: string, cameraDescricao: string): void {
    currentCameraId = cameraId;
    document.getElementById('cameraInfo')!.textContent = `ID: ${cameraId} - ${cameraDescricao}`;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirmacaoExclusao')!);
    modal.show();
}

async function excluirCamera(): Promise<void> {
    if (!CAN_DELETE) {
        showToast('Permissao insuficiente para excluir camera.', 'warning');
        return;
    }

    if (!currentCameraId) return;

    try {
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalConfirmacaoExclusao')!);
        modal!.hide();

        const btnConfirmar = document.getElementById('btnConfirmarExclusao') as HTMLButtonElement;
        const originalHTML = btnConfirmar.innerHTML;
        btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Excluindo...';
        btnConfirmar.disabled = true;

        const formData = new FormData();
        formData.append('id', currentCameraId);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const response = await fetchWithTimeout(`${window.APP_API_BASE}api_excluir_camera`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: formData
        });

        if (!response.ok) throw new Error('Falha ao excluir camera.');
        const resultado = await response.json();

        if (resultado.success === true) {
            showToast('Câmera excluída com sucesso!', 'success');
            carregarListaCameras(currentPage);
        } else {
            throw new Error(resultado.message || 'Erro ao excluir');
        }

    } catch (error) {
        console.error('Erro ao excluir câmera:', error);
        showToast('Erro ao excluir câmera: ' + (error as Error).message, 'danger');
    } finally {
        const btnConfirmar = document.getElementById('btnConfirmarExclusao') as HTMLButtonElement;
        btnConfirmar.innerHTML = '<i class="fas fa-trash me-1"></i>Excluir';
        btnConfirmar.disabled = false;
        currentCameraId = null;
    }
}

function mostrarErro(mensagem: string): void {
    const container = document.getElementById('listaCameras')!;
    container.innerHTML = `
        <div class="alert alert-danger" role="alert">
            ${escapeHtml(mensagem)}
        </div>
    `;
}

let currentCameraId: string | null = null;
let todasCameras: Array<Record<string, unknown>> = [];
let totalRecords: number = 0;
let totalPages: number = 1;

let lastFocusedTrigger: HTMLElement | null = null;

function initListarCameras(): void {
    if (!API_URL) {
        return;
    }

    function onReady(): void {
        carregarListaCameras(1);
        document.getElementById('btnAtualizarListaCameras')!.addEventListener('click', () => carregarListaCameras(currentPage));
        (document.getElementById('filtroStatus') as HTMLSelectElement).addEventListener('change', aplicarFiltros);
        (document.getElementById('filtroLocal') as HTMLSelectElement).addEventListener('change', aplicarFiltros);
        let debounceTimer: ReturnType<typeof setTimeout>;
        (document.getElementById('filtroPesquisa') as HTMLInputElement).addEventListener('keyup', function() {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(aplicarFiltros, 300);
        });
        document.getElementById('btnLimparFiltros')!.addEventListener('click', limparFiltros);

        document.getElementById('btnConfirmarExclusao')!.addEventListener('click', excluirCamera);
        document.getElementById('listaCameras')!.addEventListener('click', function(event: Event) {
            const actionButton = (event.target as HTMLElement).closest('[data-action]') as HTMLElement;
            if (!actionButton) return;

            const action = actionButton.getAttribute('data-action');
            if (action === 'limpar-filtros') {
                limparFiltros();
                return;
            }

            lastFocusedTrigger = actionButton;
            const cameraId = actionButton.getAttribute('data-camera-id');
            if (!cameraId) return;

            if (action === 'detalhes') {
                mostrarDetalhesCamera(cameraId);
                return;
            }

            if (action === 'excluir') {
                const cameraDescricao = actionButton.getAttribute('data-camera-descricao') || '';
                abrirModalExclusao(cameraId, cameraDescricao);
            }
        });

        function startAutoRefresh(): void {
            (window as any)._listarCamerasInterval = setInterval(() => carregarListaCameras(currentPage), 120000);
        }
        function stopAutoRefresh(): void {
            if ((window as any)._listarCamerasInterval) {
                clearInterval((window as any)._listarCamerasInterval);
                (window as any)._listarCamerasInterval = null;
            }
        }
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
                carregarListaCameras(currentPage);
            }
        });
        startAutoRefresh();
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
        document.getElementById('paginacaoCameras')!.addEventListener('click', function(event: Event) {
            const actionButton = (event.target as HTMLElement).closest('[data-action]') as HTMLElement;
            if (!actionButton) return;

            const action = actionButton.getAttribute('data-action');
            if (action === 'pagina-anterior' && currentPage > 1) {
                carregarListaCameras(currentPage - 1);
            }
            if (action === 'pagina-proxima' && currentPage < totalPages) {
                carregarListaCameras(currentPage + 1);
            }
        });

        const detalhesModalEl = document.getElementById('modalDetalhesCamera')!;
        detalhesModalEl.addEventListener('hide.bs.modal', function() {
            if (detalhesModalEl.contains(document.activeElement)) {
                (document.activeElement as HTMLElement).blur();
            }
        });
        detalhesModalEl.addEventListener('hidden.bs.modal', function() {
            if (lastFocusedTrigger && typeof lastFocusedTrigger.focus === 'function') {
                lastFocusedTrigger.focus();
            }
        });

        if ((window as any)._listarCamerasTooltips) {
            (window as any)._listarCamerasTooltips.forEach((t: any) => t.dispose());
        }
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        (window as any)._listarCamerasTooltips = tooltipTriggerList.map(function(tooltipTriggerEl: Element) {
            return new bootstrap.Tooltip(tooltipTriggerEl as HTMLElement);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
}

initListarCameras();

})();
export {};