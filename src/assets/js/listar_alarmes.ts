import { escapeHtml } from './utils/html';

(function() {
'use strict';

const listarAlarmesConfigEl = document.getElementById('listarAlarmesConfig') as HTMLElement;
const API_URL: string = listarAlarmesConfigEl?.dataset.apiUrl || '';
const PER_PAGE = (window as any).PER_PAGE || 10;
let currentPage = (window as any).currentPage || 1;

function getFiltros(): { status: string; regiao: string; pesquisa: string } {
    const status = (document.getElementById('filtroStatus') as HTMLSelectElement).value;
    const regiao = (document.getElementById('filtroRegiao') as HTMLInputElement).value.trim();
    const pesquisa = (document.getElementById('filtroPesquisa') as HTMLInputElement).value.trim();

    return { status, regiao, pesquisa };
}

function buildQueryParams(page: number): URLSearchParams {
    const filtros = getFiltros();
    const params = new URLSearchParams();
    params.set('page_num', String(page));
    params.set('per_page', String(PER_PAGE));

    if (filtros.status) {
        params.set('status', filtros.status);
    }
    if (filtros.regiao) {
        params.set('regiao', filtros.regiao);
    }
    if (filtros.pesquisa) {
        params.set('busca', filtros.pesquisa);
    }

    return params;
}

let listController: AbortController | null = null;

async function carregarListaAlarmes(page: number = 1): Promise<void> {
    try {
        currentPage = page;
        const container = document.getElementById("listaAlarmes")!;
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                <p>Carregando alarmes...</p>
            </div>
        `;

        if (listController) {
            listController.abort();
        }
        listController = new AbortController();

        const params = buildQueryParams(page);
        const response = await fetchWithTimeout(`${API_URL}&${params.toString()}`, {
            signal: listController.signal
        });

        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }

        const resultado = await response.json();

        if (resultado.success && Array.isArray(resultado.data)) {
            todosAlarmes = resultado.data;
            totalRecords = Number(resultado.pagination?.total || 0);
            totalPages = Number(resultado.pagination?.total_pages || 1);
            if (totalPages > 0 && currentPage > totalPages) {
                carregarListaAlarmes(totalPages);
                return;
            }
            if (todosAlarmes.length === 0 && totalRecords > 0 && currentPage > 1) {
                carregarListaAlarmes(currentPage - 1);
                return;
            }
            exibirAlarmes(todosAlarmes);
            renderPaginacao();

            showToast(totalRecords + ' alarmes encontrados', 'success');
        } else {
            throw new Error(resultado.error || 'Formato de dados invalido');
        }

    } catch (error) {
        console.error('Erro ao carregar alarmes:', error);
        mostrarErro('Erro ao carregar alarmes: ' + (error as Error).message);
    }
}

function mostrarDetalhesAlarme(alarmeId: string): void {
    const alarme = todosAlarmes.find(a => String(a.id) === String(alarmeId));
    if (!alarme) return;

    const detalhesContent = document.getElementById('detalhesAlarmeContent')!;
    const id = escapeHtml(String(alarme.id || 'N/A'));
    const conta = escapeHtml(String(alarme.conta || ''));
    const status = escapeHtml((alarme.status as string) || 'Nao informado');
    const regiao = escapeHtml((alarme.regiao as string) || 'Nao informado');
    const local = escapeHtml((alarme.local as string) || 'Nao informado');
    const endereco = escapeHtml((alarme.endereco as string) || 'Nao informado');
    const numero = escapeHtml(String(alarme.numero || ''));
    const ip = escapeHtml((alarme.ip as string) || 'Nao informado');
    const numeroSei = escapeHtml(String(alarme.numero_sei || ''));
    const observacao = alarme.observacao ? escapeHtml(String(alarme.observacao)) : '';

    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="border-bottom pb-2">Identificacao</h6>
                <p><strong>ID:</strong> ${id}</p>
                <p><strong>Conta:</strong> ${conta}</p>
                <p><strong>Status:</strong> <span class="badge ${getStatusBadgeClass(alarme.status as string)}">${status}</span></p>
                <p><strong>Regiao:</strong> ${regiao}</p>
            </div>
            <div class="col-md-6">
                <h6 class="border-bottom pb-2">Localizacao</h6>
                <p><strong>Local:</strong> ${local}</p>
                <p><strong>Endereco:</strong> ${endereco}, ${numero}</p>
                <p><strong>IP:</strong> <code>${ip}</code></p>
                <p><strong>Numero SEI:</strong> ${numeroSei}</p>
            </div>
        </div>
    `;

    if (observacao) {
        html += `
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="border-bottom pb-2">Observacoes</h6>
                    <p>${observacao}</p>
                </div>
            </div>
        `;
    }

    detalhesContent.innerHTML = html;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhesAlarme')!);
    modal.show();
}

function formatarData(dataString: string): string {
    if (!dataString) return '';
    const data = new Date(dataString);
    return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR');
}

function getStatusBadgeClass(statusNome: string): string {
    const s = statusNome ? statusNome.toLowerCase() : '';
    if (s.includes('ativo') || s.includes('operante')) {
        return 'bg-success';
    } else if (s.includes('inativo') || s.includes('desligado')) {
        return 'bg-danger';
    } else if (s.includes('manutencao') || s.includes('manutenção')) {
        return 'bg-warning';
    }
    return 'bg-secondary';
}

function aplicarFiltros(): void {
    carregarListaAlarmes(1);
}

function exibirAlarmes(alarmes: Array<Record<string, unknown>>): void {
    const container = document.getElementById('listaAlarmes')!;
    const contador = totalRecords || alarmes.length;
    document.getElementById('contadorAlarmes')!.textContent = String(contador);

    if (alarmes.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fas fa-bell-slash fa-2x mb-2"></i>
                <p>Nenhum alarme encontrado</p>
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
                        <th scope="col">Conta</th>
                        <th scope="col">Status</th>
                        <th scope="col">Regiao</th>
                        <th scope="col">Local</th>
                        <th scope="col">Endereco</th>
                        <th scope="col">IP</th>
                        <th scope="col">Numero SEI</th>
                        <th scope="col" class="text-center">Acoes</th>
                    </tr>
                </thead>
                <tbody>
    `;

    alarmes.forEach(alarme => {
        const idRaw = String(alarme.id || '').trim();
        if (!idRaw) return;

        const id = escapeHtml(idRaw);
        const idAttr = escapeHtml(idRaw);
        const conta = escapeHtml(String(alarme.conta || ''));
        const status = escapeHtml((alarme.status as string) || 'Desconhecido');
        const regiao = escapeHtml((alarme.regiao as string) || '');
        const local = escapeHtml((alarme.local as string) || '');
        const endereco = escapeHtml((alarme.endereco as string) || '');
        const ip = escapeHtml((alarme.ip as string) || '');
        const numeroSei = escapeHtml(String(alarme.numero_sei || ''));
        const observacao = alarme.observacao ? escapeHtml(String(alarme.observacao).substring(0, 50)) : '';
        const observacaoSufixo = alarme.observacao && String(alarme.observacao).length > 50 ? '...' : '';
        const statusBadge = getStatusBadgeClass(alarme.status as string);

        html += `
            <tr>
                <td><span class="badge bg-secondary">${id}</span></td>
                <td><strong>${conta}</strong></td>
                <td><span class="badge ${statusBadge}">${status}</span></td>
                <td>${regiao}</td>
                <td>
                    <div class="fw-bold">${local}</div>
                    ${observacao ? `<small class="text-muted d-block">${observacao}${observacaoSufixo}</small>` : ''}
                </td>
                <td>${endereco}</td>
                <td><code class="text-primary">${ip}</code></td>
                <td>${numeroSei}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-info py-1 px-2"
                            data-action="detalhes"
                            data-alarme-id="${idAttr}"
                            title="Ver detalhes"
                            data-bs-toggle="tooltip">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a href="?page=editar_alarmes&id=${encodeURIComponent(idRaw)}" class="btn btn-primary py-1 px-2"
                            title="Editar alarme" data-bs-toggle="tooltip">
                            <i class="fas fa-edit"></i>
                        </a>
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
            <small class="text-muted">Mostrando ${alarmes.length} de ${contador} alarme(s)</small>
        </div>
    `;

    container.innerHTML = html;

    if ((window as any)._listarAlarmesTooltips) {
        (window as any)._listarAlarmesTooltips.forEach((t: any) => t.dispose());
    }
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    (window as any)._listarAlarmesTooltips = tooltipTriggerList.map(function(tooltipTriggerEl: Element) {
        return new bootstrap.Tooltip(tooltipTriggerEl as HTMLElement);
    });
}

function limparFiltros(): void {
    (document.getElementById('filtroStatus') as HTMLSelectElement).value = '';
    (document.getElementById('filtroRegiao') as HTMLInputElement).value = '';
    (document.getElementById('filtroPesquisa') as HTMLInputElement).value = '';
    carregarListaAlarmes(1);
}

function renderPaginacao(): void {
    const paginacao = document.getElementById('paginacaoAlarmes');
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
            <span class="text-muted small">Pagina ${currentPage} de ${totalPages}</span>
            <button class="btn btn-outline-secondary btn-sm" data-action="pagina-proxima" ${nextDisabled}>
                Proxima<i class="fas fa-chevron-right ms-1"></i>
            </button>
        </div>
    `;
}

function mostrarErro(mensagem: string): void {
    const container = document.getElementById('listaAlarmes')!;
    container.innerHTML = `
        <div class="alert alert-danger" role="alert">
            ${escapeHtml(mensagem)}
        </div>
    `;
}

let todosAlarmes: Array<Record<string, unknown>> = [];
let totalRecords: number = 0;
let totalPages: number = 1;

let lastFocusedTrigger: HTMLElement | null = null;

function initListarAlarmes(): void {
    if (!API_URL) {
        return;
    }

    function onReady(): void {
        carregarListaAlarmes(1);
        document.getElementById('btnAtualizarListaAlarmes')!.addEventListener('click', () => carregarListaAlarmes(currentPage));
        (document.getElementById('filtroStatus') as HTMLSelectElement).addEventListener('change', aplicarFiltros);
        (document.getElementById('filtroRegiao') as HTMLSelectElement).addEventListener('change', aplicarFiltros);
        let debounceTimer: ReturnType<typeof setTimeout>;
        (document.getElementById('filtroPesquisa') as HTMLInputElement).addEventListener('keyup', function() {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(aplicarFiltros, 300);
        });
        document.getElementById('btnLimparFiltros')!.addEventListener('click', limparFiltros);

        document.getElementById('listaAlarmes')!.addEventListener('click', function(event: Event) {
            const actionButton = (event.target as HTMLElement).closest('[data-action]') as HTMLElement;
            if (!actionButton) return;

            const action = actionButton.getAttribute('data-action');
            if (action === 'limpar-filtros') {
                limparFiltros();
                return;
            }

            lastFocusedTrigger = actionButton;
            const alarmeId = actionButton.getAttribute('data-alarme-id');
            if (!alarmeId) return;

            if (action === 'detalhes') {
                mostrarDetalhesAlarme(alarmeId);
                return;
            }
        });

        (window as any)._listarAlarmesInterval = setInterval(() => carregarListaAlarmes(currentPage), 120000);
        window.addEventListener('beforeunload', function() {
            clearInterval((window as any)._listarAlarmesInterval);
        });
        document.getElementById('paginacaoAlarmes')!.addEventListener('click', function(event: Event) {
            const actionButton = (event.target as HTMLElement).closest('[data-action]') as HTMLElement;
            if (!actionButton) return;

            const action = actionButton.getAttribute('data-action');
            if (action === 'pagina-anterior' && currentPage > 1) {
                carregarListaAlarmes(currentPage - 1);
            }
            if (action === 'pagina-proxima' && currentPage < totalPages) {
                carregarListaAlarmes(currentPage + 1);
            }
        });

        const detalhesModalEl = document.getElementById('modalDetalhesAlarme')!;
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

        if ((window as any)._listarAlarmesTooltips) {
            (window as any)._listarAlarmesTooltips.forEach((t: any) => t.dispose());
        }
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        (window as any)._listarAlarmesTooltips = tooltipTriggerList.map(function(tooltipTriggerEl: Element) {
            return new bootstrap.Tooltip(tooltipTriggerEl as HTMLElement);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }
}

initListarAlarmes();

})();
export {};