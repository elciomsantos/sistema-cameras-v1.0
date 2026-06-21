(function () {
  const state = {
    cameraSelecionada: '',
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
    cameraBusca: '',
    cameraOptions: [] as Array<Record<string, unknown>>,
    selectedHistoricoIds: new Set<string>(),
    selectedHistoricoRows: {} as Record<string, unknown>,
    currentPagination: {} as Record<string, unknown>,
    defaultProcedimentoId: '',
    _fetching: false,
    _abortController: null as AbortController | null
  };

  function getApiBase() {
    return window.APP_API_BASE;
  }

  function renderCameras(cameras: Array<Record<string, unknown>>) {
    state.cameraOptions = Array.isArray(cameras) ? cameras : [];
    const selectIds = ['equipamento_id', 'equipamento_id_os'];
    const options = (cameras || []).map((camera) => {
      const localName = (camera.local_nome as string) || (camera.descricao as string) || `EQUIPAMENTO ${camera.id}`;
      const tipoLabel = camera.tipo_equipamento_nome ? ` [${camera.tipo_equipamento_nome}]` : '';
      const manutBadge = camera.em_manutencao ? ' (Em manutenção)' : '';
      const label = camera.ip ? `${localName} - ${camera.ip}${tipoLabel}${manutBadge}` : `${localName}${tipoLabel}${manutBadge}`;
      return `<option value="${escapeHtml(camera.id)}"${camera.em_manutencao ? ' data-em-manutencao="1"' : ''}>${escapeHtml(label)}</option>`;
    }).join('');

    selectIds.forEach((selectId) => {
      const select = document.getElementById(selectId) as HTMLSelectElement | null;
      if (!select) return;
      select.innerHTML = '<option value="">Selecione uma câmera...</option>' + options;
    });
  }

  async function ensureMaintenanceListsLoaded(force = false) {
    if (!force && hasSelectableOptions('procedimento_id') && hasSelectableOptions('status_id')) {
      return;
    }

    if (state._abortController) {
      state._abortController.abort();
    }
    state._abortController = new AbortController();

    const params = new URLSearchParams();
    params.set('page_num', '1');
    params.set('per_page', '10');
    params.set('include_lists', '1');

    const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_cameras&${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
      signal: state._abortController!.signal
    });

    if (!response.ok) {
      throw new Error('Falha ao carregar listas de manutenção.');
    }
    const payload = await response.json() as ApiResponse<Record<string, unknown>>;
    if (!payload.success) {
      throw new Error(payload.error || 'Falha ao carregar listas de manutenção.');
    }

    const data = (payload.data || {}) as Record<string, unknown>;
    if (Array.isArray(data.procedimento_options)) {
      renderSelectOptions('procedimento_id', data.procedimento_options as Array<Record<string, unknown>>, 'Selecione...');
    }
    if (Array.isArray(data.status_options)) {
      renderSelectOptions('status_id', data.status_options as Array<Record<string, unknown>>, 'Manter status atual');
    }
    state.defaultProcedimentoId = String((data as any)?.defaults?.procedimento_id || state.defaultProcedimentoId || '');
    state.listsLoaded = true;
  }

  async function loadCameraOptions(term = '') {
    const select = document.getElementById('equipamento_id') as HTMLSelectElement | null;
    const selectOs = document.getElementById('equipamento_id_os') as HTMLSelectElement | null;
    if (!select) return;
    select.innerHTML = '<option value="">Carregando câmeras...</option>';
    if (selectOs) {
      selectOs.innerHTML = '<option value="">Carregando câmeras...</option>';
    }

    try {
      const qs = new URLSearchParams();
      qs.set('page_num', '1');
      qs.set('per_page', '50');
      qs.set('excluir_manutencao', '1');
      if (term) qs.set('busca', term);

      const response = await fetchWithTimeout(`${getApiBase()}api_cameras&${qs.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store'
      });
      if (!response.ok) {
        throw new Error('Erro ao carregar cameras');
      }
      const payload = await response.json() as ApiResponse<Array<Record<string, unknown>>>;
      if (!payload.success) {
        throw new Error(payload.error || 'Erro ao carregar cameras');
      }

      const list = Array.isArray(payload.data) ? payload.data : [];
      renderCameras(list);

      if (state.cameraSelecionada) {
        const equipamentoSelect = document.getElementById('equipamento_id') as HTMLSelectElement | null;
        const equipamentoOsSelect = document.getElementById('equipamento_id_os') as HTMLSelectElement | null;
        ensureSelectOption(equipamentoSelect, String(state.cameraSelecionada), `Câmera ${state.cameraSelecionada}`);
        ensureSelectOption(equipamentoOsSelect, String(state.cameraSelecionada), `Câmera ${state.cameraSelecionada}`);
      }
    } catch (e) {
      console.error('Erro ao carregar cameras:', e);
      select.innerHTML = '<option value="">Erro ao carregar cameras</option>';
      if (selectOs) selectOs.innerHTML = '<option value="">Erro ao carregar cameras</option>';
    }
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
    resumo.textContent = `Total: ${total} | Pagina ${page} de ${totalPages}`;

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
        <td>
          <strong>${escapeHtml(item.tipo_equipamento_nome || '-')}</strong>
          ${item.camera_nome ? `<br><small class="text-muted">${escapeHtml(item.camera_nome as string)}</small>` : ''}
        </td>
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
        <td>
          ${escapeHtml(item.tipo_equipamento_nome || '-')}
          ${item.camera_nome ? `<br><small class="text-muted">${escapeHtml(item.camera_nome as string)}</small>` : ''}
        </td>
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
        <td>
          ${escapeHtml(item.tipo_equipamento_nome || '-')}
          ${item.camera_nome ? `<br><small class="text-muted">${escapeHtml(item.camera_nome as string)}</small>` : ''}
        </td>
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
        await selectOrdemServico(id);
      });
    });
  }

  async function selectOrdemServico(osId: string) {
    const ordem = state.executingOrders.find((item) => String(item.id || '') === osId);
    if (!ordem) return;
    await ensureMaintenanceListsLoaded();
    state.currentOsId = osId;
    state.currentOsProblemas = (ordem.problemas as string) || '';
    state.currentOsNumeroOs = (ordem.numero_os as string) || '';
    state.cameraSelecionada = String(ordem.equipamento_id || '');

    const equipamentoSelect = document.getElementById('equipamento_id') as HTMLSelectElement | null;
    const equipamentoOsSelect = document.getElementById('equipamento_id_os') as HTMLSelectElement | null;
    const cameraLabel = (ordem.camera_nome as string) || `Câmera ${state.cameraSelecionada}`;
    ensureSelectOption(equipamentoSelect, state.cameraSelecionada, cameraLabel);
    ensureSelectOption(equipamentoOsSelect, state.cameraSelecionada, cameraLabel);

    (document.getElementById('os_id') as HTMLInputElement).value = state.currentOsId;
    const cameraIpEl = document.getElementById('camera_ip') as HTMLInputElement | null;
    if (cameraIpEl) cameraIpEl.value = (ordem.ip as string) || '';
    (document.getElementById('numero_os') as HTMLInputElement).value = state.currentOsNumeroOs;
    (document.getElementById('local_servico') as HTMLInputElement).value = (ordem.local_servico as string) || '';
    (document.getElementById('endereco_servico') as HTMLInputElement).value = (ordem.endereco_servico as string) || '';
    document.getElementById('ordemNumeroOs')!.textContent = state.currentOsNumeroOs || '-';
    document.getElementById('ordemProblemas')!.textContent = state.currentOsProblemas;
    document.getElementById('ordemSelecionadaContainer')!.classList.remove('d-none');
    document.getElementById('btnLimparSelecaoOs')!.classList.remove('d-none');
    (document.getElementById('data_hora') as HTMLInputElement).value = toDateTimeLocalNow();
    const procedimentoSelect = document.getElementById('procedimento_id') as HTMLSelectElement | null;
    const procedimentoToSet = String((ordem as any).procedimento_id || state.defaultProcedimentoId || '');
    if (procedimentoSelect && procedimentoToSet) {
      procedimentoSelect.value = procedimentoToSet;
      procedimentoSelect.classList.add('auto-filled-highlight');
      window.setTimeout(() => { procedimentoSelect.classList.remove('auto-filled-highlight'); }, 1400);
    }
    (document.querySelector('#formManutencaoCamera button[type="submit"]') as HTMLElement).textContent = 'Finalizar Ordem de Serviço';
  }

  async function iniciarExecucaoOs(osId: string) {
    const ordem = state.pendingOrders.find((item) => String(item.id || '') === osId);
    if (!ordem) return;

    clearMessage();
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content') || (window as any).CSRF_TOKEN || '';

    const payload = {
      action: 'start_os',
      os_id: String(ordem.id || '').trim(),
      equipamento_id: String(ordem.equipamento_id || '').trim()
    };

    try {
      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_cameras`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      if (!response.ok) throw new Error('Falha ao iniciar execução da OS.');
      const result = await response.json() as ApiResponse<unknown>;
      if (!result.success) throw new Error(result.error || 'Falha ao iniciar execução da OS.');

      showMessage('success', result.message || 'Ordem de serviço em execução.');
      state.cameraSelecionada = '';
      await fetchDados();
    } catch (error) {
      handleApiError(error, 'Erro ao iniciar execução da OS.');
    }
  }

  function clearOrdemSelecao() {
    state.currentOsId = null;
    state.currentOsProblemas = '';
    state.currentOsNumeroOs = '';
    state.cameraSelecionada = '';
    (document.getElementById('os_id') as HTMLInputElement).value = '';
    (document.getElementById('equipamento_id') as HTMLSelectElement).value = '';
    const cameraIpEl = document.getElementById('camera_ip') as HTMLInputElement | null;
    if (cameraIpEl) cameraIpEl.value = '';
    (document.getElementById('numero_os') as HTMLInputElement).value = '';
    document.getElementById('ordemNumeroOs')!.textContent = '';
    document.getElementById('ordemProblemas')!.textContent = '';
    document.getElementById('ordemSelecionadaContainer')!.classList.add('d-none');
    document.getElementById('btnLimparSelecaoOs')!.classList.add('d-none');
    (document.getElementById('formManutencaoCamera') as HTMLFormElement).reset();
    (document.getElementById('data_hora') as HTMLInputElement).value = toDateTimeLocalNow();
    (document.querySelector('#formManutencaoCamera button[type="submit"]') as HTMLElement).textContent = 'Registrar Manutenção';
    (document.getElementById('btnSalvarManutencao') as HTMLButtonElement).disabled = false;
    const btnFinalizar = document.getElementById('btnFinalizarManutencao');
    if (btnFinalizar) btnFinalizar.classList.add('d-none');
    const secaoAnexos = document.getElementById('secaoAnexosManutencao');
    if (secaoAnexos) { secaoAnexos.classList.add('d-none'); secaoAnexos.classList.remove('anexos-section-visible'); }
    clearMessage();
  }

  async function fetchDados() {
    if (state._fetching) return;
    state._fetching = true;

    if (state._abortController) {
      state._abortController.abort();
    }
    state._abortController = new AbortController();

    try {
      const params = new URLSearchParams();
      params.set('page_num', String(state.historicoPage));
      params.set('per_page', String(state.historicoPerPage));
      if (state.cameraSelecionada) params.set('equipamento_id', String(state.cameraSelecionada));
      if (state.historicoBusca) params.set('busca', state.historicoBusca);
      if (state.dataInicial) params.set('data_inicial', state.dataInicial);
      if (state.dataFinal) params.set('data_final', state.dataFinal);
      params.set('include_lists', state.listsLoaded ? '0' : '1');
      params.set('sort_by', state.historicoSortBy);
      params.set('sort_dir', state.historicoSortDir);
      params.set('_t', String(Date.now()));

      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_cameras&${params.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
        signal: state._abortController!.signal
      });

      if (!response.ok) throw new Error('Falha ao carregar dados de manutenção.');
      const payload = await response.json() as ApiResponse<Record<string, unknown>>;
      if (!payload.success) throw new Error(payload.error || 'Falha ao carregar dados de manutenção.');

      const data = (payload.data || {}) as Record<string, unknown>;
      if (Array.isArray(data.cameras)) renderCameras(data.cameras as Array<Record<string, unknown>>);
      if (Array.isArray(data.procedimento_options)) renderSelectOptions('procedimento_id', data.procedimento_options as Array<Record<string, unknown>>, 'Selecione...');
      if (Array.isArray(data.status_options)) renderSelectOptions('status_id', data.status_options as Array<Record<string, unknown>>, 'Manter status atual');
      state.defaultProcedimentoId = String((data as any)?.defaults?.procedimento_id || state.defaultProcedimentoId || '');
      if (Array.isArray(data.cameras) || Array.isArray(data.procedimento_options) || Array.isArray(data.status_options)) state.listsLoaded = true;

      const cameraToSet = state.cameraSelecionada || (document.getElementById('equipamento_id') as HTMLSelectElement)?.value || '';
      if (cameraToSet) {
        (document.getElementById('equipamento_id') as HTMLSelectElement).value = cameraToSet;
        (document.getElementById('equipamento_id_os') as HTMLSelectElement).value = cameraToSet;
      }

      renderPendingOrders(data.pending_orders as Array<Record<string, unknown>> || []);
      renderExecutingOrders(data.executing_orders as Array<Record<string, unknown>> || []);
      renderHistorico(data.historico as Array<Record<string, unknown>> || [], (data.pagination || {}) as Record<string, unknown>);
      renderPaginacao((data.pagination || {}) as Record<string, unknown>, (page: number) => { state.historicoPage = page; fetchDados(); });
      renderSortIndicators(state.historicoSortBy, state.historicoSortDir);
      setupHistoricoDualScroll();
    } catch (error) {
      console.error(error);
    } finally {
      state._fetching = false;
    }
  }

  async function fetchHistoricoCompleto() {
    const allRows: Array<Record<string, unknown>> = [];
    const perPage = 100;
    let page = 1;
    let totalPages = 1;

    do {
      const params = new URLSearchParams();
      params.set('page_num', String(page));
      params.set('per_page', String(perPage));
      if (state.cameraSelecionada) params.set('equipamento_id', String(state.cameraSelecionada));
      if (state.historicoBusca) params.set('busca', state.historicoBusca);
      if (state.dataInicial) params.set('data_inicial', state.dataInicial);
      if (state.dataFinal) params.set('data_final', state.dataFinal);
      params.set('include_lists', '0');
      params.set('sort_by', state.historicoSortBy);
      params.set('sort_dir', state.historicoSortDir);
      params.set('_t', String(Date.now()));

      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_cameras&${params.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store'
      });
      if (!response.ok) throw new Error('Falha ao carregar histórico completo.');
      const payload = await response.json() as ApiResponse<Record<string, unknown>>;
      if (!payload.success) throw new Error(payload.error || 'Falha ao carregar histórico completo.');
      const data = (payload.data || {}) as Record<string, unknown>;
      const rows = Array.isArray(data.historico) ? data.historico as Array<Record<string, unknown>> : [];
      allRows.push(...rows);
      const pagination = (data.pagination || {}) as Record<string, unknown>;
      totalPages = Number((pagination as any).total_pages || 1);
      page += 1;
    } while (page <= totalPages);

    return allRows;
  }

  function getSelectedOrAllHistoricoRows() {
    const selectedRows = Object.values(state.selectedHistoricoRows || {});
    return selectedRows.length > 0 ? selectedRows : null;
  }

  async function exportarCsvHistorico() {
    let rows = getSelectedOrAllHistoricoRows();
    if (!rows) rows = await fetchHistoricoCompleto();
    if (rows.length === 0) { showMessage('warning', 'Não há dados no histórico para exportar.'); return; }

    let csv = 'Data/Hora;OS;Tipo;Câmera;IP;Série;Modelo;Local Serviço;Endereço Serviço;Procedimento;Status;Técnico;Descrição;Peças;Usuário\n';
    rows.forEach((r: unknown) => {
      const item = r as Record<string, unknown>;
      csv += [
        csvEscape(toBrDate(item.data_execucao || item.data_hora || item.created_at)),
        csvEscape(item.numero_os || ''),
        csvEscape(item.tipo_equipamento_nome || ''),
        csvEscape(item.camera_nome || ''),
        csvEscape(item.ip || ''),
        csvEscape(item.numero_serie || ''),
        csvEscape(item.modelo_nome || ''),
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
    link.setAttribute('download', `manutencao_cameras_${toIsoDateStamp()}.csv`);
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

    const htmlRows = rows.map((r: unknown) => {
      const item = r as Record<string, unknown>;
      return '<tr>'
        + '<td>' + escapeHtml(toBrDate(item.data_execucao || item.data_hora || item.created_at)) + '</td>'
        + '<td>' + escapeHtml(item.numero_os || '-') + '</td>'
        + '<td>' + escapeHtml(item.tipo_equipamento_nome || '-') + '</td>'
        + '<td>' + escapeHtml(item.camera_nome || '-') + '</td>'
        + '<td>' + escapeHtml(item.ip || '-') + '</td>'
        + '<td>' + escapeHtml(item.modelo_nome || '-') + '</td>'
        + '<td>' + escapeHtml(item.local_servico || '-') + '</td>'
        + '<td>' + escapeHtml(item.endereco_servico || '-') + '</td>'
        + '<td>' + escapeHtml(item.procedimento_nome || '-') + '</td>'
        + '<td>' + escapeHtml(item.status_nome || '-') + '</td>'
        + '<td>' + escapeHtml(item.tecnico || '-') + '</td>'
        + '<td>' + escapeHtml(item.descricao || '-') + '</td>'
        + '<td>' + escapeHtml(item.pecas_previstas || '-') + '</td>'
        + '<td>' + escapeHtml(item.usuario_nome || 'Sistema') + '</td>'
        + '</tr>';
    }).join('');

    const reportHtml = `<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Relatório Manutenção Câmeras</title>
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
  <h1>Relatório de Manutenção de Câmeras</h1>
  <div class="meta">Gerado em: ${escapeHtml(new Date().toLocaleString('pt-BR'))} | Total: ${rows.length}</div>
  <table>
    <thead>
      <tr>
        <th>Data/Hora</th><th>OS</th><th>Tipo</th><th>Câmera</th><th>IP</th>
        <th>Modelo</th><th>Local</th><th>Endereço</th><th>Procedimento</th>
        <th>Status</th><th>Técnico</th><th>Descrição</th><th>Peças</th><th>Usuário</th>
      </tr>
    </thead>
    <tbody>${htmlRows}</tbody>
  </table>
  <script>window.onload=function(){window.print();};<\/script>
</body>
</html>`;

    const printWindow = window.open('', '_blank');
    if (!printWindow) { showMessage('danger', 'Não foi possível abrir a janela de impressão. Verifique o bloqueador de pop-up.'); return; }
    printWindow.document.open();
    printWindow.document.write(reportHtml);
    printWindow.document.close();
  }

  async function salvarOrdemServico(event: Event) {
    event.preventDefault();
    clearMessage();

    const form = document.getElementById('formCriarOs') as HTMLFormElement;
    const button = document.getElementById('btnSalvarOs') as HTMLButtonElement;
    const formData = new FormData(form);

    const payload: Record<string, string> = {
      action: 'create_os',
      equipamento_id: String(formData.get('equipamento_id_os') || '').trim(),
      data_hora: String(formData.get('data_hora_os') || '').trim(),
      numero_os: String(formData.get('numero_os_os') || '').trim(),
      local_servico: String(formData.get('local_servico_os') || '').trim(),
      endereco_servico: String(formData.get('endereco_servico_os') || '').trim(),
      problemas: String(formData.get('problemas') || '').trim()
    };

    if (!payload.equipamento_id) { showMessage('warning', 'Selecione uma câmera para criar a OS.'); return; }
    if (payload.problemas.length < 5) { showMessage('warning', 'Descreva os problemas na ordem de serviço.'); return; }

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content') || (window as any).CSRF_TOKEN || '';

    try {
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Criando...';

      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_cameras`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      if (!response.ok) throw new Error('Falha ao criar ordem de serviço.');
      const result = await response.json() as ApiResponse<unknown>;
      if (!result.success) throw new Error(result.error || 'Falha ao criar ordem de serviço.');

      showMessage('success', result.message || 'Ordem de serviço cadastrada com sucesso.');
      form.reset();
      (document.getElementById('data_hora_os') as HTMLInputElement).value = toDateTimeLocalNow();
      state.historicoPage = 1;
      state.cameraSelecionada = '';
      await fetchDados();
    } catch (error) {
      handleApiError(error, 'Erro ao cadastrar ordem de serviço.');
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="fas fa-save me-1"></i>Criar Ordem de Serviço';
    }
  }

  async function salvarManutencao(event: Event) {
    event.preventDefault();
    clearMessage();

    if (!state.currentOsId) { showMessage('warning', 'Selecione uma ordem de serviço em execução para finalizar.'); return; }

    const form = document.getElementById('formManutencaoCamera') as HTMLFormElement;
    const button = document.getElementById('btnSalvarManutencao') as HTMLButtonElement;
    const formData = new FormData(form);

    const payload: Record<string, string> = {
      action: 'finalize_os',
      os_id: String(formData.get('os_id') || '').trim(),
      equipamento_id: String(formData.get('equipamento_id') || '').trim(),
      data_hora: String(formData.get('data_hora') || '').trim(),
      procedimento_id: String(formData.get('procedimento_id') || '').trim(),
      status_id: String(formData.get('status_id') || '').trim(),
      tecnico: String(formData.get('tecnico') || '').trim(),
      numero_os: String(formData.get('numero_os') || '').trim(),
      local_servico: String(formData.get('local_servico') || '').trim(),
      endereco_servico: String(formData.get('endereco_servico') || '').trim(),
      pecas_previstas: String(formData.get('pecas_previstas') || '').trim(),
      descricao: String(formData.get('descricao') || '').trim()
    };

    if (!payload.equipamento_id) { showMessage('warning', 'Selecione uma câmera.'); return; }
    if (payload.descricao.length < 5) { showMessage('warning', 'Descrição precisa ter ao menos 5 caracteres.'); return; }

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content') || (window as any).CSRF_TOKEN || '';

    try {
      button.disabled = true;
      button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Salvando...';

      const response = await fetchWithTimeout(`${getApiBase()}api_manutencao_cameras`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      if (!response.ok) throw new Error('Falha ao registrar manutenção.');
      const result = await response.json() as ApiResponse<unknown>;
      if (!result.success) throw new Error(result.error || 'Falha ao registrar manutenção.');

      showMessage('success', result.message || 'Manutenção registrada com sucesso.');
      const manutencaoId = (document.getElementById('os_id') as HTMLInputElement).value;
      if (manutencaoId) {
        const secao = document.getElementById('secaoAnexosManutencao');
        const listEl = secao && secao.querySelector('.anexos-list');
        if (listEl) {
          listEl.setAttribute('data-manutencao-camera-id', manutencaoId);
          secao.classList.remove('d-none');
          secao.classList.add('anexos-section-visible');
          if (typeof window.initAnexoSection === 'function') window.initAnexoSection(secao);
          secao.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }
      document.getElementById('btnFinalizarManutencao')!.classList.remove('d-none');
      state.historicoPage = 1;
      state.cameraSelecionada = '';
      await fetchDados();
    } catch (error) {
      handleApiError(error, 'Erro ao registrar manutenção.');
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="fas fa-save me-1"></i>Registrar Manutenção';
    }
  }

  function bindEvents() {
    (document.getElementById('formCriarOs') as HTMLFormElement | null)?.addEventListener('submit', salvarOrdemServico);
    (document.getElementById('formManutencaoCamera') as HTMLFormElement).addEventListener('submit', salvarManutencao);

    const btnBuscarCameraLista = document.getElementById('btnBuscarCameraLista') as HTMLButtonElement | null;
    const filtroCamera = document.getElementById('filtroCamera') as HTMLInputElement | null;
    if (btnBuscarCameraLista && filtroCamera) {
      let debounceTimer: ReturnType<typeof setTimeout>;

      const searchCameras = async (term: string) => {
        clearMessage();
        state.cameraBusca = term;
        await loadCameraOptions(term);
        const select = document.getElementById('equipamento_id_os') as HTMLSelectElement | null;
        if (!select) return;
        const options = Array.from(select.options).filter(o => o.value !== '');
        if (options.length === 0 && term) showMessage('warning', `Nenhuma câmera encontrada para "${term}".`);
        else if (options.length === 1) { select.value = options[0].value; select.dispatchEvent(new Event('change')); }
      };

      btnBuscarCameraLista.addEventListener('click', async () => {
        const term = String(filtroCamera.value || '').trim();
        clearTimeout(debounceTimer);
        await searchCameras(term);
      });
      filtroCamera.addEventListener('keydown', async (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        clearTimeout(debounceTimer);
        await searchCameras(String(filtroCamera.value || '').trim());
      });
      filtroCamera.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const term = String(filtroCamera.value || '').trim();
        debounceTimer = setTimeout(() => searchCameras(term), 300);
      });
    }

    (document.getElementById('btnLimparFormulario') as HTMLButtonElement | null)?.addEventListener('click', () => {
      (document.getElementById('formManutencaoCamera') as HTMLFormElement).reset();
      (document.getElementById('data_hora') as HTMLInputElement).value = toDateTimeLocalNow();
      clearMessage();
      clearOrdemSelecao();
    });

    (document.getElementById('btnFinalizarManutencao') as HTMLButtonElement | null)?.addEventListener('click', () => clearOrdemSelecao());

    (document.getElementById('btnLimparOs') as HTMLButtonElement | null)?.addEventListener('click', () => {
      const form = document.getElementById('formCriarOs') as HTMLFormElement | null;
      if (!form) return;
      form.reset();
      (document.getElementById('data_hora_os') as HTMLInputElement).value = toDateTimeLocalNow();
      clearMessage();
    });

    const equipamentoOsSelect = document.getElementById('equipamento_id_os') as HTMLSelectElement | null;
    const localServicoOsEl = document.getElementById('local_servico_os') as HTMLInputElement | null;
    const enderecoServicoOsEl = document.getElementById('endereco_servico_os') as HTMLInputElement | null;
    equipamentoOsSelect?.addEventListener('change', () => {
      const cameraId = String(equipamentoOsSelect.value || '');
      const camera = state.cameraOptions.find((item) => String(item.id || '') === cameraId);
      if (camera && (camera as any)?.em_manutencao) {
        const nome = (camera as any).local_nome || (camera as any).descricao || `ID ${camera.id}`;
        const modal = new (window as any).bootstrap.Modal(document.getElementById('modalManutencaoAtiva'));
        document.getElementById('modalManutencaoNome')!.textContent = nome;
        document.getElementById('modalManutencaoOk')!.onclick = () => {
          modal.hide();
          equipamentoOsSelect.value = '';
          if (localServicoOsEl) localServicoOsEl.value = '';
          if (enderecoServicoOsEl) enderecoServicoOsEl.value = '';
        };
        modal.show();
        return;
      }
      if (localServicoOsEl) localServicoOsEl.value = (camera as any)?.local_nome || '';
      if (enderecoServicoOsEl) {
        const parts = [(camera as any)?.tipo_logradouro || '', (camera as any)?.local_logradouro || '', (camera as any)?.local_numero || ''].filter(Boolean);
        enderecoServicoOsEl.value = parts.join(' ');
      }
    });

    (document.getElementById('btnLimparSelecaoOs') as HTMLButtonElement | null)?.addEventListener('click', () => clearOrdemSelecao());

    (document.getElementById('btnFiltrarHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      const cameraId = (document.getElementById('equipamento_id') as HTMLSelectElement).value;
      if (!cameraId) { showMessage('warning', 'Selecione uma câmera para filtrar o histórico.'); return; }
      state.cameraSelecionada = cameraId;
      state.historicoPage = 1;
      await fetchDados();
    });

    (document.getElementById('btnLimparFiltroHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      state.cameraSelecionada = '';
      state.historicoBusca = '';
      state.dataInicial = '';
      state.dataFinal = '';
      (document.getElementById('filtroBuscaHistorico') as HTMLInputElement).value = '';
      (document.getElementById('dataInicial') as HTMLInputElement).value = '';
      (document.getElementById('dataFinal') as HTMLInputElement).value = '';
      state.historicoPage = 1;
      await fetchDados();
    });

    function readHistoricoFilters() {
      state.historicoBusca = String((document.getElementById('filtroBuscaHistorico') as HTMLInputElement).value || '').trim();
      state.dataInicial = (document.getElementById('dataInicial') as HTMLInputElement).value || '';
      state.dataFinal = (document.getElementById('dataFinal') as HTMLInputElement).value || '';
    }

    (document.getElementById('btnBuscarHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      readHistoricoFilters();
      state.historicoPage = 1;
      await fetchDados();
    });

    (document.getElementById('filtroBuscaHistorico') as HTMLInputElement | null)?.addEventListener('keydown', async (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      readHistoricoFilters();
      state.historicoPage = 1;
      await fetchDados();
    });

    (document.getElementById('dataInicial') as HTMLInputElement | null)?.addEventListener('change', async () => {
      readHistoricoFilters();
      state.historicoPage = 1;
      await fetchDados();
    });

    (document.getElementById('dataFinal') as HTMLInputElement | null)?.addEventListener('change', async () => {
      readHistoricoFilters();
      state.historicoPage = 1;
      await fetchDados();
    });

    (document.getElementById('historicoPerPage') as HTMLSelectElement | null)?.addEventListener('change', async (event) => {
      const value = Number((event.target as HTMLSelectElement).value || 20);
      state.historicoPerPage = Number.isNaN(value) ? 20 : value;
      state.historicoPage = 1;
      await fetchDados();
    });

    (document.getElementById('btnExportarCsvHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      try { await exportarCsvHistorico(); } catch (error) { console.error(error); showMessage('danger', (error as Error).message || 'Falha ao exportar CSV.'); }
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

    (document.getElementById('btnExportarRapidoHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      try { await exportarPdfHistorico(); } catch (error) { console.error(error); showMessage('danger', (error as Error).message || 'Falha ao exportar relatório.'); }
    });

    (document.getElementById('btnExportarPdfHistorico') as HTMLButtonElement | null)?.addEventListener('click', async () => {
      try { await exportarPdfHistorico(); } catch (error) { console.error(error); showMessage('danger', (error as Error).message || 'Falha ao exportar PDF.'); }
    });

    document.querySelector('.historico-table-wrap table thead')?.addEventListener('click', async (event) => {
      const button = (event.target as HTMLElement).closest('[data-sort]');
      if (!button) return;
      const sortKey = button.getAttribute('data-sort');
      if (!sortKey) return;
      state.historicoSortBy === sortKey
        ? (state.historicoSortDir = state.historicoSortDir === 'asc' ? 'desc' : 'asc')
        : (state.historicoSortBy = sortKey, state.historicoSortDir = 'asc');
      state.historicoPage = 1;
      await fetchDados();
    });
  }

  async function init() {
    if (!document.body.classList.contains('page-manutencao_cameras')) return;
    (document.getElementById('data_hora') as HTMLInputElement).value = toDateTimeLocalNow();
    const dataHoraOs = document.getElementById('data_hora_os') as HTMLInputElement | null;
    if (dataHoraOs) dataHoraOs.value = toDateTimeLocalNow();
    bindEvents();

    try {
      await fetchDados();
    } catch (error) {
      console.error(error);
      showMessage('danger', (error as Error).message || 'Erro ao carregar dados da manutenção.');
      document.getElementById('historicoManutencaoBody')!.innerHTML = '<tr><td colspan="15" class="text-center text-danger py-4">Falha ao carregar histórico.</td></tr>';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
export {};