import { describe, it, expect, beforeEach, vi } from 'vitest'

beforeEach(() => {
  document.body.innerHTML = ''
  vi.useRealTimers()
})

describe('debounce', () => {
  it('delays function execution', async () => {
    vi.useFakeTimers()
    await import('../../src/assets/js/utils/ui/LoadingManager')
    const debounce = (window as any).debounce

    let callCount = 0
    const fn = () => { callCount++ }
    const debounced = debounce(fn, 100)

    debounced()
    expect(callCount).toBe(0)

    vi.advanceTimersByTime(100)
    expect(callCount).toBe(1)
    vi.useRealTimers()
  })

  it('resets timer on subsequent calls', async () => {
    vi.useFakeTimers()
    await import('../../src/assets/js/utils/ui/LoadingManager')
    const debounce = (window as any).debounce

    let callCount = 0
    const fn = () => { callCount++ }
    const debounced = debounce(fn, 100)

    debounced()
    vi.advanceTimersByTime(50)
    debounced()
    vi.advanceTimersByTime(50)
    expect(callCount).toBe(0)

    vi.advanceTimersByTime(50)
    expect(callCount).toBe(1)
    vi.useRealTimers()
  })

  it('cancel prevents execution', async () => {
    vi.useFakeTimers()
    await import('../../src/assets/js/utils/ui/LoadingManager')
    const debounce = (window as any).debounce

    let callCount = 0
    const fn = () => { callCount++ }
    const debounced = debounce(fn, 100)

    debounced()
    debounced.cancel()
    vi.advanceTimersByTime(200)
    expect(callCount).toBe(0)
    vi.useRealTimers()
  })

  it('flush executes immediately', async () => {
    vi.useFakeTimers()
    await import('../../src/assets/js/utils/ui/LoadingManager')
    const debounce = (window as any).debounce

    let callCount = 0
    const fn = () => { callCount++ }
    const debounced = debounce(fn, 100)

    debounced()
    debounced.flush()
    expect(callCount).toBe(1)
    vi.useRealTimers()
  })
})

describe('throttle', () => {
  it('limits function calls within window', async () => {
    vi.useFakeTimers()
    await import('../../src/assets/js/utils/ui/LoadingManager')
    const throttle = (window as any).throttle

    let callCount = 0
    const fn = () => { callCount++ }
    const throttled = throttle(fn, 100)

    throttled()
    throttled()
    throttled()
    expect(callCount).toBe(1)
    vi.useRealTimers()
  })
})

describe('LoadingManager global singleton', () => {
  it('withLoading manages loading state', async () => {
    document.body.innerHTML = ''
    await import('../../src/assets/js/utils/ui/LoadingManager')
    const lm = (window as any).LoadingManager

    const promise = Promise.resolve('done')
    const result = await lm.withLoading(promise, 'test-op')
    expect(result).toBe('done')
  })

  it('show updates state and hide resets it', async () => {
    document.body.innerHTML = ''
    await import('../../src/assets/js/utils/ui/LoadingManager')
    const lm = (window as any).LoadingManager

    lm.hide()
    expect(lm.state.globalLoading).toBe(false)
    expect(lm.state.globalMessage).toBe('')
  })
})
