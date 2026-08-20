import './utils/fetchWithTimeout';
import { escapeHtml } from './utils/html';

(function () {
  function getApiBase() {
    return window.APP_API_BASE;
  }

  async function loadEquipamentos() {
    const select = document.getElementById('regEquipamento') as HTMLSelectElement | null;
    if (!select) return;

    select.innerHTML = '<option value="">Carregando...</option>';

    try {
      const qs = new URLSearchParams();
      qs.set('page_num', '1');
      qs.set('per_page', '200');

      const response = await fetchWithTimeout(`${getApiBase()}api_cameras&${qs.toString()}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store'
      });
      if (!response.ok) {
        throw new Error('Erro ao carregar equipamentos');
      }

      const payload = await response.json() as { success: boolean; error?: string; data?: Array<Record<string, unknown>> };
      if (!payload.success) {
        throw new Error(payload.error || 'Erro ao carregar equipamentos');
      }

      const list = Array.isArray(payload.data) ? payload.data : [];
      let html = '<option value="">Nenhum</option>';
      for (const item of list) {
        const id = item.id;
        const patrimonio = String(item.patrimonio || '').trim();
        const descricao = String(item.descricao || '').trim();
        const label = [patrimonio, descricao].filter(Boolean).join(' - ') || `Equipamento ${id}`;
        html += `<option value="${escapeHtml(String(id))}">#${escapeHtml(String(id))} - ${escapeHtml(label)}</option>`;
      }
      select.innerHTML = html;
    } catch (e) {
      console.error('Erro ao carregar equipamentos:', e);
      select.innerHTML = '<option value="">Erro ao carregar equipamentos</option>';
    }
  }

  function bindOsSelect() {
    const osSelect = document.getElementById('regOs') as HTMLSelectElement | null;
    const osTipoHidden = document.getElementById('osTipoHidden') as HTMLInputElement | null;
    const numeroOsHidden = document.getElementById('numeroOsHidden') as HTMLInputElement | null;
    if (!osSelect || !osTipoHidden || !numeroOsHidden) return;

    osSelect.addEventListener('change', () => {
      const option = osSelect.selectedOptions[0] as HTMLOptionElement | null;
      osTipoHidden.value = option?.dataset?.osTipo || '';
      numeroOsHidden.value = option?.dataset?.numeroOs || '';
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadEquipamentos();
    bindOsSelect();
  });
})();