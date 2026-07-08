import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { escapeHtml, sanitizeHtml } from '../../src/assets/js/utils/html'

beforeAll(() => {
  const div = document.createElement('div')
  document.body.appendChild(div)
})

afterAll(() => {
  document.body.innerHTML = ''
})

describe('escapeHtml', () => {
  it('escapes HTML special characters', () => {
    const result = escapeHtml('<script>alert("xss")</script>')
    expect(result).toContain('&lt;script&gt;')
    expect(result).toContain('&lt;/script&gt;')
    expect(result).not.toContain('<script>')
  })

  it('returns empty string for null', () => {
    expect(escapeHtml(null)).toBe('')
  })

  it('returns empty string for undefined', () => {
    expect(escapeHtml(undefined)).toBe('')
  })

  it('preserves plain text', () => {
    expect(escapeHtml('hello world')).toBe('hello world')
  })

  it('escapes ampersands', () => {
    expect(escapeHtml('a & b')).toBe('a &amp; b')
  })
})

describe('sanitizeHtml', () => {
  it('removes script tags', () => {
    expect(sanitizeHtml('<script>alert(1)</script>')).toBe('alert(1)')
  })

  it('removes unsafe attributes', () => {
    const result = sanitizeHtml('<a href="javascript:alert(1)">click</a>')
    expect(result).not.toContain('javascript:')
    expect(result).toContain('click')
  })

  it('preserves safe tags', () => {
    expect(sanitizeHtml('<b>bold</b> <i>italic</i>')).toBe('<b>bold</b> <i>italic</i>')
  })

  it('removes unknown tags but keeps content', () => {
    expect(sanitizeHtml('<marquee>text</marquee>')).toBe('text')
  })

  it('removes img without src', () => {
    expect(sanitizeHtml('<img src="">')).toBe('<img>')
  })
})
