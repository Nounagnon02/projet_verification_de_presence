import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, waitFor, act } from '@testing-library/react'
import useApi from '../hooks/useApi'

// Mock the axios module
vi.mock('../api/axios', () => ({
  default: {
    get: vi.fn(),
  },
}))

import api from '../api/axios'

describe('useApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('starts with loading true when immediate is true', () => {
    api.get.mockResolvedValue({ data: { data: [] } })
    const { result } = renderHook(() => useApi('/test'))
    expect(result.current.loading).toBe(true)
  })

  it('starts with loading false when immediate is false', () => {
    const { result } = renderHook(() => useApi('/test', {}, { immediate: false }))
    expect(result.current.loading).toBe(false)
    expect(result.current.data).toBeNull()
  })

  it('fetches data on mount and sets data', async () => {
    const responseData = { data: [{ id: 1, name: 'Test' }] }
    api.get.mockResolvedValue({ data: responseData })

    const { result } = renderHook(() => useApi('/test'))

    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toEqual(responseData.data)
    expect(api.get).toHaveBeenCalledWith('/test', { params: {}, signal: expect.any(Object) })
  })

  it('extracts success.data from API response', async () => {
    const responseData = { success: true, data: { id: 1 } }
    api.get.mockResolvedValue({ data: responseData })

    const { result } = renderHook(() => useApi('/test'))

    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.data).toEqual({ id: 1 })
  })

  it('sets error when success is false', async () => {
    const responseData = { success: false, message: 'Erreur API' }
    api.get.mockResolvedValue({ data: responseData })

    const { result } = renderHook(() => useApi('/test'))

    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBe('Erreur API')
  })

  it('sets error on network failure', async () => {
    api.get.mockRejectedValue(new Error('Network Error'))

    const { result } = renderHook(() => useApi('/test'))

    await waitFor(() => expect(result.current.loading).toBe(false))
    expect(result.current.error).toBe('Network Error')
  })

  it('does not fetch when url is null', () => {
    renderHook(() => useApi(null))
    expect(api.get).not.toHaveBeenCalled()
  })

  it('refetch can be called manually', async () => {
    api.get.mockResolvedValue({ data: { data: 'initial' } })
    const { result } = renderHook(() => useApi('/test'))

    await waitFor(() => expect(result.current.loading).toBe(false))

    api.get.mockResolvedValue({ data: { data: 'updated' } })
    act(() => { result.current.refetch() })

    await waitFor(() => expect(result.current.data).toBe('updated'))
  })
})
