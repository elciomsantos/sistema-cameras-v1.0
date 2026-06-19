import { escapeHtml } from './utils/html';

(function (): void {
  const state: { page: number; perPage: number } = {
    page: 1,
    perPage: 20
  };

  function getApiBase(): string {
    return window.APP_API_BASE || '';
  }

  function getBadgeClass(operacao: string): string {
    if (operacao === 'INSERT') return 'bg-success';
    if (operacao === 'UPDATE') return 'bg-warning text-dark';
    if (operacao === 'DELETE') return 'bg-danger';
    return 'bg-secondary';
  }

  function toBrDate(value: unknown): string {
    if (!value) return '-';
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(value);
    return d.toLocaleString('pt-BR');
  }

  function buildQuery(): string {
    const form = document.getElementById('formAuditoria') as HTMLFormElement;
    if (!form) return '';
    const formData = new FormData(form);
    const params = new URLSearchParams();
    params.set('page_num', String(state.page));
    params.set('per_page', String(state.perPage));

    for (const [key, rawValue] of formData.entries()) {
      const value = String(rawValue || '').trim();
      if (value !== '') {
        params.set(key, value);
      }
    }
    return params.toString();
  }

  function renderRows(rows: Array<Record<string, unknown>> | null | undefined): void {
    const tbody = document.getElementById('auditoriaTabelaCorpo');
    if (!tbody) return;
    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map((item: Record<string, unknown>) => {
      const usuario: string = (item.usuario_nome as string) || (item.usuario_login as string) || 'Sistema';
      return `
        <tr>
          <td>${escapeHtml(toBrDate(item.created_at))}</td>
          <td>${escapeHtml(usuario)}</td>
          <td><span class="badge badge-op ${getBadgeClass(item.operacao as string)}">${escapeHtml(item.operacao as string || '-')}</span></td>
          <td>${escapeHtml(item.codigo_publico as string || '-')}<br><small class="text-muted">ID ${escapeHtml(String(item.equipamento_id || '-'))} | ${escapeHtml(item.numero_serie as string || '-')} | ${escapeHtml(item.ip as string || '-')}</small></td>
          <td>${escapeHtml(item.origem as string || '-')}</td>
          <td>${escapeHtml(item.resumo as string || '-')}</td>
        </tr>
      `;
    }).join('');
  }

  function renderPagination(pagination: Record<string, unknown> | null | undefined): void {
    const container = document.getElementById('auditoriaPaginacao');
    if (!container) return;
    const totalPages = Number((pagination as Record<string, unknown>)?.total_pages || 1);
    const current = Number((pagination as Record<string, unknown>)?.page || 1);

    if (totalPages <= 1) {
      container.innerHTML = '';
      return;
    }

    const items: string[] = [];
    items.push(`<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}">Anterior</a></li>`);
    for (let i = 1; i <= totalPages; i += 1) {
      if (i === 1 || i === totalPages || Math.abs(i - current) <= 2) {
        items.push(`<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
      }
    }
    items.push(`<li class="page-item ${current >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}">Próxima</a></li>`);
    container.innerHTML = items.join('');

    container.querySelectorAll('a.page-link[data-page]').forEach((el) => {
      el.addEventListener('click', (event: Event) => {
        event.preventDefault();
        const page = Number((el as HTMLElement).getAttribute('data-page'));
        if (!Number.isNaN(page) && page >= 1 && page <= totalPages) {
          state.page = page;
          fetchAuditoria();
        }
      });
    });
  }

  async function fetchAuditoria(): Promise<void> {
    const totalEl = document.getElementById('totalAuditoria');
    if (!totalEl) return;
    totalEl.textContent = 'Carregando...';

    try {
      const query = buildQuery();
      const response = await fetchWithTimeout(`${getApiBase()}api_auditoria_cameras&${query}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      if (!response.ok) {
        throw new Error('Falha ao carregar auditoria');
      }
      const payload = await response.json();

      if (!payload.success) {
        throw new Error(payload.error || 'Falha ao carregar auditoria');
      }

      renderRows(payload.data || []);
      renderPagination(payload.pagination || {});

      const total = Number((payload.pagination as Record<string, unknown>)?.total || 0);
      totalEl.textContent = `Total de registros: ${total}`;
    } catch (error) {
      const tbody = document.getElementById('auditoriaTabelaCorpo');
      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Erro ao carregar auditoria.</td></tr>';
      }
      const pag = document.getElementById('auditoriaPaginacao');
      if (pag) pag.innerHTML = '';
      if (totalEl) totalEl.textContent = 'Falha ao consultar auditoria.';
      console.error(error);
    }
  }

  function bindEvents(): void {
    const form = document.getElementById('formAuditoria') as HTMLFormElement;
    if (!form) return;
    form.addEventListener('submit', (event: Event) => {
      event.preventDefault();
      state.page = 1;
      fetchAuditoria();
    });

    const btnLimpar = document.getElementById('btnLimparAuditoria');
    if (btnLimpar) {
      btnLimpar.addEventListener('click', () => {
        form.reset();
        state.page = 1;
        fetchAuditoria();
      });
    }
  }

  function onReady(): void {
    if (!document.body.classList.contains('page-auditoria_cameras')) return;
    bindEvents();
    fetchAuditoria();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }
})();
export {};