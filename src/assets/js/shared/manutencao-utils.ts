// Shared utilities for maintenance pages (câmeras & alarmes)
// Assigns global functions to window for use by page-specific scripts

(function () {
  // === Date/Time Utilities ===
  window.toBrDate = function toBrDate(value: unknown): string {
    if (!value) return '-';
    const dt = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(dt.getTime())) return String(value);
    return dt.toLocaleString('pt-BR');
  };

  window.toDateTimeLocalNow = function toDateTimeLocalNow(): string {
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
  };

  window.toIsoDateStamp = function toIsoDateStamp(): string {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}`;
  };

  window.toDateOnlyBr = function toDateOnlyBr(value: unknown): string {
    if (!value) return '-';
    const dt = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(dt.getTime()) ? String(value) : dt.toLocaleDateString('pt-BR');
  };

  window.toTimeOnlyBr = function toTimeOnlyBr(value: unknown): string {
    if (!value) return '-';
    const dt = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(dt.getTime()) ? String(value) : dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' } as Intl.DateTimeFormatOptions);
  };

  // === String Utilities ===
  window.escapeHtml = function escapeHtml(value: unknown): string {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  window.csvEscape = function csvEscape(value: unknown): string {
    return `"${String(value ?? '').replace(/"/g, '""')}"`;
  };

  // === Message Utilities ===
  window.showMessage = function showMessage(type: string, text: string): void {
    window.showToast(text, type as 'success' | 'danger' | 'warning' | 'info');
  };

  window.clearMessage = function clearMessage(): void {
    // no-op: toasts auto-hide
  };

  // === Select/Render Utilities ===
  window.renderSelectOptions = function renderSelectOptions(selectId: string, data: Array<Record<string, unknown>> | null | undefined, includeEmptyLabel: string | false | undefined): void {
    const select = document.getElementById(selectId) as HTMLSelectElement | null;
    if (!select) return;
    const baseOption = includeEmptyLabel ? `<option value="">${escapeHtml(includeEmptyLabel)}</option>` : '';
    const options = (data || []).map((item) => {
      return `<option value="${escapeHtml(String(item.id))}">${escapeHtml(String(item.nome || ''))}</option>`;
    }).join('');
    select.innerHTML = baseOption + options;
  };

  window.hasSelectableOptions = function hasSelectableOptions(selectId: string): boolean {
    const select = document.getElementById(selectId) as HTMLSelectElement | null;
    if (!select) return false;
    return select.options.length > 1;
  };

  window.ensureSelectOption = function ensureSelectOption(select: HTMLSelectElement | null, value: string, label: string): void {
    if (!select || !value) return;
    const existing = Array.from(select.options).find((option) => option.value === value);
    if (existing) {
      select.value = value;
      return;
    }
    const option = new Option(label || value, value, true, true);
    select.add(option);
    select.value = value;
  };

  // === Scroll Sync ===
  let _historicoScrollStyle: HTMLStyleElement | null = null;

  function _setHistoricoScrollWidth(width: number): void {
    if (!_historicoScrollStyle) {
      _historicoScrollStyle = document.createElement('style');
      document.head.appendChild(_historicoScrollStyle);
    }
    _historicoScrollStyle.textContent = `#historicoTopScrollContent { width: ${width}px !important; }`;
  }

  window.syncHistoricoTopScroll = function syncHistoricoTopScroll(): void {
    const top = document.getElementById('historicoTopScroll');
    const topContent = document.getElementById('historicoTopScrollContent');
    const bottom = document.querySelector('.historico-table-wrap');
    const table = bottom?.querySelector('table');
    if (!top || !topContent || !bottom || !table) return;

    const totalWidth = table.scrollWidth;
    const visibleWidth = bottom.clientWidth;
    _setHistoricoScrollWidth(totalWidth);
    top.classList.toggle('d-none', totalWidth <= visibleWidth);
    top.scrollLeft = bottom.scrollLeft;
  };

  window.setupHistoricoDualScroll = function setupHistoricoDualScroll(): void {
    const top = document.getElementById('historicoTopScroll');
    const bottom = document.querySelector('.historico-table-wrap');
    if (!top || !bottom) return;

    if (!top.dataset.bound) {
      top.addEventListener('scroll', () => {
        bottom.scrollLeft = top.scrollLeft;
      });
      bottom.addEventListener('scroll', () => {
        top.scrollLeft = bottom.scrollLeft;
      });
      window.addEventListener('resize', window.syncHistoricoTopScroll);
      top.dataset.bound = '1';
    }

    window.syncHistoricoTopScroll();
  };

  // === Selection Utilities ===
  window.updateSelectedCount = function updateSelectedCount(selectedIds: Set<string>): void {
    const el = document.getElementById('resumoSelecaoHistorico');
    if (!el) return;
    el.textContent = `Selecionados: ${selectedIds.size}`;
  };

  window.updateSelectAllCheckbox = function updateSelectAllCheckbox(selectedIds: Set<string>, historico: Array<Record<string, unknown>>): void {
    const selectAll = document.getElementById('selectAllHistorico') as HTMLInputElement | null;
    if (!selectAll) return;
    const visibleIds = historico.map((item) => String(item.id || ''));
    if (visibleIds.length === 0) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
      return;
    }
    const selectedVisible = visibleIds.filter((id) => selectedIds.has(id));
    selectAll.checked = selectedVisible.length === visibleIds.length;
    selectAll.indeterminate = selectedVisible.length > 0 && selectedVisible.length < visibleIds.length;
  };

  // === Pagination & Sorting ===
  window.renderPaginacao = function renderPaginacao(pagination: Record<string, unknown> | null | undefined, onPageChange: (page: number) => void): void {
    const container = document.getElementById('historicoPaginacao');
    if (!container) return;

    const totalPages = Number((pagination as any)?.total_pages || 1);
    const current = Number((pagination as any)?.page || 1);

    if (totalPages <= 1) {
      container.innerHTML = '';
      return;
    }

    const itens: string[] = [];
    itens.push(`<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}">Anterior</a></li>`);
    for (let i = 1; i <= totalPages; i += 1) {
      if (i === 1 || i === totalPages || Math.abs(i - current) <= 2) {
        itens.push(`<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
      }
    }
    itens.push(`<li class="page-item ${current >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}">Próxima</a></li>`);
    container.innerHTML = itens.join('');

    container.querySelectorAll('a.page-link[data-page]').forEach((el) => {
      el.addEventListener('click', (event) => {
        event.preventDefault();
        const page = Number(el.getAttribute('data-page'));
        if (Number.isNaN(page) || page < 1 || page > totalPages) return;
        onPageChange(page);
      });
    });
  };

  window.renderSortIndicators = function renderSortIndicators(sortBy: string, sortDir: string): void {
    document.querySelectorAll('[data-sort]').forEach((btn) => {
      const key = btn.getAttribute('data-sort');
      btn.classList.toggle('is-active', key === sortBy);
    });

    document.querySelectorAll('[data-sort-indicator]').forEach((el) => {
      const key = el.getAttribute('data-sort-indicator');
      if (key === sortBy) {
        el.textContent = sortDir === 'asc' ? '↑' : '↓';
      } else {
        el.textContent = '↕';
      }
    });
  };
})();
