(function (): void {
  let formDirty = false;
  let savedAlarmeId: string | null = null;

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

  function resetForm(): void {
    const form = document.getElementById('formCadastroAlarme') as HTMLFormElement;
    if (!form) return;
    form.reset();
    form.classList.remove('was-validated');
    formDirty = false;

    const btn = document.getElementById('btnSubmit') as HTMLButtonElement;
    const btnFinalizar = document.getElementById('btnFinalizar');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save me-1"></i>Salvar';
    }
    if (btnFinalizar) {
      btnFinalizar.classList.add('d-none');
    }

    const anexosSection = document.getElementById('anexosSection');
    if (anexosSection) {
      anexosSection.classList.remove('anexos-section-visible');
      const list = anexosSection.querySelector('.anexos-list');
      if (list) {
        list.removeAttribute('data-equipamento-id');
        list.removeAttribute('data-alarme-id');
      }
    }

    savedAlarmeId = null;

    window.showSuccessModal('Cadastro Finalizado!', 'O cadastro do alarme foi concluído com sucesso.');
  }

  async function handleSubmit(event: Event): Promise<void> {
    const form = event.currentTarget as HTMLFormElement;
    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    const btn = document.getElementById('btnSubmit') as HTMLButtonElement;
    const payload = formToPayload(form);
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    try {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Salvando...';
      const response = await fetchWithTimeout(`${getApiBase()}api_cadastrar_alarmes`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
          Accept: 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || 'Falha ao cadastrar alarme.');
      }

      formDirty = false;
      savedAlarmeId = result.data?.resource?.alarme_id;

      const alarmMsg = result.data?.resource?.message || 'Alarme cadastrado com sucesso!';
      window.showSuccessModal('Cadastro Realizado!', alarmMsg);
      window.showToast('Agora você pode anexar imagens se desejar.', 'success');

      if (savedAlarmeId) {
        const anexosSection = document.getElementById('anexosSection');
        if (anexosSection) {
          anexosSection.classList.add('anexos-section-visible');
          const list = anexosSection.querySelector('.anexos-list');
          if (list) list.setAttribute('data-alarme-id', savedAlarmeId);
          if (window.initAnexoSection) {
            window.initAnexoSection(anexosSection);
          }
          anexosSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Salvo';

      const btnFinalizar = document.getElementById('btnFinalizar');
      if (btnFinalizar) {
        btnFinalizar.classList.remove('d-none');
      }
    } catch (error) {
      window.showToast((error as Error).message || 'Erro ao cadastrar alarme.', 'danger');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save me-1"></i>Salvar';
    }
  }

  function init(): void {
    if (!document.body.classList.contains('page-cadastro_alarmes')) return;
    const form = document.getElementById('formCadastroAlarme') as HTMLFormElement;
    if (!form) return;
    if (window.aplicarUppercaseUniversal) {
      window.aplicarUppercaseUniversal(form);
    }
    setupCharCounters();
    setupDirtyTracking(form);
    setupBeforeUnload();
    form.addEventListener('submit', handleSubmit);
    document.getElementById('btnFinalizar')?.addEventListener('click', resetForm);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
export {};