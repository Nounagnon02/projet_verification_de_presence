import { describe, it, expect, vi } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import useToast from '../hooks/useToast'

describe('useToast', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('starts with empty toasts', () => {
    const { result } = renderHook(() => useToast())
    expect(result.current.toasts).toEqual([])
  })

  it('adds a toast with addToast', () => {
    const { result } = renderHook(() => useToast())
    act(() => {
      result.current.addToast('Hello', 'info')
    })
    expect(result.current.toasts).toHaveLength(1)
    expect(result.current.toasts[0].message).toBe('Hello')
    expect(result.current.toasts[0].type).toBe('info')
  })

  it('uses success shorthand', () => {
    const { result } = renderHook(() => useToast())
    act(() => {
      result.current.success('Succès !')
    })
    expect(result.current.toasts[0].type).toBe('success')
  })

  it('uses error shorthand', () => {
    const { result } = renderHook(() => useToast())
    act(() => {
      result.current.error('Erreur !')
    })
    expect(result.current.toasts[0].type).toBe('error')
  })

  it('uses warning shorthand', () => {
    const { result } = renderHook(() => useToast())
    act(() => {
      result.current.warning('Attention')
    })
    expect(result.current.toasts[0].type).toBe('warning')
  })

  it('uses info shorthand', () => {
    const { result } = renderHook(() => useToast())
    act(() => {
      result.current.info('Info')
    })
    expect(result.current.toasts[0].type).toBe('info')
  })

  it('removes a toast by id', () => {
    const { result } = renderHook(() => useToast())
    let id
    act(() => {
      id = result.current.addToast('Test', 'info', 0)
    })
    expect(result.current.toasts).toHaveLength(1)
    act(() => {
      result.current.removeToast(id)
    })
    expect(result.current.toasts).toHaveLength(0)
  })

  it('auto-removes toast after duration', () => {
    const { result } = renderHook(() => useToast())
    act(() => {
      result.current.addToast('Auto', 'info', 1000)
    })
    expect(result.current.toasts).toHaveLength(1)
    act(() => {
      vi.advanceTimersByTime(1000)
    })
    expect(result.current.toasts).toHaveLength(0)
  })

  it('keeps toast when duration is 0', () => {
    const { result } = renderHook(() => useToast())
    act(() => {
      result.current.addToast('Persistant', 'info', 0)
    })
    expect(result.current.toasts).toHaveLength(1)
    act(() => {
      vi.advanceTimersByTime(5000)
    })
    expect(result.current.toasts).toHaveLength(1)
  })
})
