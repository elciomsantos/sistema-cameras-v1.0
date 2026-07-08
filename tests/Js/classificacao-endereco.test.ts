import { describe, it, expect } from 'vitest'
import { CLASSIFICACAO_MAP, CLASSIFICACOES } from '../../src/assets/js/utils/classificacao-endereco'

describe('CLASSIFICACAO_MAP', () => {
  it('maps RUA to 1', () => {
    expect(CLASSIFICACAO_MAP['RUA']).toBe(1)
  })

  it('maps AVENIDA to 2', () => {
    expect(CLASSIFICACAO_MAP['AVENIDA']).toBe(2)
  })

  it('maps OUTRO to 10', () => {
    expect(CLASSIFICACAO_MAP['OUTRO']).toBe(10)
  })

  it('has exactly 10 entries', () => {
    expect(Object.keys(CLASSIFICACAO_MAP).length).toBe(10)
  })
})

describe('CLASSIFICACOES', () => {
  it('has 10 items', () => {
    expect(CLASSIFICACOES.length).toBe(10)
  })

  it('each item has id and descricao', () => {
    for (const item of CLASSIFICACOES) {
      expect(item).toHaveProperty('id')
      expect(item).toHaveProperty('descricao')
      expect(typeof item.id).toBe('number')
      expect(typeof item.descricao).toBe('string')
    }
  })

  it('maps id 1 to RUA', () => {
    const rua = CLASSIFICACOES.find(c => c.id === 1)
    expect(rua?.descricao).toBe('RUA')
  })

  it('is consistent with CLASSIFICACAO_MAP', () => {
    for (const item of CLASSIFICACOES) {
      expect(CLASSIFICACAO_MAP[item.descricao]).toBe(item.id)
    }
  })
})
