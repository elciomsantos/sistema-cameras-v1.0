import { describe, it, expect, beforeEach } from 'vitest'
import { setSelectByText } from '../../src/assets/js/utils/locais-utils'

beforeEach(() => {
  document.body.innerHTML = ''
})

describe('setSelectByText', () => {
  it('sets select value by matching text', () => {
    document.body.innerHTML = `
      <select id="classificacao">
        <option value="1">RUA</option>
        <option value="2">AVENIDA</option>
        <option value="3">ALAMEDA</option>
      </select>
    `
    setSelectByText('classificacao', 'AVENIDA')
    const select = document.getElementById('classificacao') as HTMLSelectElement
    expect(select.value).toBe('2')
  })

  it('is case-insensitive', () => {
    document.body.innerHTML = `
      <select id="classificacao">
        <option value="1">RUA</option>
        <option value="2">AVENIDA</option>
      </select>
    `
    setSelectByText('classificacao', 'avenida')
    const select = document.getElementById('classificacao') as HTMLSelectElement
    expect(select.value).toBe('2')
  })

  it('does nothing if select does not exist', () => {
    setSelectByText('nonexistent', 'RUA')
  })

  it('does nothing if text is empty', () => {
    document.body.innerHTML = `
      <select id="classificacao">
        <option value="1">RUA</option>
      </select>
    `
    setSelectByText('classificacao', '')
    const select = document.getElementById('classificacao') as HTMLSelectElement
    expect(select.value).toBe('1')
  })

  it('does nothing if text is not found', () => {
    document.body.innerHTML = `
      <select id="classificacao">
        <option value="1">RUA</option>
        <option value="2">AVENIDA</option>
      </select>
    `
    setSelectByText('classificacao', 'MARGINAL')
    const select = document.getElementById('classificacao') as HTMLSelectElement
    expect(select.value).toBe('1')
  })

  it('handles whitespace in text', () => {
    document.body.innerHTML = `
      <select id="classificacao">
        <option value="1">RUA</option>
        <option value="2">AVENIDA</option>
      </select>
    `
    setSelectByText('classificacao', '  AVENIDA  ')
    const select = document.getElementById('classificacao') as HTMLSelectElement
    expect(select.value).toBe('2')
  })
})
