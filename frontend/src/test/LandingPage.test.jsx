import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: null }),
}))

import LandingPage from '../pages/LandingPage'

describe('LandingPage', () => {
  it('renders heading', () => {
    // QueryClientProvider : LandingPage lit /landing/stats avec useQuery (voir
    // src/api/resources/landing.js). Un client neuf, sans retry, pour ne pas
    // faire fuiter de cache d'un test au suivant.
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: 0 } } })
    render(
      <QueryClientProvider client={queryClient}>
        <BrowserRouter>
          <LandingPage />
        </BrowserRouter>
      </QueryClientProvider>
    )
    expect(screen.getAllByText(/Présence/).length).toBeGreaterThan(0)
  })
})
