export function setSelectByText(selectId: string, text: string): void {
  const sel = document.getElementById(selectId) as HTMLSelectElement | null;
  if (!sel || !text) return;
  const upper = text.toUpperCase().trim();
  for (let i = 0; i < sel.options.length; i++) {
    if (sel.options[i].text.toUpperCase().trim() === upper) {
      sel.selectedIndex = i;
      return;
    }
  }
}

export function initTemAlarmeToggle(): void {
  const chk = document.getElementById('tem_alarme') as HTMLInputElement | null;
  const wrapper = document.getElementById('alarmeContaWrapper') as HTMLElement | null;
  if (!chk || !wrapper) return;
  chk.addEventListener('change', () => {
    wrapper.style.display = chk.checked ? 'block' : 'none';
    const select = wrapper.querySelector('select') as HTMLSelectElement | null;
    if (select) select.required = chk.checked;
  });
}

export function initCepInput(): HTMLInputElement | null {
  const cepInput = document.getElementById('cep') as HTMLInputElement | null;
  if (!cepInput) return null;

  cepInput.addEventListener('input', (e: Event) => {
    const target = e.target as HTMLInputElement;
    let value = target.value.replace(/\D/g, '').substring(0, 8);
    target.value = value.length > 5 ? value.substring(0, 5) + '-' + value.substring(5) : value;

    const raw = value;
    if (raw.length === 0 || raw.length < 8) {
      const logradouro = document.getElementById('logradouro') as HTMLInputElement | null;
      const bairro = document.getElementById('bairro') as HTMLInputElement | null;
      const cidade = document.getElementById('cidade') as HTMLInputElement | null;
      if (logradouro) logradouro.value = '';
      if (bairro) bairro.value = '';
      if (cidade) cidade.value = '';
    }
  });

  return cepInput;
}

export function initBuscarCep(cepInput: HTMLInputElement, apiBase: string): void {
  function buscarCep(): void {
    const cep = cepInput.value.replace(/\D/g, '');
    if (cep.length !== 8) {
      if (typeof window.showToast === 'function') window.showToast('Informe um CEP valido com 8 digitos.', 'warning');
      cepInput.focus();
      return;
    }

    fetch(apiBase + 'api_cep_lookup&cep=' + cep)
      .then(r => r.json())
      .then(data => {
        if (data.success && data.data) {
          const d = data.data;
          const logradouroCompleto = (d.logradouro || '').trim();
          const partes = logradouroCompleto.split(/\s+/);
          const tipo = partes.length > 1 ? partes[0].replace('.', '').toUpperCase() : '';
          const via = partes.length > 1 ? partes.slice(1).join(' ') : logradouroCompleto;

          if (tipo) setSelectByText('classificacao_endereco_id', tipo);

          const logrEl = document.getElementById('logradouro') as HTMLInputElement | null;
          const bairroEl = document.getElementById('bairro') as HTMLInputElement | null;
          const cidadeEl = document.getElementById('cidade') as HTMLInputElement | null;
          const ufSelect = document.getElementById('uf') as HTMLSelectElement | null;

          if (logrEl) logrEl.value = (via || '').toUpperCase();
          if (bairroEl) bairroEl.value = (d.bairro || '').toUpperCase();
          if (cidadeEl) cidadeEl.value = (d.cidade || '').toUpperCase();
          if (ufSelect && d.uf) {
            for (let i = 0; i < ufSelect.options.length; i++) {
              if (ufSelect.options[i].value === d.uf) {
                ufSelect.selectedIndex = i;
                break;
              }
            }
          }
          if (typeof window.showToast === 'function') window.showToast('Endereco preenchido a partir do CEP.', 'success');
        } else {
          if (typeof window.showToast === 'function') window.showToast('CEP nao encontrado.', 'warning');
        }
      })
      .catch(err => {
        console.error('CEP lookup failed:', err);
        if (typeof window.showToast === 'function') window.showToast('Erro ao consultar CEP.', 'danger');
      });
  }

  document.getElementById('btnBuscarCep')?.addEventListener('click', buscarCep);
  cepInput.addEventListener('blur', buscarCep);
}

export function initGeocode(apiBase: string): void {
  const btnGeo = document.getElementById('btnBuscarCoordenadas') as HTMLButtonElement | null;
  if (!btnGeo) return;

  btnGeo.addEventListener('click', () => {
    const getVal = (id: string): string => (document.getElementById(id) as HTMLInputElement | null)?.value || '';

    const tipoLog = getVal('classificacao_endereco_id');
    let tipoText = '';
    const sel = document.getElementById('classificacao_endereco_id') as HTMLSelectElement | null;
    if (sel) {
      for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === tipoLog) {
          tipoText = sel.options[i].text;
          break;
        }
      }
    }

    const logradouro = getVal('logradouro');
    const numero = getVal('numero');
    const bairro = getVal('bairro');
    const cidade = getVal('cidade');
    const uf = getVal('uf');

    if (!logradouro || !bairro || !cidade || !uf) {
      if (typeof window.showToast === 'function') {
        window.showToast('Preencha logradouro, bairro, cidade e UF antes de buscar coordenadas.', 'warning');
      }
      return;
    }

    const query = [tipoText, logradouro, numero, bairro, cidade, uf, 'Brasil'].filter(Boolean).join(', ');

    btnGeo.disabled = true;
    btnGeo.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch(apiBase + 'api_geocode&q=' + encodeURIComponent(query))
      .then(r => r.json())
      .then(data => {
        if (data.success && data.data && data.data.lat && data.data.lon) {
          const latEl = document.getElementById('latitude') as HTMLInputElement | null;
          const lonEl = document.getElementById('longitude') as HTMLInputElement | null;
          if (latEl) latEl.value = Number(data.data.lat).toFixed(6);
          if (lonEl) lonEl.value = Number(data.data.lon).toFixed(6);
          if (typeof window.showToast === 'function') {
            window.showToast('Coordenadas preenchidas automaticamente.', 'success');
          }
        } else if (typeof window.showToast === 'function') {
          window.showToast('Endereco nao encontrado para geolocalizacao.', 'warning');
        }
      })
      .catch(err => {
        console.error('Geocode failed:', err);
        if (typeof window.showToast === 'function') {
          window.showToast('Erro ao buscar coordenadas.', 'danger');
        }
      })
      .finally(() => {
        btnGeo.disabled = false;
        btnGeo.innerHTML = '<i class="fas fa-map-pin me-1"></i>Geo';
      });
  });
}
