import { escapeHtml } from './utils/html';

document.addEventListener('DOMContentLoaded', function (): void {
  const formFiltros = document.getElementById('formFiltros') as HTMLFormElement;
  const btnLimpar = document.getElementById('btnLimpar') as HTMLElement;
  const btnExportar = document.getElementById('btnExportar') as HTMLElement;
  const corpoTabela = document.getElementById('corpoTabela') as HTMLElement;
  const totalRegistros = document.getElementById('totalRegistros') as HTMLElement;
  const loadingOverlay = document.getElementById('loadingOverlay') as HTMLElement;
  const paginacao = document.getElementById('paginacao') as HTMLElement;

  const apiUrl: string = `${window.APP_API_BASE}api_relatorios_cameras`;
  const itensPorPagina: number = 50;
  const locaisList: Array<{id: number; nome: string}> = (window as any).LOCAIS_LIST || [];
  let paginaAtual: number = 1;
  let totalItens: number = 0;
  let _resizeObserver: ResizeObserver | null = null;
  let dadosAtuais: Array<Record<string, unknown>> = [];

  function mostrarLoading(mostrar: boolean): void {
    if (mostrar) {
      loadingOverlay.classList.remove('is-hidden');
    } else {
      loadingOverlay.classList.add('is-hidden');
    }
  }

  function formatarData(dataString: string): string {
    if (!dataString) return '';
    const data = new Date(dataString);
    return data.toLocaleDateString('pt-BR');
  }

  function getClasseStatus(statusNome: string): string {
    const statusLower = statusNome.toLowerCase();
    if (statusLower.includes('ativo') || statusLower.includes('funcionando')) {
      return 'status-ativo';
    } else if (statusLower.includes('inativo') || statusLower.includes('desligado')) {
      return 'status-inativo';
    } else if (statusLower.includes('manutenção') || statusLower.includes('manutencao')) {
      return 'status-manutencao';
    }
    return '';
  }

  function construirQueryString(filtros: Record<string, string>, pagina: number = 1): string {
    const params = new URLSearchParams();

    if (filtros.pesquisa) params.append('pesquisa', filtros.pesquisa);
    if (filtros.status) params.append('status', filtros.status);
    if (filtros.regiao) params.append('regiao', filtros.regiao);
    if (filtros.local) params.append('local', filtros.local);

    params.append('page_num', String(pagina));
    params.append('per_page', String(itensPorPagina));

    return params.toString();
  }

  let _buscando = false;
  async function buscarDados(pagina: number = 1): Promise<void> {
    if (_buscando) return;
    _buscando = true;
    try {
      paginaAtual = pagina;
      mostrarLoading(true);

      const filtros: Record<string, string> = {
        pesquisa: (document.getElementById('pesquisa') as HTMLInputElement).value,
        status: (document.getElementById('status') as HTMLSelectElement).value,
        regiao: (document.getElementById('regiao') as HTMLSelectElement).value,
        local: (document.getElementById('local_id') as HTMLInputElement).value
      };

      const queryString = construirQueryString(filtros, pagina);
      const timestamp = Date.now();
      const url = queryString ? `${apiUrl}&${queryString}&_t=${timestamp}` : `${apiUrl}?_t=${timestamp}`;

      const response = await fetchWithTimeout(url, { cache: 'no-store' });

      if (!response.ok) {
        throw new Error(`Erro HTTP: ${response.status}`);
      }

      const raw = await response.text();
      
      let data: Record<string, unknown> | null = null;
      try {
        data = JSON.parse(raw);
      } catch (_jsonError) {
        const isHtmlResponse = /^\s*</.test(raw);
        if (isHtmlResponse) {
          if (raw.includes('page=login') || raw.includes('login-title') || raw.includes('<!DOCTYPE html')) {
            window.location.href = `${window.BASE_URL}index.php?page=login`;
            return;
          }
          throw new Error('Servidor retornou HTML em vez de JSON. Verifique sessao/permissoes.');
        }
        throw new Error('Resposta invalida da API (JSON malformado).');
      }

      if (data && data.success) {
        dadosAtuais = Array.isArray(data.data) ? data.data as Array<Record<string, unknown>> : [];
        totalItens = data.pagination && (data.pagination as Record<string, unknown>).total != null ? Number((data.pagination as Record<string, unknown>).total) : dadosAtuais.length;

        atualizarTotalRegistros(totalItens);
        renderizarTabela(dadosAtuais);
        renderizarPaginacao();
      } else {
        throw new Error((data && (data.message as string || data.error as string)) || 'Erro desconhecido');
      }

    } catch (error) {
      console.error('Erro ao buscar dados:', error);
      mostrarErro('Erro ao carregar dados: ' + (error as Error).message);
    } finally {
      mostrarLoading(false);
      _buscando = false;
    }
  }

  function atualizarTotalRegistros(total: number): void {
    let texto = '';

    if (total === 0) {
      texto = '<i class="fas fa-exclamation-circle"></i> Nenhuma câmera encontrada com os filtros aplicados';
    } else if (total === 1) {
      texto = `<i class="fas fa-check-circle"></i> ${total} câmera encontrada`;
    } else {
      texto = `<i class="fas fa-check-circle"></i> ${total} câmeras encontradas`;
    }

    totalRegistros.innerHTML = texto;
  }

  function renderizarTabela(dados: Array<Record<string, unknown>>): void {
    if (dados.length === 0) {
      corpoTabela.innerHTML = `
                <tr>
                    <td colspan="28" class="no-results">
                        <i class="fas fa-search fa-3x"></i>
                        <h5 class="mt-3">Nenhuma câmera encontrada</h5>
                        <p class="text-muted">Tente ajustar os filtros de busca</p>
                    </td>
                </tr>
            `;
      return;
    }

    let html = '';

    dados.forEach((camera: Record<string, unknown>) => {
      const classeStatus = getClasseStatus(camera.status_nome as string);

      html += `
                <tr>
                    <td>${escapeHtml(camera.id)}</td>
                    <td>${escapeHtml(camera.descricao as string || '')}</td>
                    <td><code>${escapeHtml(camera.ip as string || '')}</code></td>
                    <td>${escapeHtml(camera.porta as string || '')}</td>
                    <td>${escapeHtml(camera.patrimonio as string || '')}</td>
                    <td><small class="text-muted">${escapeHtml(camera.serie_mac as string || '')}</small></td>
                    <td>${escapeHtml(camera.marca_nome as string || '')}</td>
                    <td>${escapeHtml(camera.modelo_nome as string || '')}</td>
                    <td>${escapeHtml(camera.tipo_camera_nome as string || '')}</td>
                    <td>${escapeHtml(camera.procedimento_nome as string || '')}</td>
                    <td>${escapeHtml(camera.regiao_nome as string || '')}</td>
                    <td>${escapeHtml(camera.local_nome as string || '')}</td>
                    <td>${escapeHtml(camera.secretaria_nome as string || '')}</td>
                    <td class="${escapeHtml(classeStatus)}">${escapeHtml(camera.status_nome as string || '')}</td>
                    <td>${escapeHtml(camera.alarme_conta as string || '')}</td>
                    <td>${escapeHtml(camera.transmissao_nome as string || '')}</td>
                    <td>${escapeHtml(camera.mosaico as string || '')}</td>
                    <td>${escapeHtml(camera.coordenadas as string || '')}</td>
                    <td>${escapeHtml(camera.tipo_logradouro as string || '')}</td>
                    <td>${escapeHtml(camera.local_logradouro as string || '')}</td>
                    <td>${escapeHtml(camera.local_numero as string || '')}</td>
                    <td>${escapeHtml(camera.local_bairro as string || '')}</td>
                    <td>${escapeHtml(camera.local_cidade as string || '')}</td>
                    <td>${escapeHtml(camera.local_uf as string || '')}</td>
                    <td>${escapeHtml(camera.local_cep as string || '')}</td>
                    <td>${escapeHtml(formatarData(camera.data_instalacao as string))}</td>
                    <td>${escapeHtml(formatarData(camera.created_at as string))}</td>
                    <td>
                        ${camera.observacao ? 
                            `<span title="${escapeHtml(String(camera.observacao))}">${escapeHtml(String(camera.observacao).substring(0, 30))}${String(camera.observacao).length > 30 ? '...' : ''}</span>` : 
                            '<span class="text-muted">-</span>'
                        }
                    </td>
                </tr>
            `;
    });

    corpoTabela.innerHTML = html;

    setupColumnSelector();
    setupTopScrollbar();
    aplicarVisibilidadeSalva();
  }

  function renderizarPaginacao(): void {
    if (totalItens <= itensPorPagina) {
      paginacao.classList.add('is-hidden');
      return;
    }

    const totalPaginas = Math.ceil(totalItens / itensPorPagina);
    let html = '';

    html += `
            <li class="page-item ${paginaAtual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-pagina="${paginaAtual - 1}">Anterior</a>
            </li>
        `;

    const maxPaginasVisiveis = 5;
    let inicio = Math.max(1, paginaAtual - Math.floor(maxPaginasVisiveis / 2));
    let fim = Math.min(totalPaginas, inicio + maxPaginasVisiveis - 1);

    if (fim - inicio + 1 < maxPaginasVisiveis) {
      inicio = Math.max(1, fim - maxPaginasVisiveis + 1);
    }

    for (let i = inicio; i <= fim; i++) {
      html += `
                <li class="page-item ${i === paginaAtual ? 'active' : ''}">
                    <a class="page-link" href="#" data-pagina="${i}">${i}</a>
                </li>
            `;
    }

    html += `
            <li class="page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}">
                <a class="page-link" href="#" data-pagina="${paginaAtual + 1}">Próximo</a>
            </li>
        `;

    paginacao.querySelector('ul')!.innerHTML = html;
    paginacao.classList.remove('is-hidden');
  }

  function mostrarErro(mensagem: string): void {
    corpoTabela.innerHTML = `
            <tr>
                <td colspan="28" class="text-center text-danger py-5">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
                    <h5>Erro ao carregar dados</h5>
                    <p class="text-muted">${mensagem}</p>
                    <button class="btn btn-sm btn-primary mt-2" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Tentar novamente
                    </button>
                </td>
            </tr>
        `;

    totalRegistros.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro na busca';
  }

  function limparFiltros(): void {
    (formFiltros as HTMLFormElement).reset();
    buscarDados();
  }

  function exportarDados(): void {
    if (dadosAtuais.length === 0) {
      window.showToast('Nao ha dados para exportar!', 'warning');
      return;
    }

    try {
      const headers = document.querySelectorAll('#tabelaResultados thead th');
      const visibleHeaders: string[] = [];
      let csv = '';

      headers.forEach(th => {
        if (!th.classList.contains('column-hidden')) {
          const label = (th as HTMLElement).textContent!.trim();
          visibleHeaders.push(label);
          csv += (csv ? ';' : '') + '"' + label.replace(/"/g, '""') + '"';
        }
      });
      csv += '\n';

      dadosAtuais.forEach(item => {
        let row = '';
        visibleHeaders.forEach(label => {
          const accessor = (CAMPO_POR_HEADER as Record<string, (d: Record<string, unknown>) => string>)[label];
          let val = accessor ? accessor(item) : '';
          row += (row ? ';' : '') + '"' + (typeof val === 'string' ? val.replace(/"/g, '""') : val) + '"';
        });
        csv += row + '\n';
      });

      const blob = new Blob(["\uFEFF" + csv], {
        type: 'text/csv;charset=utf-8;'
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');

      const dataAtual = new Date().toISOString().split('T')[0];
      link.href = url;
      link.setAttribute('download', `relatorio_cameras_${dataAtual}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      mostrarMensagemSucesso('Exportação iniciada! Verifique seus downloads.');

    } catch (error) {
      console.error('Erro ao exportar:', error);
      window.showToast('Erro ao exportar dados: ' + (error as Error).message, 'danger');
    }
  }

  function mostrarMensagemSucesso(mensagem: string): void {
    window.showToast(mensagem, 'success');
  }

  function setupColumnSelector(): void {
    const menu = document.getElementById('menuColunas');
    const thead = document.querySelector('#tabelaResultados thead tr');
    if (!menu || !thead) return;

    menu.innerHTML = '<li><div class="dropdown-item py-1 px-2 d-flex gap-2 border-bottom mb-1">'
      + '<a href="#" class="small" id="selecionarTodasColunas">Todas</a>'
      + ' <span class="text-muted">|</span> '
      + '<a href="#" class="small" id="limparTodasColunas">Nenhuma</a>'
      + '</div></li>';

    const headers = thead.querySelectorAll('th');
    headers.forEach((th, index) => {
      const label = (th as HTMLElement).textContent!.trim();
      const saved = JSON.parse(localStorage.getItem('colunasVisiveisCameras') || '{}') as Record<string, boolean>;
      const checked = saved[label] !== false;
      const item = document.createElement('li');
      item.innerHTML = '<div class="dropdown-item py-1 px-2">'
        + '<div class="form-check">'
        + '<input class="form-check-input toggle-coluna" type="checkbox" data-col-index="' + index + '" ' + (checked ? 'checked' : '') + '>'
        + '<label class="form-check-label small">' + label + '</label>'
        + '</div></div>';
      menu.appendChild(item);
    });
  }

  function toggleColuna(index: number, show: boolean): void {
    document.querySelectorAll('#tabelaResultados tr').forEach(row => {
      const cell = (row as HTMLElement).children[index];
      if (cell) cell.classList.toggle('column-hidden', !show);
    });
  }

  function salvarVisibilidade(): void {
    const headers = document.querySelectorAll('#tabelaResultados thead th');
    const state: Record<string, boolean> = {};
    headers.forEach((th, index) => {
      const label = (th as HTMLElement).textContent!.trim();
      state[label] = !th.classList.contains('column-hidden');
    });
    localStorage.setItem('colunasVisiveisCameras', JSON.stringify(state));
  }

  function aplicarVisibilidadeSalva(): void {
    const saved = JSON.parse(localStorage.getItem('colunasVisiveisCameras') || '{}') as Record<string, boolean>;
    if (Object.keys(saved).length === 0) return;
    const headers = document.querySelectorAll('#tabelaResultados thead th');
    headers.forEach((th, index) => {
      const label = (th as HTMLElement).textContent!.trim();
      if (saved[label] === false) {
        toggleColuna(index, false);
      }
    });
  }

  document.addEventListener('change', function (e: Event) {
    const target = e.target as HTMLElement;
    if (target.matches('.toggle-coluna')) {
      const cb = target as HTMLInputElement;
      const index = parseInt(cb.dataset.colIndex!);
      toggleColuna(index, cb.checked);
      salvarVisibilidade();
    }
  });

  document.addEventListener('click', function (e: Event) {
    const target = e.target as HTMLElement;
    const selecionarTodas = target.closest('#selecionarTodasColunas');
    if (selecionarTodas) {
      e.preventDefault();
      document.querySelectorAll('.toggle-coluna').forEach(cb => {
        (cb as HTMLInputElement).checked = true;
        toggleColuna(parseInt((cb as HTMLInputElement).dataset.colIndex!), true);
      });
      salvarVisibilidade();
      return;
    }
    const limparTodas = target.closest('#limparTodasColunas');
    if (limparTodas) {
      e.preventDefault();
      document.querySelectorAll('.toggle-coluna').forEach(cb => {
        (cb as HTMLInputElement).checked = false;
        toggleColuna(parseInt((cb as HTMLInputElement).dataset.colIndex!), false);
      });
      salvarVisibilidade();
    }
  });

  function setupTopScrollbar(): void {
    const container = document.querySelector('.table-responsive') as HTMLElement;
    if (!container) return;

    let wrapper = document.querySelector('.scrollbar-top-wrapper') as HTMLElement;
    if (wrapper) wrapper.remove();

    wrapper = document.createElement('div');
    wrapper.className = 'scrollbar-top-wrapper';

    const inner = document.createElement('div');
    inner.className = 'scrollbar-top-inner';
    wrapper.appendChild(inner);

    container.parentNode!.insertBefore(wrapper, container);

    function syncWidth(): void {
      const tableEl = container.querySelector('table') as HTMLElement;
      if (tableEl) {
        inner.style.width = tableEl.offsetWidth + 'px';
      }
    }

    syncWidth();

    wrapper.addEventListener('scroll', function (this: HTMLElement) {
      container.scrollLeft = this.scrollLeft;
    });

    container.addEventListener('scroll', function (this: HTMLElement) {
      wrapper.scrollLeft = this.scrollLeft;
    });

    if (_resizeObserver) _resizeObserver.disconnect();
    _resizeObserver = new ResizeObserver(syncWidth);
    const tableEl = container.querySelector('table');
    if (tableEl) _resizeObserver.observe(tableEl);
  }

  const CAMPO_POR_HEADER: Record<string, (d: Record<string, unknown>) => string> = {
    'ID': d => String(d.id || ''),
    'Descricao': d => (d.descricao as string || ''),
    'IP': d => (d.ip as string || ''),
    'Porta': d => (d.porta as string || ''),
    'Patrimonio': d => (d.patrimonio as string || ''),
    'Serie/MAC': d => (d.serie_mac as string || ''),
    'Marca': d => (d.marca_nome as string || ''),
    'Modelo': d => (d.modelo_nome as string || ''),
    'Tipo Camera': d => (d.tipo_camera_nome as string || ''),
    'Procedimento': d => (d.procedimento_nome as string || ''),
    'Regiao': d => (d.regiao_nome as string || ''),
    'Local': d => (d.local_nome as string || ''),
    'Secretaria': d => (d.secretaria_nome as string || ''),
    'Status': d => (d.status_nome as string || ''),
    'Conta Alarme': d => (d.alarme_conta as string || ''),
    'Transmissao': d => (d.transmissao_nome as string || ''),
    'Mosaico': d => (d.mosaico as string || ''),
    'Coordenadas': d => (d.coordenadas as string || ''),
    'Tipo': d => (d.tipo_logradouro as string || ''),
    'Logradouro': d => (d.local_logradouro as string || ''),
    'Numero': d => (d.local_numero as string || ''),
    'Bairro': d => (d.local_bairro as string || ''),
    'Cidade': d => (d.local_cidade as string || ''),
    'UF': d => (d.local_uf as string || ''),
    'CEP': d => (d.local_cep as string || ''),
    'Data Instalacao': d => formatarData(d.data_instalacao as string),
    'Criado em': d => formatarData(d.created_at as string),
    'Observacao': d => ((d.observacao as string) || '').replace(/"/g, '""')
  };

  formFiltros.addEventListener('submit', function (e: Event) {
    e.preventDefault();
    buscarDados();
  });

  btnLimpar.addEventListener('click', function () {
    limparFiltros();
  });

  btnExportar.addEventListener('click', function () {
    exportarDados();
  });

  paginacao.addEventListener('click', function (e: Event) {
    const target = e.target as HTMLElement;
    const link = target.closest('.page-link') as HTMLElement | null;
    if (link) {
      e.preventDefault();
      const pagina = parseInt(link.dataset.pagina!);
      if (pagina && !link.parentElement!.classList.contains('disabled')) {
        buscarDados(pagina);
      }
    }
  });

  const localInput = document.getElementById('local') as HTMLInputElement;
  const localHidden = document.getElementById('local_id') as HTMLInputElement;
  const localSuggestions = document.getElementById('localSuggestions') as HTMLElement;

  localInput.addEventListener('input', function () {
    const term = this.value.trim();
    if (term.length < 3) {
      localSuggestions.classList.add('d-none');
      return;
    }
    const lower = term.toLowerCase();
    const filtered = locaisList.filter(l => l.nome.toLowerCase().includes(lower));
    if (filtered.length === 0) {
      localSuggestions.classList.add('d-none');
      return;
    }
    localSuggestions.innerHTML = filtered.map(l =>
      `<button type="button" class="list-group-item list-group-item-action" data-id="${l.id}" data-nome="${escapeHtml(l.nome)}">${escapeHtml(l.nome)}</button>`
    ).join('');
    localSuggestions.classList.remove('d-none');
  });

  localSuggestions.addEventListener('click', function (e: Event) {
    const target = e.target as HTMLElement;
    const btn = target.closest('.list-group-item') as HTMLElement | null;
    if (!btn) return;
    localInput.value = btn.dataset.nome || '';
    localHidden.value = btn.dataset.id || '';
    localSuggestions.classList.add('d-none');
    buscarDados();
  });

  document.addEventListener('click', function (e: Event) {
    if (!(e.target as HTMLElement).closest('#local, #localSuggestions')) {
      localSuggestions.classList.add('d-none');
    }
  });

  localInput.addEventListener('blur', function () {
    setTimeout(() => {
      if (this.value.trim() === '') {
        localHidden.value = '';
      }
    }, 200);
  });

  // Clear hidden when user types (removes selection)
  localInput.addEventListener('keydown', function () {
    if (localHidden.value) {
      localHidden.value = '';
    }
  });

  ['status', 'regiao'].forEach(id => {
    document.getElementById(id)!.addEventListener('change', function () {
      buscarDados();
    });
  });

  function inicializar(): void {
    setupColumnSelector();
    setupTopScrollbar();
    
    buscarDados();
  }

  inicializar();

});
export {};
