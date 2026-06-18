$(document).ready(function (): void {
  const formFiltros = $('#formFiltros');
  const btnLimpar = $('#btnLimpar');
  const btnExportar = $('#btnExportar');
  const corpoTabela = $('#corpoTabela');
  const totalRegistros = $('#totalRegistros');
  const loadingOverlay = $('#loadingOverlay');
  const paginacao = $('#paginacao');

  const apiUrl: string = `${window.APP_API_BASE}api_alarmes`;
  const itensPorPagina: number = 50;
  let paginaAtual: number = 1;
  let totalItens: number = 0;
  let dadosAtuais: Array<Record<string, unknown>> = [];

  function mostrarLoading(mostrar: boolean): void {
    if (mostrar) {
      loadingOverlay.removeClass('is-hidden');
    } else {
      loadingOverlay.addClass('is-hidden');
    }
  }

  function escapeHtml(value: unknown): string {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatarData(dataString: string): string {
    if (!dataString) return '';
    const data = new Date(dataString);
    return data.toLocaleDateString('pt-BR');
  }

  function getClasseStatus(statusNome: string): string {
    const statusLower = String(statusNome || '').toLowerCase();
    if (statusLower.includes('ativo') || statusLower.includes('operante')) {
      return 'status-ativo';
    } else if (statusLower.includes('inativo') || statusLower.includes('desligado')) {
      return 'status-inativo';
    } else if (statusLower.includes('manutencao') || statusLower.includes('manutenção')) {
      return 'status-manutencao';
    }
    return '';
  }

  function construirQueryString(filtros: Record<string, string>, pagina: number = 1): string {
    const params = new URLSearchParams();

    if (filtros.status) params.append('status', filtros.status);
    if (filtros.regiao) params.append('regiao', filtros.regiao);
    if (filtros.conta) params.append('conta', filtros.conta);

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
        conta: $('#conta').val() as string,
        status: $('#status').val() as string,
        regiao: $('#regiao').val() as string
      };

      const queryString = construirQueryString(filtros, pagina);
      const url = queryString ? `${apiUrl}&${queryString}` : apiUrl;

      const response = await fetchWithTimeout(url, {
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

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
        totalItens = Number((data.pagination as Record<string, unknown>)?.total || 0);

        atualizarTotalRegistros(totalItens);
        renderizarTabela(dadosAtuais);
        renderizarPaginacao();

      } else {
        throw new Error((data && data.error as string) || 'Erro desconhecido');
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
      texto = '<i class="fas fa-exclamation-circle"></i> Nenhum alarme encontrado com os filtros aplicados';
    } else if (total === 1) {
      texto = `<i class="fas fa-check-circle"></i> ${total} alarme encontrado`;
    } else {
      texto = `<i class="fas fa-check-circle"></i> ${total} alarmes encontrados`;
    }

    totalRegistros.html(texto);
  }

  function renderizarTabela(dados: Array<Record<string, unknown>>): void {
    if (dados.length === 0) {
      corpoTabela.html(`
                <tr>
                    <td colspan="25" class="no-results">
                        <i class="fas fa-search fa-3x"></i>
                        <h5 class="mt-3">Nenhum alarme encontrado</h5>
                        <p class="text-muted">Tente ajustar os filtros de busca</p>
                    </td>
                </tr>
            `);
      return;
    }

    let html = '';

    dados.forEach(alarme => {
      const classeStatus = getClasseStatus(alarme.status as string);
      const cameraGm = alarme.camera_gm == 1 ? 'Sim' : (alarme.camera_gm == 0 ? 'Nao' : '');

      html += `
                <tr>
                    <td>${escapeHtml(alarme.id)}</td>
                    <td>${escapeHtml(alarme.conta as string || '')}</td>
                    <td class="${escapeHtml(classeStatus)}">${escapeHtml(alarme.status as string || '')}</td>
                    <td>${escapeHtml(alarme.regiao as string || '')}</td>
                    <td>${escapeHtml(alarme.local as string || '')}</td>
                    <td>${escapeHtml(alarme.endereco as string || '')}</td>
                    <td>${escapeHtml(alarme.numero as string || '')}</td>
                    <td><code>${escapeHtml(alarme.ip as string || '')}</code></td>
                    <td>${escapeHtml(alarme.pgm1 as string || '')}</td>
                    <td>${escapeHtml(alarme.pgm2 as string || '')}</td>
                    <td><small class="text-muted">${escapeHtml(alarme.mac as string || '')}</small></td>
                    <td>${escapeHtml(alarme.modelo_central as string || '')}</td>
                    <td>${escapeHtml(alarme.quant_repetidor as string || '')}</td>
                    <td>${escapeHtml(alarme.qtde_sensores as string || '')}</td>
                    <td><code>${escapeHtml(alarme.ip_dvr as string || '')}</code></td>
                    <td>${escapeHtml(alarme.cameras_dvr as string || '')}</td>
                    <td>${escapeHtml(cameraGm)}</td>
                    <td>${escapeHtml(alarme.quant_camera_gm as string || '')}</td>
                    <td>${escapeHtml(formatarData(alarme.integracao as string))}</td>
                    <td>${escapeHtml(alarme.documentacao as string || '')}</td>
                    <td>${escapeHtml(alarme.monitorada as string || '')}</td>
                    <td>${escapeHtml(alarme.numero_sei as string || '')}</td>
                    <td>${escapeHtml(formatarData(alarme.data_atualizacao as string))}</td>
                    <td>${escapeHtml(formatarData(alarme.created_at as string))}</td>
                    <td>
                        ${alarme.observacao ?
                            `<span title="${escapeHtml(String(alarme.observacao))}">${escapeHtml(String(alarme.observacao).substring(0, 30))}${String(alarme.observacao).length > 30 ? '...' : ''}</span>` :
                            '<span class="text-muted">-</span>'
                        }
                    </td>
                </tr>
            `;
    });

    corpoTabela.html(html);

    setupTopScrollbar();
    setupColumnSelector();
    aplicarVisibilidadeSalva();
  }

  function renderizarPaginacao(): void {
    if (totalItens <= itensPorPagina) {
      paginacao.addClass('is-hidden');
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
                <a class="page-link" href="#" data-pagina="${paginaAtual + 1}">Proximo</a>
            </li>
        `;

    paginacao.find('ul').html(html);
    paginacao.removeClass('is-hidden');
  }

  function mostrarErro(mensagem: string): void {
    corpoTabela.html(`
            <tr>
                <td colspan="25" class="text-center text-danger py-5">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
                    <h5>Erro ao carregar dados</h5>
                    <p class="text-muted">${mensagem}</p>
                    <button class="btn btn-sm btn-primary mt-2" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Tentar novamente
                    </button>
                </td>
            </tr>
        `);

    totalRegistros.html('<i class="fas fa-exclamation-triangle"></i> Erro na busca');
  }

  function limparFiltros(): void {
    (formFiltros[0] as HTMLFormElement).reset();
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
          csv += (csv ? ';' : '') + label;
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
      link.setAttribute('download', `relatorio_alarmes_${dataAtual}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      mostrarMensagemSucesso('Exportacao iniciada! Verifique seus downloads.');
    } catch (error) {
      console.error('Erro ao exportar:', error);
      window.showToast('Erro ao exportar dados: ' + (error as Error).message, 'danger');
    }
  }

  function mostrarMensagemSucesso(mensagem: string): void {
    window.showToast(mensagem, 'success');
  }

  function carregarFiltrosSalvos(): void {
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
      const saved = JSON.parse(localStorage.getItem('colunasVisiveisAlarmes') || '{}') as Record<string, boolean>;
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
    localStorage.setItem('colunasVisiveisAlarmes', JSON.stringify(state));
  }

  function aplicarVisibilidadeSalva(): void {
    const saved = JSON.parse(localStorage.getItem('colunasVisiveisAlarmes') || '{}') as Record<string, boolean>;
    if (Object.keys(saved).length === 0) return;
    const headers = document.querySelectorAll('#tabelaResultados thead th');
    headers.forEach((th, index) => {
      const label = (th as HTMLElement).textContent!.trim();
      if (saved[label] === false) {
        toggleColuna(index, false);
      }
    });
  }

  $(document).on('change', '.toggle-coluna', function (this: HTMLElement) {
    const index = parseInt($(this).data('col-index') as string);
    toggleColuna(index, this.checked);
    salvarVisibilidade();
  });

  $(document).on('click', '#selecionarTodasColunas', function (e: Event) {
    e.preventDefault();
    document.querySelectorAll('.toggle-coluna').forEach(cb => {
      (cb as HTMLInputElement).checked = true;
      toggleColuna(parseInt((cb as HTMLInputElement).dataset.colIndex!), true);
    });
    salvarVisibilidade();
  });

  $(document).on('click', '#limparTodasColunas', function (e: Event) {
    e.preventDefault();
    document.querySelectorAll('.toggle-coluna').forEach(cb => {
      (cb as HTMLInputElement).checked = false;
      toggleColuna(parseInt((cb as HTMLInputElement).dataset.colIndex!), false);
    });
    salvarVisibilidade();
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

    const observer = new ResizeObserver(syncWidth);
    const tableEl = container.querySelector('table');
    if (tableEl) observer.observe(tableEl);
  }

  const CAMPO_POR_HEADER: Record<string, (d: Record<string, unknown>) => string> = {
    'ID': d => String(d.id || ''),
    'Conta': d => (d.conta as string || ''),
    'Status': d => (d.status as string || ''),
    'Regiao': d => (d.regiao as string || ''),
    'Local': d => (d.local as string || ''),
    'Endereco': d => (d.endereco as string || ''),
    'Numero': d => (d.numero as string || ''),
    'IP': d => (d.ip as string || ''),
    'PGM1': d => (d.pgm1 as string || ''),
    'PGM2': d => (d.pgm2 as string || ''),
    'MAC': d => (d.mac as string || ''),
    'Modelo Central': d => (d.modelo_central as string || ''),
    'Qtd Repetidor': d => (d.quant_repetidor as string || ''),
    'Qtd Sensores': d => (d.qtde_sensores as string || ''),
    'IP DVR': d => (d.ip_dvr as string || ''),
    'Cameras DVR': d => (d.cameras_dvr as string || ''),
    'Camera GM': d => (d.camera_gm == 1 ? 'Sim' : (d.camera_gm == 0 ? 'Nao' : '')),
    'Qtd Camera GM': d => (d.quant_camera_gm as string || ''),
    'Integracao': d => formatarData(d.integracao as string),
    'Documentacao': d => (d.documentacao as string || ''),
    'Monitorada': d => (d.monitorada as string || ''),
    'Numero SEI': d => (d.numero_sei as string || ''),
    'Data Atualizacao': d => formatarData(d.data_atualizacao as string),
    'Criado em': d => formatarData(d.created_at as string),
    'Observacao': d => ((d.observacao as string) || '').replace(/"/g, '""')
  };

  formFiltros.on('submit', function (e: Event) {
    e.preventDefault();
    buscarDados();
  });

  btnLimpar.on('click', function () {
    limparFiltros();
  });

  btnExportar.on('click', function () {
    exportarDados();
  });

  paginacao.on('click', '.page-link', function (e: Event) {
    e.preventDefault();
    const pagina = $(this).data('pagina') as number;
    if (pagina && !$(this).parent().hasClass('disabled')) {
      buscarDados(pagina);
    }
  });

  $('#status, #regiao').on('change', function () {
    buscarDados();
  });

  function inicializar(): void {
    setupColumnSelector();
    setupTopScrollbar();
  }

  inicializar();

});
