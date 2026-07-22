import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: { name: 'Admin User' }, logout: vi.fn() }),
}))

vi.mock('../api/axios', () => ({
  default: {
    get: vi.fn().mockResolvedValue({ data: { success: true, data: { count: 3 } } }),
  },
}))

import TopNavBar from '../components/layout/navigation/TopNavBar'

describe('TopNavBar', () => {
  it('renders app title', () => {
    render(
      <BrowserRouter>
        <TopNavBar />
      </BrowserRouter>
    )
    expect(screen.getByText('Présence')).toBeInTheDocument()
  })

  it('renders username', () => {
    render(
      <BrowserRouter>
        <TopNavBar />
      </BrowserRouter>
    )
    expect(screen.getByText('Admin User')).toBeInTheDocument()
  })

  it('renders notifications button', () => {
    render(
      <BrowserRouter>
        <TopNavBar />
      </BrowserRouter>
    )
    const notifBtn = screen.getByRole('button', { name: /notifications/i })
    expect(notifBtn).toBeInTheDocument()
  })
})
