import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: null }),
}))

import LandingPage from '../pages/LandingPage'

describe('LandingPage', () => {
  it('renders heading', () => {
    render(
      <BrowserRouter>
        <LandingPage />
      </BrowserRouter>
    )
    expect(screen.getAllByText(/Présence/).length).toBeGreaterThan(0)
  })
})
