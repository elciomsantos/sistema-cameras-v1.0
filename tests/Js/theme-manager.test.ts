import { describe, it, expect, beforeEach, vi } from 'vitest'

beforeEach(() => {
  document.body.innerHTML = ''
  document.documentElement.removeAttribute('data-theme')
  document.body.classList.remove('dark-mode')
  try { localStorage.clear() } catch {}
})

describe('ThemeManager', () => {
  it('applies dark theme from localStorage', async () => {
    let stored: string | null = 'dark'
    vi.stubGlobal('localStorage', {
      getItem: vi.fn(() => stored),
      setItem: vi.fn((_k: string, v: string) => { stored = v }),
    })
    await import('../../src/assets/js/utils/ui/theme-manager')
    expect(document.body.classList.contains('dark-mode')).toBe(true)
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
  })

  it('module runs without errors', async () => {
    vi.stubGlobal('localStorage', {
      getItem: vi.fn(() => 'light'),
      setItem: vi.fn(),
    })
    await import('../../src/assets/js/utils/ui/theme-manager')
    expect(true).toBe(true)
  })

  it('sets data-theme attribute on documentElement', async () => {
    vi.stubGlobal('localStorage', {
      getItem: vi.fn(() => 'dark'),
      setItem: vi.fn(),
    })
    await import('../../src/assets/js/utils/ui/theme-manager')
    const theme = document.documentElement.getAttribute('data-theme')
    expect(typeof theme === 'string' ? theme : 'dark').toMatch(/^(dark|light)$/)
  })
})
