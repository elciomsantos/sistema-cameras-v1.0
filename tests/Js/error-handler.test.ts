import { describe, it, expect, beforeEach, vi } from 'vitest'

beforeEach(() => {
  document.body.innerHTML = ''
  vi.resetModules()
})

describe('ErrorHandler', () => {
  it('creates notification container on init', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const container = document.getElementById('error-notification-container')
    expect(container).not.toBeNull()
    expect(container?.querySelector('.error-notification-wrapper')).not.toBeNull()
  })

  it('handle() returns error info object', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    const info = handler.handle(new Error('test error'), 'test context', 'error')
    expect(info).toHaveProperty('error')
    expect(info).toHaveProperty('context', 'test context')
    expect(info).toHaveProperty('severity', 'error')
    expect(info).toHaveProperty('timestamp')
    expect(info).toHaveProperty('type', 'Error')
  })

  it('adds errors to log', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    handler.clearErrorLog()
    handler.handle(new Error('error 1'), 'ctx1')
    handler.handle(new Error('error 2'), 'ctx2')
    const log = handler.getErrorLog()
    expect(log.length).toBe(2)
    expect(log[0].context).toBe('ctx2')
    expect(log[1].context).toBe('ctx1')
  })

  it('clears error log', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    handler.handle(new Error('test'))
    handler.clearErrorLog()
    expect(handler.getErrorLog().length).toBe(0)
  })

  it('respects maxLogSize', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    handler.clearErrorLog()
    handler.options.maxLogSize = 3
    for (let i = 0; i < 5; i++) {
      handler.handle(new Error('error ' + i))
    }
    expect(handler.getErrorLog().length).toBe(3)
  })

  it('formatErrorMessage handles strings', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    expect(handler.formatErrorMessage('simple error')).toBe('simple error')
  })

  it('formatErrorMessage handles Error objects', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    expect(handler.formatErrorMessage(new Error('msg'))).toBe('msg')
  })

  it('formatErrorMessage handles unknown types', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    expect(handler.formatErrorMessage(42)).toBe('Ocorreu um erro desconhecido')
  })

  it('getUserMessage returns mapped messages', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    expect(handler.getUserMessage(new Error('network error'))).toContain('conexão')
    expect(handler.getUserMessage(new Error('timeout occurred'))).toContain('demorou')
    expect(handler.getUserMessage(new TypeError('invalid'))).toContain('inválidos')
    expect(handler.getUserMessage(new Error('not found'))).toContain('não encontrado')
    expect(handler.getUserMessage(new Error('unauthorized'))).toContain('permissão')
  })

  it('getErrorType classifies errors correctly', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    expect(handler.getErrorType(new TypeError('x'))).toBe('validation')
    expect(handler.getErrorType(new ReferenceError('x'))).toBe('validation')
    expect(handler.getErrorType(new Error('network fail'))).toBe('network')
    expect(handler.getErrorType(new Error('timeout'))).toBe('timeout')
    expect(handler.getErrorType(new Error('unauthorized'))).toBe('authorization')
    expect(handler.getErrorType(new Error('not found'))).toBe('not_found')
    expect(handler.getErrorType(new Error('duplicate key'))).toBe('duplicate')
    expect(handler.getErrorType(new Error('something else'))).toBe('server_error')
  })

  it('custom handlers can be added and removed', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    const mockFn = vi.fn()
    handler.addCustomHandler('test_key', mockFn)
    expect(handler.options.customHandlers['test_key']).toBe(mockFn)
    handler.removeCustomHandler('test_key')
    expect(handler.options.customHandlers['test_key']).toBeUndefined()
  })

  it('convenience methods work', async () => {
    await import('../../src/assets/js/utils/ui/ErrorHandler')
    const handler = (window as any).ErrorHandler
    const apiInfo = handler.handleApiError(new Error('fail'), '/api/test')
    expect(apiInfo.context).toContain('API Error')

    const valInfo = handler.handleValidationError(new Error('bad'), 'email')
    expect(valInfo.context).toContain('Validation Error')
    expect(valInfo.severity).toBe('warning')

    const netInfo = handler.handleNetworkError(new Error('offline'), 'http://x')
    expect(netInfo.context).toContain('Network Error')

    const timeoutInfo = handler.handleTimeoutError(new Error('slow'), 'upload')
    expect(timeoutInfo.context).toContain('Timeout Error')
  })
})
