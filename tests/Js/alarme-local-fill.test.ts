import { describe, it, expect, beforeEach } from 'vitest'
import { preencherCamposLocal } from '../../src/assets/js/utils/alarme-local-fill'

beforeEach(() => {
  document.body.innerHTML = `
    <input id="endereco" />
    <input id="numero" />
    <input id="bairro" />
    <input id="cidade" />
    <input id="uf" />
    <input id="cep" />
    <input id="local_id" />
    <select name="regiao">
      <option value="1">CENTRO</option>
      <option value="2">NORTE</option>
      <option value="3">SUL</option>
    </select>
  `
})

describe('preencherCamposLocal', () => {
  it('fills all address fields', () => {
    preencherCamposLocal({
      logradouro: 'Rua Principal',
      numero: '100',
      bairro: 'Centro',
      cidade: 'Londrina',
      uf: 'PR',
      cep: '86010-000',
      id: '42',
    })

    expect((document.getElementById('endereco') as HTMLInputElement).value).toBe('Rua Principal')
    expect((document.getElementById('numero') as HTMLInputElement).value).toBe('100')
    expect((document.getElementById('bairro') as HTMLInputElement).value).toBe('Centro')
    expect((document.getElementById('cidade') as HTMLInputElement).value).toBe('Londrina')
    expect((document.getElementById('uf') as HTMLInputElement).value).toBe('PR')
    expect((document.getElementById('cep') as HTMLInputElement).value).toBe('86010-000')
    expect((document.getElementById('local_id') as HTMLInputElement).value).toBe('42')
  })

  it('sets regiao select by regiao_id', () => {
    preencherCamposLocal({
      regiao_id: 2,
    })

    const select = document.querySelector('[name="regiao"]') as HTMLSelectElement
    expect(select.value).toBe('2')
  })

  it('sets regiao select by regiao name if regiao_id not present', () => {
    preencherCamposLocal({
      regiao: 'SUL',
    })

    const select = document.querySelector('[name="regiao"]') as HTMLSelectElement
    expect(select.value).toBe('3')
  })

  it('does nothing for missing fields', () => {
    preencherCamposLocal({})

    expect((document.getElementById('endereco') as HTMLInputElement).value).toBe('')
    expect((document.getElementById('numero') as HTMLInputElement).value).toBe('')
    expect((document.getElementById('bairro') as HTMLInputElement).value).toBe('')
  })

  it('handles null values gracefully', () => {
    preencherCamposLocal({
      logradouro: null,
      numero: null,
      bairro: null,
    })

    expect((document.getElementById('endereco') as HTMLInputElement).value).toBe('')
    expect((document.getElementById('numero') as HTMLInputElement).value).toBe('')
  })
})
