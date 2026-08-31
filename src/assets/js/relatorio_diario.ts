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

  function bindMudarSituacao() {
    document.querySelectorAll<HTMLFormElement>('form[action*="action_relatorio_diario"]').forEach((form) => {
      const acao = form.querySelector<HTMLInputElement>('input[name="acao"]')?.value;
      if (acao !== 'mudar_situacao') return;

      const select = form.querySelector<HTMLSelectElement>('select[name="situacao"]');
      if (!select) return;

      select.addEventListener('change', () => {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      });
    });
  }

  function bindExcluirRegistro() {
    document.querySelectorAll<HTMLFormElement>('form[action*="action_relatorio_diario"]').forEach((form) => {
      const acao = form.querySelector<HTMLInputElement>('input[name="acao"]')?.value;
      if (acao !== 'excluir_registro') return;

      form.addEventListener('submit', (event) => {
        if (!window.confirm('Excluir este registro?')) {
          event.preventDefault();
        }
      });
    });
  }

  function limparCampoAposSalvar() {
    document.querySelectorAll<HTMLTextAreaElement>('textarea[data-limpar]').forEach((el) => {
      el.value = '';
      el.removeAttribute('data-limpar');
    });
  }

  // ---- Minhas Anotações (privadas do usuário) ----
  function bindConfirmForms() {
    document.querySelectorAll<HTMLFormElement>('form[data-confirm]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        const msg = form.getAttribute('data-confirm') || 'Tem certeza?';
        if (!window.confirm(msg)) {
          event.preventDefault();
        }
      });
    });
  }

  function bindAnotacaoStatus() {
    document.querySelectorAll<HTMLFormElement>('form[data-anotacao-status]').forEach((form) => {
      const select = form.querySelector<HTMLSelectElement>('select[name="status"]');
      if (!select) return;
      select.addEventListener('change', () => {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      });
    });
  }

  function bindAnotacaoEditar() {
    const editForm = document.getElementById('formEditarAnotacao') as HTMLFormElement | null;
    const idInput = document.getElementById('editarAnotacaoId') as HTMLInputElement | null;
    const tituloInput = document.getElementById('editarAnotacaoTitulo') as HTMLInputElement | null;
    const textoInput = document.getElementById('editarAnotacaoTexto') as HTMLTextAreaElement | null;
    const cancelBtn = document.getElementById('btnCancelarEditarAnotacao') as HTMLButtonElement | null;
    if (!editForm || !idInput || !tituloInput || !textoInput) return;

    document.querySelectorAll<HTMLElement>('[data-anotacao-editar]').forEach((btn) => {
      btn.addEventListener('click', () => {
        idInput.value = btn.getAttribute('data-id') || '';
        tituloInput.value = btn.getAttribute('data-titulo') || '';
        textoInput.value = btn.getAttribute('data-texto') || '';
        editForm.classList.remove('d-none');
        tituloInput.focus();
        editForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    });

    const esconderForm = () => {
      editForm.classList.add('d-none');
      idInput.value = '';
      tituloInput.value = '';
      textoInput.value = '';
    };

    cancelBtn?.addEventListener('click', esconderForm);
  }

  function bindListaComHifen() {
    ['situacaoGeral', 'passagemTurno'].forEach((id) => {
      const ta = document.getElementById(id) as HTMLTextAreaElement | null;
      if (!ta) return;

      ta.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) return;

        event.preventDefault();
        const start = ta.selectionStart ?? ta.value.length;
        const end = ta.selectionEnd ?? start;
        const prefixo = '\n- ';
        ta.value = ta.value.slice(0, start) + prefixo + ta.value.slice(end);
        const pos = start + prefixo.length;
        ta.setSelectionRange(pos, pos);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  }

  function showSucessoToast() {
    const toastEl = document.getElementById('sucessoToast');
    if (!toastEl || typeof window.bootstrap === 'undefined' || !window.bootstrap.Toast) return;
    const toast = new window.bootstrap.Toast(toastEl, { delay: 5000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadEquipamentos();
    bindOsSelect();
    bindMudarSituacao();
    bindExcluirRegistro();
    limparCampoAposSalvar();
    bindListaComHifen();
    bindConfirmForms();
    bindAnotacaoStatus();
    bindAnotacaoEditar();
    showSucessoToast();
  });
})();