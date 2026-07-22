import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: null }),
}))

import ProtectedRoute from '../components/auth/ProtectedRoute'

describe('ProtectedRoute', () => {
  it('redirects to login when not authenticated', () => {
    render(
      <MemoryRouter initialEntries={['/dashboard']}>
        <ProtectedRoute>
          <div>Contenu protégé</div>
        </ProtectedRoute>
      </MemoryRouter>
    )
    expect(screen.queryByText('Contenu protégé')).not.toBeInTheDocument()
  })
})
