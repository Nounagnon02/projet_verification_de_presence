import { describe, it, expect } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import useDebounce from '../hooks/useDebounce'

describe('useDebounce', () => {
  it('returns initial value immediately', () => {
    const { result } = renderHook(() => useDebounce('hello', 300))
    expect(result.current).toBe('hello')
  })

  it('updates after delay', async () => {
    const { result, rerender } = renderHook(
      ({ value, delay }) => useDebounce(value, delay),
      { initialProps: { value: 'hello', delay: 100 } }
    )

    expect(result.current).toBe('hello')

    rerender({ value: 'world', delay: 100 })

    // Should still be old value immediately
    expect(result.current).toBe('hello')

    // Wait for debounce
    await new Promise(resolve => setTimeout(resolve, 150))

    expect(result.current).toBe('world')
  })

  it('cancels previous timer on rapid updates', async () => {
    const { result, rerender } = renderHook(
      ({ value, delay }) => useDebounce(value, delay),
      { initialProps: { value: 'a', delay: 100 } }
    )

    rerender({ value: 'b', delay: 100 })
    rerender({ value: 'c', delay: 100 })

    await new Promise(resolve => setTimeout(resolve, 150))

    expect(result.current).toBe('c')
  })
})
