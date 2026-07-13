import { describe, it, expect } from 'vitest'
import { CLASSIFICACAO_MAP } from '../../src/assets/js/utils/classificacao-endereco'

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

  it('all values are numeric ids from 1 to 10', () => {
    const values = Object.values(CLASSIFICACAO_MAP)
    expect(values.sort((a, b) => a - b)).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
  })

  it('all keys are uppercase strings', () => {
    for (const key of Object.keys(CLASSIFICACAO_MAP)) {
      expect(key).toBe(key.toUpperCase())
    }
  })

  it('maps id 1 to RUA via reverse lookup', () => {
    const entries = Object.entries(CLASSIFICACAO_MAP)
    const rua = entries.find(([, id]) => id === 1)
    expect(rua?.[0]).toBe('RUA')
  })

  it('has no duplicate values', () => {
    const values = Object.values(CLASSIFICACAO_MAP)
    expect(new Set(values).size).toBe(values.length)
  })
})
