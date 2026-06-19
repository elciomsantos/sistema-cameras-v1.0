import { escapeHtml } from './utils/html';

(function (): void {
  let alarmes: Array<Record<string, unknown>> = [];
  let formDirty = false;

  function getApiBase(): string {
    return window.APP_API_BASE || '';
  }

  function formToPayload(form: HTMLFormElement): Record<string, string> {
    const data: Record<string, string> = {};
    const fd = new FormData(form);
    for (const [k, v] of fd.entries()) {
      const value = String(v || '').trim();
      if (value === '') continue;
      data[k] = value;
    }
    return data;
  }

  function fillForm(alarme: Record<string, unknown> | null): void {
    const form = document.getElementById('formEditarAlarme') as HTMLFormElement;
    if (!form || !alarme) return;

    const fields = form.querySelectorAll('[name]');
    fields.forEach((field) => {
      const name = field.getAttribute('name');
      if (!name) return;
      const value: unknown = alarme[name] ?? '';
      if (field.tagName === 'SELECT') {
        const option = Array.from((field as HTMLSelectElement).options).find((o: HTMLOptionElement) => String(o.value) === String(value));
        (field as HTMLSelectElement).value = option ? String(value) : '';
      } else {
        (field as HTMLInputElement).value = String(value);
      }
    });

    const idField = document.getElementById('alarme_id') as HTMLInputElement;
    if (idField) idField.value = String(alarme.id || '');

    const anexosSection = document.getElementById('anexosSection');
    if (anexosSection) {
      const anexosList = anexosSection.querySelector('.anexos-list');
      if (anexosList) {
        anexosList.setAttribute('data-alarme-id', String(alarme.id || ''));
      }
      if (alarme.id) {
        if (window.initAnexoSection) {
          window.initAnexoSection(anexosSection);
        }
      } else {
        if (anexosList) anexosList.innerHTML = '';
      }
    }

    formDirty = false;
  }

  function setupCharCounters(): void {
    document.querySelectorAll('.char-counter').forEach((el) => {
      const target = document.querySelector((el as HTMLElement).getAttribute('data-target') || '');
      if (!target) return;
      const update = () => { target.textContent = String((el as HTMLInputElement).value.length); };
      el.addEventListener('input', update);
      update();
    });
  }

  function setupDirtyTracking(form: HTMLFormElement): void {
    form.querySelectorAll('input, select, textarea').forEach((el) => {
      el.addEventListener('change', () => { formDirty = true; });
      el.addEventListener('input', () => { formDirty = true; });
    });
  }

  function setupBeforeUnload(): void {
    window.addEventListener('beforeunload', (e: BeforeUnloadEvent) => {
      if (!formDirty) return;
      e.preventDefault();
      e.returnValue = '';
    });
  }

  function renderSelector(rows: Array<Record<string, unknown>>): void {
    const select = document.getElementById('alarmeSelector') as HTMLSelectElement;
    if (!select) return;

    select.innerHTML = '<option value="">Selecione...</option>' + rows.map((row: Record<string, unknown>) => {
      const label = `${row.id} - CONTA ${row.conta || '-'} - ${row.local || 'SEM LOCAL'} (${row.status || 'SEM STATUS'})`;
      return `<option value="${escapeHtml(row.id)}">${escapeHtml(label)}</option>`;
    }).join('');
  }

  async function loadAlarmes(extraParams: string = ''): Promise<void> {
    const select = document.getElementById('alarmeSelector') as HTMLSelectElement;
    if (select) select.innerHTML = '<option value="">Carregando alarmes...</option>';

    const response = await fetchWithTimeout(`${getApiBase()}api_alarmes&per_page=100${extraParams}`, { credentials: 'same-origin' });
    if (!response.ok) {
      throw new Error('Erro ao carregar alarmes.');
    }
    const payload = await response.json();
    if (!payload.success) {
      throw new Error(payload.error || 'Erro ao carregar alarmes.');
    }
    alarmes = Array.isArray(payload.data) ? payload.data : [];
    renderSelector(alarmes);
  }

  async function handleSave(event: Event): Promise<void> {
    const form = event.currentTarget as HTMLFormElement;
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    const id = (document.getElementById('alarme_id') as HTMLInputElement)?.value;
    if (!id) {
      window.showToast('Selecione um alarme para editar.', 'warning');
      return;
    }

    const payload = formToPayload(form) as Record<string, string>;
    payload.id = id;

    const btn = document.getElementById('btnSubmit') as HTMLButtonElement;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const originalHtml = btn.innerHTML;

    try {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Salvando...';
      const response = await fetchWithTimeout(`${getApiBase()}api_editar_alarme`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      if (!response.ok) {
        throw new Error('Falha ao atualizar alarme.');
      }
      const result = await response.json();
      if (!result.success) {
        throw new Error(result.message || 'Falha ao atualizar alarme.');
      }

      formDirty = false;
      window.showToast(result.message || 'Alarme atualizado com sucesso.', 'success');
    } catch (error) {
      handleApiError(error, 'Erro ao atualizar alarme.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }

  async function init(): Promise<void> {
    if (!document.body.classList.contains('page-editar_alarmes')) return;

    const form = document.getElementById('formEditarAlarme') as HTMLFormElement;
    const select = document.getElementById('alarmeSelector') as HTMLSelectElement;
    const btnBuscar = document.getElementById('btnBuscar');
    const filtroNome = document.getElementById('filtroNome') as HTMLInputElement;
    const filtroConta = document.getElementById('filtroConta') as HTMLInputElement;

    if (window.aplicarUppercaseUniversal) {
      window.aplicarUppercaseUniversal(form);
    }
    setupCharCounters();
    setupDirtyTracking(form);
    setupBeforeUnload();

    try {
      await loadAlarmes();

      const urlId = new URLSearchParams(window.location.search).get('id');
      if (urlId) {
        select.value = urlId;
        const found = alarmes.find((a: Record<string, unknown>) => String(a.id) === String(urlId));
        if (found) fillForm(found);
      }
    } catch (error) {
      handleApiError(error, 'Falha ao carregar alarmes.');
    }

    select?.addEventListener('change', () => {
      const id = select.value;
      const found = alarmes.find((a: Record<string, unknown>) => String(a.id) === String(id));
      fillForm(found || null);
    });

    btnBuscar?.addEventListener('click', async () => {
      const nome = String(filtroNome?.value || '').trim();
      const conta = String(filtroConta?.value || '').trim();
      const qs = new URLSearchParams();
      if (nome) qs.set('nome', nome);
      if (conta) qs.set('conta', conta);
      const params = qs.toString() ? `&${qs.toString()}` : '';
      try {
        await loadAlarmes(params);
        if (alarmes.length === 0) {
          window.showToast('Nenhum alarme encontrado.', 'warning');
        } else if (alarmes.length === 1) {
          select.value = String(alarmes[0].id);
          fillForm(alarmes[0]);
        }
      } catch (error) {
        handleApiError(error, 'Erro ao buscar alarmes.');
      }
    });

    form?.addEventListener('submit', handleSave);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
export {};