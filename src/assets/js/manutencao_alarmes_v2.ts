// Versão Otimizada do JavaScript para Manutenção de Alarmes
// Versão 2.0 com módulos centralizados e performance melhorada

(function () {
  const errorHandler = window.ErrorHandler;
  errorHandler.options.enableAlerts = false;
  const loadingManager = window.LoadingManager;

  const state = {
    alarmeSelecionada: '',
    historico: [] as Array<Record<string, unknown>>,
    pendingOrders: [] as Array<Record<string, unknown>>,
    executingOrders: [] as Array<Record<string, unknown>>,
    currentOsId: null as string | null,
    currentOsProblemas: '',
    currentOsNumeroOs: '',
    historicoBusca: '',
    historicoPage: 1,
    historicoPerPage: 20,
    historicoSortBy: 'data_hora',
    historicoSortDir: 'desc',
    dataInicial: '',
    dataFinal: '',
    listsLoaded: false,
    alarmeBusca: '',
    alarmeOptions: [] as Array<Record<string, unknown>>,
    selectedHistoricoIds: new Set<string>(),
    selectedHistoricoRows: {} as Record<string, unknown>,
    currentPagination: {} as Record<string, unknown>,
    defaultProcedimentoId: '',
    _fetching: false
  };

  function getApiBase() {
    return window.APP_API_BASE;
  }

  function showLoading(message = 'Carregando...') {
    const loadingEl = document.getElementById('globalLoadingAlert');
    if (loadingEl) {
      const messageEl = loadingEl.querySelector('#loadingMessage') as HTMLElement;
      messageEl.textContent = message;
      loadingEl.classList.remove('d-none');
    }
  }

  function hideLoading() {
    const loadingEl = document.getElementById('globalLoadingAlert');
    if (loadingEl) loadingEl.classList.add('d-none');
  }

  const debouncedFetchDados = window.debounce(async () => {
    try { await fetchDados(); } catch (error) { errorHandler.handle(error, 'Debounced fetch failed', 'warning'); showMessage('danger', 'Erro ao carregar dados: ' + (error as Error).message); }
  }, 300);

  async function loadAlarmeOptions(term = '', targetSelectId: string | null = null) {
    const selectIds = targetSelectId ? [targetSelectId] : ['equipamento_id', 'equipamento_id_os'];
    const firstSelect = document.getElementById(selectIds[0]) as HTMLSelectElement | null;
    if (!firstSelect) return;
    const loading = loadingManager.showElement(firstSelect, 'Carregando alarmes...');

    selectIds.forEach((id) => {
      const sel = document.getElementById(id) as HTMLSelectElement | null;
      if (sel) sel.innerHTML = '<option value="">Carregando alarmes...</option>';
    });

    try {
      const qs = new URLSearchParams();
      qs.set('page_num', '1');
      qs.set('per_page', '50');
      if (term) qs.set('busca', term);

      const response = await fetchWithTimeout(`${getApiBase()}api_alarmes&${qs.toString()}`, {
        headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store'
      });

      const payload = await response.json() as ApiResponse<Array<Record<string, unknown>>>;
      if (!response.ok || !payload.success) throw new Error(payload.error || 'Erro ao carregar alarmes');

      const list = Array.isArray(payload.data) ? payload.data : [];
      renderAlarmes(list, selectIds);

      if (state.alarmeSelecionada && !targetSelectId) {
        selectIds.forEach((id) => {
          const sel = document.getElementById(id) as HTMLSelectElement | null;
          if (sel) ensureSelectOption(sel, String(state.alarmeSelecionada), `Alarme ${state.alarmeSelecionada}`);
        });
      }
    } catch (e) {
      console.error('Erro ao carregar alarmes:', e);
      selectIds.forEach((id) => {
        const sel = document.getElementById(id) as HTMLSelectElement | null;
        if (sel) sel.innerHTML = '<option value="">Erro ao carregar alarmes</option>';
      });
      errorHandler.handle(e, 'Failed to load alarm options', 'error');
    } finally {
      loading?.hide();
    }
  }

  function renderAlarmes(alarmes: Array<Record<string, unknown>>, targetSelectIds: string[] | null = null) {
    state.alarmeOptions = Array.isArray(alarmes) ? alarmes : [];
    const selectIds = targetSelectIds || ['equipamento_id', 'equipamento_id_os'];
    const options = (alarmes || []).map((alarme) => {
      const alarmeId = alarme.equipamento_id || alarme.id;
      const localName = (alarme.local_nome as string) || (alarme.descricao as string) || (alarme.local as string) || `ALARME ${alarmeId}`;
      const label = alarme.ip ? `${localName} - ${alarme.ip}` : localName;
      return `<option value="${escapeHtml(alarmeId)}">${escapeHtml(label)}</option>`;
    }).join('');

    selectIds.forEach((selectId) => {
      const select = document.getElementById(selectId) as HTMLSelectElement | null;
      if (!select) return;
      select.innerHTML = '<option value="">Selecione um alarme...</option>' + options;
    });
  }

  function renderPendingOrders(orders: Array<Record<string, unknown>>) {
    const tbody = document.getElementById('ordensCadastradasBody');
    if (!tbody) return;
    state.pendingOrders = Array.isArray(orders) ? orders : [];

    if (state.pendingOrders.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma ordem de serviço cadastrada.</td></tr>';
      return;
    }

    tbody.innerHTML = state.pendingOrders.map((item) => {
      const itemId = String(item.id || '');
      return `
      <tr>
        <td>${escapeHtml(item.numero_os || '-')}</td>
        <td>${escapeHtml((item.alarme_nome as string) || `ID ${item.alarme_id || '-'}`)}</td>
        <td>${escapeHtml(item.ip || '-')}</td>
        <td>${escapeHtml(toBrDate(item.data_hora || item.created_at))}</td>
        <td>${escapeHtml(item.problemas || '-')}</td>
        <td>
          <button type="button" class="btn btn-sm btn-outline-primary btn-start-os" data-id="${escapeHtml(itemId)}">
            <i class="fas fa-play me-1"></i>Iniciar execução
          </button>
        </td>
      </tr>
    `;
    }).join('');

    tbody.querySelectorAll('.btn-start-os').forEach((button) => {
      button.addEventListener('click', async () => {
        const id = String((button as HTMLElement).getAttribute('data-id') || '');
        if (!id) return;
        await iniciarExecucaoOs(id);
      });
    });
  }

  function renderExecutingOrders(orders: Array<Record<string, unknown>>) {
    const tbody = document.getElementById('ordensExecutandoBody');
    if (!tbody) return;
    state.executingOrders = Array.isArray(orders) ? orders : [];

    if (state.executingOrders.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhuma ordem de serviço em execução.</td></tr>';
      return;
    }

    tbody.innerHTML = state.executingOrders.map((item) => {
      const itemId = String(item.id || '');
      return `
      <tr>
        <td>${escapeHtml(item.numero_os || '-')}</td>
        <td>${escapeHtml((item.alarme_nome as string) || `ID ${item.alarme_id || '-'}`)}</td>
        <td>${escapeHtml(item.ip || '-')}</td>
        <td>${escapeHtml(toBrDate(item.data_hora || item.created_at))}</td>
        <td>${escapeHtml(item.problemas || '-')}</td>
        <td>
          <button type="button" class="btn btn-sm btn-outline-success btn-finalizar-os" data-id="${escapeHtml(itemId)}">
            <i class="fas fa-flag-checkered me-1"></i>Finalizar
          </button>
        </td>
      </tr>
    `;
    }).join('');

    tbody.querySelectorAll('.btn-finalizar-os').forEach((button) => {
      button.addEventListener('click', async () => {
        const id = String((button as HTMLElement).getAttribute('data-id') || '');
        if (!id) return;
        try { await selectOrdemServico(id); } catch (error) { showMessage('danger', 'Erro ao selecionar OS: ' + ((error as Error).message || 'erro desconhecido')); }
      });
    });
  }

  function renderHistorico(historico: Array<Record<string, unknown>>, pagination: Record<string, unknown>) {
    const tbody = document.getElementById('historicoManutencaoBody');
    const resumo = document.getElementById('resumoHistorico');
    if (!tbody || !resumo) return;

    state.historico = Array.isArray(historico) ? historico : [];
    state.currentPagination = pagination || {};
    const total = Number((pagination as any)?.total || 0);
    const page = Number((pagination as any)?.page || state.historicoPage);
    const totalPages = Number((pagination as any)?.total_pages || 1);
    resumo.textContent = `Total: ${total} | Página ${page} de ${totalPages}`;

    if (state.historico.length === 0) {
      tbody.innerHTML = '<tr><td colspan="15" class="text-center text-muted py-4">Nenhuma manutenção registrada.</td></tr>';
      updateSelectedCount(state.selectedHistoricoIds);
      updateSelectAllCheckbox(state.selectedHistoricoIds, state.historico);
      return;
    }

    tbody.innerHTML = state.historico.map((item) => {
      const itemId = String(item.id || '');
      const selected = state.selectedHistoricoIds.has(itemId);
      const dateValue = item.data_execucao || item.data_hora || item.created_at;
      return `
      <tr>
        <td class="align-middle text-center">
          <input type="checkbox" class="form-check-input historico-select-checkbox" data-id="${escapeHtml(itemId)}" ${selected ? 'checked' : ''}>
        </td>
        <td>${escapeHtml(item.numero_os || '-')}</td>
        <td><small>${escapeHtml(toDateOnlyBr(dateValue))}</small></td>
        <td><small>${escapeHtml(toTimeOnlyBr(dateValue))}</small></td>
        <td>${escapeHtml((item.conta as string) || (item.alarme_nome as string) || `ID ${item.alarme_id || '-'}`)}</td>
        <td>${escapeHtml(item.ip || '-')}</td>
        <td>${escapeHtml(item.modelo_nome || '-')}</td>
        <td>${escapeHtml(item.local_servico || '-')}</td>
        <td>${escapeHtml(item.endereco_servico || '-')}</td>
        <td>${escapeHtml(item.procedimento_nome || '-')}</td>
        <td>${escapeHtml(item.status_nome || '-')}</td>
        <td>${escapeHtml(item.tecnico || '-')}</td>
        <td class="td-min-320">${escapeHtml(item.descricao || '-')}</td>
        <td>${escapeHtml(item.pecas_previstas || '-')}</td>
        <td>${escapeHtml(item.usuario_nome || 'Sistema')}</td>
      </tr>
    `;
    }).join('');

    tbody.querySelectorAll('.historico-select-checkbox').forEach((checkbox) => {
      checkbox.addEventListener('change', () => {
        const id = String((checkbox as HTMLElement).getAttribute('data-id') || '');
        if (!id) return;
        const item = state.historico.find((row) => String(row.id || '') === id);
        if ((checkbox as HTMLInputElement).checked) {
          state.selectedHistoricoIds.add(id);
          if (item) state.selectedHistoricoRows[id] = item;
        } else {
          state.selectedHistoricoIds.delete(id);
          delete state.selectedHistoricoRows[id];
        }
        updateSelectedCount(state.selectedHistoricoIds);
        updateSelectAllCheckbox(state.selectedHistoricoIds, state.historico);
      });
    });

    updateSelectedCount(state.selectedHistoricoIds);
    updateSelectAllCheckbox(state.selectedHistoricoIds, state.historico);
  }

  async function iniciarExecucaoOs(osId: string) {
    const ordem = state.pendingOrders.find((item) => String(item.id || '') === osId);
    if (!ordem) return;

    clearMessage();
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content') || (window as any).CSRF_TOKEN || '';

    const payload = { action: 'start_os', os_id: String(ordem.id || '').trim(), alarme_id: String(ordem.alarme_id || '').trim() };

    try {
      showLoading('Iniciando execução...');
      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_alarmes`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(payload)
      });
      const result = await response.json() as ApiResponse<unknown>;
      if (!response.ok || !result.success) throw new Error(result.error || 'Erro ao iniciar execução da OS.');
      showMessage('success', 'Ordem de serviço em execução.');
      await fetchDados();
    } catch (error) {
      errorHandler.handle(error, 'Failed to start OS execution', 'error');
      showMessage('danger', (error as Error).message || 'Erro ao iniciar execução da OS.');
    } finally { hideLoading(); }
  }

  async function salvarManutencao(event: Event) {
    event.preventDefault();
    clearMessage();

    if (!state.currentOsId) { showMessage('warning', 'Selecione uma ordem de serviço em execução para finalizar.'); return; }

    const form = event.currentTarget as HTMLFormElement;
    const btn = document.getElementById('btnSalvarManutencao') as HTMLButtonElement;
    const formData = new FormData(form);
    const payload: Record<string, string> = {
      action: 'finalize_os',
      os_id: String(formData.get('os_id') || '').trim(),
      alarme_id: String(formData.get('equipamento_id') || '').trim(),
      data_hora: String(formData.get('data_hora') || '').trim(),
      procedimento_id: String(formData.get('procedimento_id') || '').trim(),
      status_id: String(formData.get('status_id') || '').trim(),
      tecnico: String(formData.get('tecnico') || '').trim(),
      numero_os: String(formData.get('numero_os') || '').trim(),
      local_servico: String(formData.get('local_servico') || '').trim(),
      endereco_servico: String(formData.get('endereco_servico') || '').trim(),
      descricao: String(formData.get('descricao') || '').trim(),
      pecas_previstas: String(formData.get('pecas_previstas') || '').trim()
    };

    if (!payload.alarme_id) { showMessage('warning', 'Selecione um alarme.'); return; }
    if (payload.descricao.length < 5) { showMessage('warning', 'Descrição deve ter ao menos 5 caracteres.'); return; }

    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content') || (window as any).CSRF_TOKEN || '';

    try {
      btn.disabled = true;
      showLoading('Finalizando ordem de serviço...');

      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_alarmes`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(payload)
      });

      const result = await response.json() as ApiResponse<unknown>;
      if (!response.ok || !result.success) throw new Error(result.error || 'Erro ao registrar manutenção.');

      showMessage('success', 'Manutenção registrada com sucesso.');
      document.getElementById('btnSalvarManutencao')!.setAttribute('disabled', 'true');
      document.getElementById('btnFinalizarManutencao')?.classList.remove('d-none');
      state.alarmeSelecionada = '';
      state.historicoPage = 1;
      await fetchDados();
    } catch (error) {
      errorHandler.handle(error, 'Failed to save maintenance', 'error');
      showMessage('danger', (error as Error).message || 'Erro ao salvar manutenção.');
    } finally {
      btn.disabled = false;
      hideLoading();
    }
  }

  function clearOrdemSelecao() {
    state.currentOsId = null;
    state.currentOsProblemas = '';
    state.currentOsNumeroOs = '';
    state.alarmeSelecionada = '';
    (document.getElementById('os_id') as HTMLInputElement).value = '';
    (document.getElementById('equipamento_id') as HTMLSelectElement).value = '';
    (document.getElementById('numero_os') as HTMLInputElement).value = '';
    document.getElementById('ordemNumeroOs')!.textContent = '';
    document.getElementById('ordemProblemas')!.textContent = '';
    document.getElementById('ordemSelecionadaContainer')!.classList.add('d-none');
    (document.getElementById('formManutencaoAlarme') as HTMLFormElement).reset();
    (document.getElementById('data_hora') as HTMLInputElement).value = toDateTimeLocalNow();
    (document.querySelector('#formManutencaoAlarme button[type="submit"]') as HTMLElement).textContent = 'Registrar Manutenção';
    (document.getElementById('btnSalvarManutencao') as HTMLButtonElement).disabled = false;
    document.getElementById('btnFinalizarManutencao')?.classList.add('d-none');
    clearMessage();
  }

  async function selectOrdemServico(osId: string) {
    const ordem = state.executingOrders.find((item) => String(item.id || '') === osId);
    if (!ordem) return;
    await ensureMaintenanceListsLoaded();
    state.currentOsId = osId;
    state.currentOsProblemas = (ordem.problemas as string) || '';
    state.currentOsNumeroOs = (ordem.numero_os as string) || '';
    state.alarmeSelecionada = String(ordem.alarme_id || '');

    const equipamentoSelect = document.getElementById('equipamento_id') as HTMLSelectElement | null;
    const equipamentoOsSelect = document.getElementById('equipamento_id_os') as HTMLSelectElement | null;
    const alarmeLabel = (ordem.alarme_nome as string) || `Alarme ${state.alarmeSelecionada}`;
    ensureSelectOption(equipamentoSelect, state.alarmeSelecionada, alarmeLabel);
    ensureSelectOption(equipamentoOsSelect, state.alarmeSelecionada, alarmeLabel);

    const alarme = state.alarmeOptions.find((a) => String((a as any).equipamento_id || a.id) === state.alarmeSelecionada);
    (document.getElementById('os_id') as HTMLInputElement).value = state.currentOsId;
    (document.getElementById('numero_os') as HTMLInputElement).value = state.currentOsNumeroOs;
    (document.getElementById('local_servico') as HTMLInputElement).value = (ordem.local_servico as string) || (alarme as any)?.local || '';
    (document.getElementById('endereco_servico') as HTMLInputElement).value = (ordem.endereco_servico as string) || (alarme as any)?.endereco || '';
    document.getElementById('ordemNumeroOs')!.textContent = state.currentOsNumeroOs || '-';
    document.getElementById('ordemProblemas')!.textContent = state.currentOsProblemas;
    document.getElementById('ordemSelecionadaContainer')!.classList.remove('d-none');
    (document.getElementById('data_hora') as HTMLInputElement).value = toDateTimeLocalNow();
    const procedimentoSelect = document.getElementById('procedimento_id') as HTMLSelectElement | null;
    const procedimentoToSet = String((ordem as any).procedimento_id || state.defaultProcedimentoId || '');
    if (procedimentoSelect && procedimentoToSet) {
      procedimentoSelect.value = procedimentoToSet;
      procedimentoSelect.classList.add('auto-filled-highlight');
      window.setTimeout(() => { procedimentoSelect.classList.remove('auto-filled-highlight'); }, 1400);
    }
    clearMessage();
    (document.querySelector('#formManutencaoAlarme button[type="submit"]') as HTMLElement).textContent = 'Finalizar Ordem de Serviço';
  }

  async function ensureMaintenanceListsLoaded(force = false) {
    if (!force && hasSelectableOptions('procedimento_id') && hasSelectableOptions('status_id')) return;

    const params = new URLSearchParams();
    params.set('page_num', '1');
    params.set('per_page', '10');
    params.set('include_lists', '1');

    const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_alarmes&${params.toString()}`, {
      headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store'
    });

    const payload = await response.json() as ApiResponse<Record<string, unknown>>;
    if (!response.ok || !payload.success) throw new Error(payload.error || 'Falha ao carregar listas de manutenção.');

    const data = (payload.data || {}) as Record<string, unknown>;
    if (Array.isArray(data.procedimento_options)) renderSelectOptions('procedimento_id', data.procedimento_options as Array<Record<string, unknown>>, 'Selecione...');
    if (Array.isArray(data.status_options)) renderSelectOptions('status_id', data.status_options as Array<Record<string, unknown>>, 'Manter status atual');
    state.defaultProcedimentoId = String((data as any)?.defaults?.procedimento_id || state.defaultProcedimentoId || '');
    state.listsLoaded = true;
  }

  async function criarOs(event: Event) {
    event.preventDefault();
    clearMessage();

    const form = event.currentTarget as HTMLFormElement;
    const btn = document.getElementById('btnSalvarOs') as HTMLButtonElement;
    const formData = new FormData(form);
    const payload: Record<string, string> = {
      action: 'create_os',
      alarme_id: String(formData.get('equipamento_id_os') || '').trim(),
      problemas: String(formData.get('problemas') || '').trim(),
      data_hora: String(formData.get('data_hora_os') || '').trim(),
      numero_os: String(formData.get('numero_os_os') || '').trim(),
      local_servico: String(formData.get('local_servico_os') || '').trim(),
      endereco_servico: String(formData.get('endereco_servico_os') || '').trim()
    };

    if (!payload.alarme_id) { showMessage('warning', 'Selecione um alarme.'); return; }
    if (payload.problemas.length < 5) { showMessage('warning', 'Problemas deve ter ao menos 5 caracteres.'); return; }

    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content') || (window as any).CSRF_TOKEN || '';

    try {
      btn.disabled = true;
      showLoading('Criando ordem de serviço...');

      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_alarmes`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
        credentials: 'same-origin', body: JSON.stringify(payload)
      });

      const result = await response.json() as ApiResponse<unknown>;
      if (!response.ok || !result.success) throw new Error(result.error || 'Erro ao criar ordem de serviço.');

      showMessage('success', 'Ordem de serviço cadastrada com sucesso.');
      (document.getElementById('formCriarOs') as HTMLFormElement).reset();
      (document.getElementById('local_servico_os') as HTMLInputElement).value = '';
      (document.getElementById('endereco_servico_os') as HTMLInputElement).value = '';
      (document.getElementById('data_hora_os') as HTMLInputElement).value = toDateTimeLocalNow();
      await fetchDados();
    } catch (error) {
      errorHandler.handle(error, 'Failed to create OS', 'error');
      showMessage('danger', (error as Error).message || 'Erro ao criar ordem de serviço.');
    } finally { btn.disabled = false; hideLoading(); }
  }

  async function fetchDados() {
    if (state._fetching) return;
    state._fetching = true;
    showLoading('Carregando dados...');

    try {
      state.historicoBusca = (document.getElementById('filtroBuscaHistorico') as HTMLInputElement)?.value || '';
      state.dataInicial = (document.getElementById('dataInicialHist') as HTMLInputElement)?.value || '';
      state.dataFinal = (document.getElementById('dataFinalHist') as HTMLInputElement)?.value || '';

      const params = new URLSearchParams();
      params.set('page_num', String(state.historicoPage));
      params.set('per_page', String(state.historicoPerPage));
      if (state.alarmeSelecionada) params.set('equipamento_id', String(state.alarmeSelecionada));
      if (state.historicoBusca) params.set('busca', state.historicoBusca);
      if (state.dataInicial) params.set('data_inicial', state.dataInicial);
      if (state.dataFinal) params.set('data_final', state.dataFinal);
      params.set('include_lists', state.listsLoaded ? '0' : '1');
      params.set('sort_by', state.historicoSortBy);
      params.set('sort_dir', state.historicoSortDir);
      params.set('_t', String(Date.now()));

      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_alarmes&${params.toString()}`, {
        headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store'
      });

      const payload = await response.json() as ApiResponse<Record<string, unknown>>;
      if (!response.ok || !payload.success) throw new Error(payload.error || 'Falha ao carregar dados de manutenção.');

      const data = (payload.data || {}) as Record<string, unknown>;
      if (Array.isArray(data.alarme)) renderAlarmes(data.alarme as Array<Record<string, unknown>>);
      if (Array.isArray(data.procedimento_options)) renderSelectOptions('procedimento_id', data.procedimento_options as Array<Record<string, unknown>>, 'Selecione...');
      if (Array.isArray(data.status_options)) renderSelectOptions('status_id', data.status_options as Array<Record<string, unknown>>, 'Manter status atual');
      state.defaultProcedimentoId = String((data as any)?.defaults?.procedimento_id || state.defaultProcedimentoId || '');
      if (Array.isArray(data.alarme) || Array.isArray(data.procedimento_options) || Array.isArray(data.status_options)) state.listsLoaded = true;

      renderPendingOrders(data.pending_orders as Array<Record<string, unknown>> || []);
      renderExecutingOrders(data.executing_orders as Array<Record<string, unknown>> || []);
      renderHistorico(data.historico as Array<Record<string, unknown>> || [], (data.pagination || {}) as Record<string, unknown>);
      renderPaginacao((data.pagination || {}) as Record<string, unknown>, (page: number) => { state.historicoPage = page; fetchDados(); });
      renderSortIndicators(state.historicoSortBy, state.historicoSortDir);
      setupHistoricoDualScroll();
    } catch (error) {
      errorHandler.handle(error, 'Failed to fetch data', 'error');
      throw error;
    } finally { hideLoading(); state._fetching = false; }
  }

  function csvEscape(value: unknown): string {
    const str = String(value ?? '');
    if (str.includes(';') || str.includes('"') || str.includes('\n')) return `"${str.replace(/"/g, '""')}"`;
    return str;
  }

  function getSelectedOrAllHistoricoRows() {
    if (state.selectedHistoricoIds.size > 0) return state.historico.filter((item) => state.selectedHistoricoIds.has(String(item.id || '')));
    return null;
  }

  async function fetchHistoricoCompleto() {
    const params = new URLSearchParams();
    params.set('page_num', '1');
    params.set('per_page', '99999');
    if (state.alarmeSelecionada) params.set('equipamento_id', String(state.alarmeSelecionada));
    if (state.historicoBusca) params.set('busca', state.historicoBusca);
    if (state.dataInicial) params.set('data_inicial', state.dataInicial);
    if (state.dataFinal) params.set('data_final', state.dataFinal);
    params.set('sort_by', state.historicoSortBy);
    params.set('sort_dir', state.historicoSortDir);
    params.set('_t', String(Date.now()));

    const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_alarmes&${params.toString()}`, {
      headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store'
    });
    const payload = await response.json() as ApiResponse<Record<string, unknown>>;
    if (!response.ok || !payload.success) throw new Error(payload.error || 'Falha ao carregar histórico completo.');
    const data = (payload.data || {}) as Record<string, unknown>;
    return Array.isArray(data.historico) ? data.historico as Array<Record<string, unknown>> : [];
  }

  async function exportarCsvHistorico() {
    let rows = getSelectedOrAllHistoricoRows();
    if (!rows) rows = await fetchHistoricoCompleto();
    if (rows.length === 0) { showMessage('warning', 'Não há dados no histórico para exportar.'); return; }

    let csv = 'Data/Hora;OS;Conta;IP;Local;Endereço;Procedimento;Status;Técnico;Descrição;Peças;Usuário\n';
    rows.forEach((item) => {
      csv += [
        csvEscape(toBrDate(item.data_execucao || item.data_hora || item.created_at)),
        csvEscape(item.numero_os || ''),
        csvEscape((item.conta as string) || (item.alarme_nome as string) || ''),
        csvEscape(item.ip || ''),
        csvEscape(item.local_servico || ''),
        csvEscape(item.endereco_servico || ''),
        csvEscape(item.procedimento_nome || ''),
        csvEscape(item.status_nome || ''),
        csvEscape(item.tecnico || ''),
        csvEscape(item.descricao || ''),
        csvEscape(item.pecas_previstas || ''),
        csvEscape(item.usuario_nome || 'Sistema')
      ].join(';') + '\n';
    });

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `manutencao_alarmes_${toIsoDateStamp()}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    showMessage('success', 'Exportação CSV iniciada.');
  }

  async function exportarPdfHistorico() {
    let rows = getSelectedOrAllHistoricoRows();
    if (!rows) rows = await fetchHistoricoCompleto();
    if (rows.length === 0) { showMessage('warning', 'Não há dados no histórico para exportar.'); return; }

    const htmlRows = rows.map((item) => `
      <tr>
        <td>${escapeHtml(toBrDate(item.data_execucao || item.data_hora || item.created_at))}</td>
        <td>${escapeHtml(item.numero_os || '-')}</td>
        <td>${escapeHtml((item.conta as string) || (item.alarme_nome as string) || '-')}</td>
        <td>${escapeHtml(item.ip || '-')}</td>
        <td>${escapeHtml(item.local_servico || '-')}</td>
        <td>${escapeHtml(item.endereco_servico || '-')}</td>
        <td>${escapeHtml(item.procedimento_nome || '-')}</td>
        <td>${escapeHtml(item.status_nome || '-')}</td>
        <td>${escapeHtml(item.tecnico || '-')}</td>
        <td>${escapeHtml(item.descricao || '-')}</td>
        <td>${escapeHtml(item.pecas_previstas || '-')}</td>
        <td>${escapeHtml(item.usuario_nome || 'Sistema')}</td>
      </tr>
    `).join('');

    const reportHtml = `<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Relatório Manutenção Alarmes</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; margin: 24px; }
    h1 { margin: 0 0 8px; font-size: 18px; }
    .meta { margin-bottom: 16px; color: #444; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 6px; vertical-align: top; }
    th { background: #f1f1f1; text-align: left; }
    @media print { body { margin: 8mm; } }
  </style>
</head>
<body>
  <h1>Relatório de Manutenção de Alarmes</h1>
  <div class="meta">Gerado em: ${escapeHtml(new Date().toLocaleString('pt-BR'))} | Total: ${rows.length}</div>
  <table>
    <thead>
      <tr>
        <th>Data/Hora</th><th>OS</th><th>Alarme</th><th>IP</th>
        <th>Local</th><th>Endereço</th><th>Procedimento</th>
        <th>Status</th><th>Técnico</th><th>Descrição</th><th>Peças</th><th>Usuário</th>
      </tr>
    </thead>
    <tbody>${htmlRows}</tbody>
  </table>
</body>
</html>`;

    const win = window.open('', '_blank');
    if (!win) { showMessage('warning', 'Permita pop-ups para gerar o relatório.'); return; }
    win.document.write(reportHtml);
    win.document.close();
    win.focus();
    win.print();
    showMessage('success', 'Relatório gerado com sucesso.');
  }

  function bindEvents() {
    (document.getElementById('formCriarOs') as HTMLFormElement | null)?.addEventListener('submit', criarOs);
    (document.getElementById('formManutencaoAlarme') as HTMLFormElement | null)?.addEventListener('submit', salvarManutencao);

    const onFilterInput = (async (e: Event): Promise<void> => {
      try { await loadAlarmeOptions((e.target as HTMLInputElement).value, 'equipamento_id_os'); } catch (error) { showMessage('danger', 'Erro ao buscar alarmes: ' + (error as Error).message); }
    }) as (...args: unknown[]) => unknown;
    (document.getElementById('filtroAlarmeOs') as HTMLInputElement | null)?.addEventListener('input', window.debounce(onFilterInput, 300));

    (document.getElementById('btnBuscarAlarmeLista') as HTMLButtonElement | null)?.addEventListener('click', () => {
      const term = (document.getElementById('filtroAlarmeOs') as HTMLInputElement)?.value || '';
      loadAlarmeOptions(term, 'equipamento_id_os');
    });

    (document.getElementById('dataInicialHist') as HTMLInputElement | null)?.addEventListener('change', debouncedFetchDados);
    (document.getElementById('dataFinalHist') as HTMLInputElement | null)?.addEventListener('change', debouncedFetchDados);
    (document.getElementById('historicoPerPageHist') as HTMLSelectElement | null)?.addEventListener('change', debouncedFetchDados);

    (document.getElementById('filtroBuscaHistorico') as HTMLInputElement | null)?.addEventListener('input', (e) => {
      state.historicoBusca = (e.target as HTMLInputElement).value;
      debouncedFetchDados();
    });

    (document.getElementById('btnFiltrarHistorico') as HTMLButtonElement | null)?.addEventListener('click', () => {
      const id = (document.getElementById('equipamento_id') as HTMLSelectElement)?.value;
      if (!id) { showMessage('warning', 'Selecione um alarme para filtrar.'); return; }
      state.alarmeSelecionada = id;
      state.historicoPage = 1;
      fetchDados();
    });

    (document.getElementById('btnLimparFiltroHistorico') as HTMLButtonElement | null)?.addEventListener('click', () => {
      state.alarmeSelecionada = '';
      state.historicoBusca = '';
      state.dataInicial = '';
      state.dataFinal = '';
      const bEl = document.getElementById('filtroBuscaHistorico') as HTMLInputElement | null;
      if (bEl) bEl.value = '';
      (document.getElementById('dataInicialHist') as HTMLInputElement).value = '';
      (document.getElementById('dataFinalHist') as HTMLInputElement).value = '';
      state.historicoPage = 1;
      fetchDados();
    });

    (document.getElementById('equipamento_id') as HTMLSelectElement | null)?.addEventListener('change', (event) => {
      state.alarmeSelecionada = String((event.target as HTMLSelectElement).value || '');
      state.historicoPage = 1;
      debouncedFetchDados();
    });

    (document.getElementById('equipamento_id_os') as HTMLSelectElement | null)?.addEventListener('change', (event) => {
      const id = String((event.target as HTMLSelectElement).value || '');
      const alarme = state.alarmeOptions.find((a) => String((a as any).equipamento_id || a.id) === id);
      (document.getElementById('local_servico_os') as HTMLInputElement).value = (alarme as any)?.local || '';
      (document.getElementById('endereco_servico_os') as HTMLInputElement).value = (alarme as any)?.endereco || '';
    });

    (document.getElementById('btnLimparOs') as HTMLButtonElement | null)?.addEventListener('click', () => {
      (document.getElementById('formCriarOs') as HTMLFormElement).reset();
      (document.getElementById('local_servico_os') as HTMLInputElement).value = '';
      (document.getElementById('endereco_servico_os') as HTMLInputElement).value = '';
      (document.getElementById('data_hora_os') as HTMLInputElement).value = toDateTimeLocalNow();
      showMessage('info', 'Formulário limpo');
    });

    (document.getElementById('btnFinalizarManutencao') as HTMLButtonElement | null)?.addEventListener('click', () => { clearOrdemSelecao(); showMessage('success', 'Ordem de serviço finalizada.'); });
    (document.getElementById('btnLimparFormulario') as HTMLButtonElement | null)?.addEventListener('click', () => { clearOrdemSelecao(); showMessage('info', 'Seleção limpa'); });

    (document.getElementById('btnExportarCsvHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      try { await exportarCsvHistorico(); } catch (error) { showMessage('danger', (error as Error).message || 'Falha ao exportar CSV.'); }
    });

    (document.getElementById('btnExportarPdfHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      try { await exportarPdfHistorico(); } catch (error) { showMessage('danger', (error as Error).message || 'Falha ao exportar PDF.'); }
    });

    (document.getElementById('btnExportarRapidoHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      try { await exportarPdfHistorico(); } catch (error) { showMessage('danger', (error as Error).message || 'Falha ao gerar relatório.'); }
    });

    (document.getElementById('btnSelecionarTodosHistorico') as HTMLButtonElement | null)?.addEventListener('click', () => {
      state.historico.forEach((item) => {
        const itemId = String(item.id || '');
        if (!itemId) return;
        state.selectedHistoricoIds.add(itemId);
        state.selectedHistoricoRows[itemId] = item;
      });
      renderHistorico(state.historico, state.currentPagination);
    });

    (document.getElementById('btnLimparSelecaoHistorico') as HTMLButtonElement | null)?.addEventListener('click', () => {
      state.selectedHistoricoIds.clear();
      state.selectedHistoricoRows = {};
      renderHistorico(state.historico, state.currentPagination);
    });

    (document.getElementById('selectAllHistorico') as HTMLInputElement | null)?.addEventListener('change', (event) => {
      const checked = (event.target as HTMLInputElement).checked;
      document.querySelectorAll('.historico-select-checkbox').forEach((checkbox) => {
        (checkbox as HTMLInputElement).checked = checked;
        const id = String((checkbox as HTMLElement).getAttribute('data-id') || '');
        const item = state.historico.find((row) => String(row.id || '') === id);
        if (!id) return;
        if (checked) { state.selectedHistoricoIds.add(id); if (item) state.selectedHistoricoRows[id] = item; }
        else { state.selectedHistoricoIds.delete(id); delete state.selectedHistoricoRows[id]; }
      });
      updateSelectedCount(state.selectedHistoricoIds);
      updateSelectAllCheckbox(state.selectedHistoricoIds, state.historico);
    });
  }

  async function init() {
    if (!document.body.classList.contains('page-manutencao_alarmes')) return;

    (document.getElementById('data_hora_os') as HTMLInputElement).value = toDateTimeLocalNow();
    (document.getElementById('data_hora') as HTMLInputElement).value = toDateTimeLocalNow();

    bindEvents();

    try {
      showLoading('Inicializando sistema...');
      await fetchDados();
    } catch (error) {
      errorHandler.handle(error, 'Failed to initialize system', 'error');
      showMessage('danger', 'Falha ao carregar dados de manutenção.');
      document.getElementById('historicoManutencaoBody')!.innerHTML = '<tr><td colspan="15" class="text-center text-danger py-4">Falha ao carregar histórico.</td></tr>';
    } finally { hideLoading(); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
export {};