import { describe, it, expect, beforeEach } from 'vitest'

beforeEach(() => {
  document.body.innerHTML = ''
})

describe('toBrDate', () => {
  it('returns dash for falsy values', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).toBrDate(null)).toBe('-')
    expect((window as any).toBrDate('')).toBe('-')
    expect((window as any).toBrDate(undefined)).toBe('-')
  })

  it('formats valid date string', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    const result = (window as any).toBrDate('2026-01-15 14:30:00')
    expect(result).toMatch(/\d{2}\/\d{2}\/2026/)
  })

  it('returns original string for invalid date', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).toBrDate('invalid')).toBe('invalid')
  })
})

describe('toDateTimeLocalNow', () => {
  it('returns datetime-local format', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    const result = (window as any).toDateTimeLocalNow()
    expect(result).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/)
  })
})

describe('toIsoDateStamp', () => {
  it('returns ISO date stamp format', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    const result = (window as any).toIsoDateStamp()
    expect(result).toMatch(/^\d{8}_\d{4}$/)
  })
})

describe('toDateOnlyBr', () => {
  it('returns dash for falsy values', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).toDateOnlyBr(null)).toBe('-')
    expect((window as any).toDateOnlyBr('')).toBe('-')
  })

  it('formats valid date to dd/mm/yyyy', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    const result = (window as any).toDateOnlyBr('2026-01-15T12:00:00')
    expect(result).toBe('15/01/2026')
  })
})

describe('toTimeOnlyBr', () => {
  it('returns dash for falsy values', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).toTimeOnlyBr(null)).toBe('-')
  })

  it('formats valid time', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    const result = (window as any).toTimeOnlyBr('2026-07-12 14:30:00')
    expect(result).toMatch(/\d{2}:\d{2}/)
  })
})

describe('csvEscape', () => {
  it('wraps value in quotes', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).csvEscape('hello')).toBe('"hello"')
  })

  it('escapes double quotes', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).csvEscape('say "hello"')).toBe('"say ""hello"""')
  })

  it('handles null/undefined', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).csvEscape(null)).toBe('""')
    expect((window as any).csvEscape(undefined)).toBe('""')
  })

  it('handles numbers', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).csvEscape(42)).toBe('"42"')
  })
})

describe('renderSelectOptions', () => {
  it('renders options into select element', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    document.body.innerHTML = '<select id="status"></select>'
    ;(window as any).renderSelectOptions('status', [
      { id: 1, nome: 'Ativo' },
      { id: 2, nome: 'Inativo' },
    ])
    const select = document.getElementById('status') as HTMLSelectElement
    expect(select.options.length).toBe(2)
    expect(select.options[0].text).toBe('Ativo')
    expect(select.options[1].text).toBe('Inativo')
  })

  it('includes empty label when provided', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    document.body.innerHTML = '<select id="status"></select>'
    ;(window as any).renderSelectOptions('status', [{ id: 1, nome: 'Ativo' }], 'Selecione...')
    const select = document.getElementById('status') as HTMLSelectElement
    expect(select.options.length).toBe(2)
    expect(select.options[0].text).toBe('Selecione...')
    expect(select.options[0].value).toBe('')
  })

  it('handles null data', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    document.body.innerHTML = '<select id="status"></select>'
    ;(window as any).renderSelectOptions('status', null)
    const select = document.getElementById('status') as HTMLSelectElement
    expect(select.options.length).toBe(0)
  })

  it('does nothing if select does not exist', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    ;(window as any).renderSelectOptions('nonexistent', [{ id: 1, nome: 'Test' }])
  })
})

describe('hasSelectableOptions', () => {
  it('returns true when select has multiple options', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    document.body.innerHTML = `
      <select id="sel">
        <option value="">--</option>
        <option value="1">One</option>
      </select>
    `
    expect((window as any).hasSelectableOptions('sel')).toBe(true)
  })

  it('returns false when select has only one option', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    document.body.innerHTML = '<select id="sel"><option value="">--</option></select>'
    expect((window as any).hasSelectableOptions('sel')).toBe(false)
  })

  it('returns false when select does not exist', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    expect((window as any).hasSelectableOptions('nonexistent')).toBe(false)
  })
})

describe('ensureSelectOption', () => {
  it('adds option if not present', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    document.body.innerHTML = '<select id="sel"></select>'
    const select = document.getElementById('sel') as HTMLSelectElement
    ;(window as any).ensureSelectOption(select, '3', 'Three')
    expect(select.options.length).toBe(1)
    expect(select.value).toBe('3')
  })

  it('selects existing option', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    document.body.innerHTML = `
      <select id="sel">
        <option value="1">One</option>
        <option value="2">Two</option>
      </select>
    `
    const select = document.getElementById('sel') as HTMLSelectElement
    ;(window as any).ensureSelectOption(select, '2', 'Two')
    expect(select.value).toBe('2')
  })

  it('does nothing for null select', async () => {
    await import('../../src/assets/js/shared/manutencao-utils')
    ;(window as any).ensureSelectOption(null, '1', 'One')
  })
})
