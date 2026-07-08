/**
 * Preenche os campos de endereco do formulario de alarme
 * a partir de um item selecionado no autocomplete de locais.
 *
 * Centraliza a logica antes duplicada entre cadastro_alarmes.ts
 * e editar_alarmes.ts (DRY).
 */
export function preencherCamposLocal(d: Record<string, unknown>): void {
  const setVal = (id: string, val: unknown) => {
    const el = document.getElementById(id) as HTMLInputElement | null
    if (el && val) el.value = String(val)
  }
  setVal('endereco', d.logradouro ?? '')
  setVal('numero', d.numero ?? '')
  setVal('bairro', d.bairro ?? '')
  setVal('cidade', d.cidade ?? '')
  setVal('uf', d.uf ?? '')
  setVal('cep', d.cep ?? '')
  setVal('local_id', d.id ?? '')

  const regiaoSelect = document.querySelector('[name="regiao"]') as HTMLSelectElement | null
  if (regiaoSelect && d.regiao_id) {
    const opt = Array.from(regiaoSelect.options).find(o => o.value === String(d.regiao_id) || o.textContent === String(d.regiao_nome ?? ''))
    if (opt) regiaoSelect.value = opt.value
  } else if (regiaoSelect && d.regiao) {
    const opt = Array.from(regiaoSelect.options).find(o => o.textContent === String(d.regiao))
    if (opt) regiaoSelect.value = opt.value
  }
}
