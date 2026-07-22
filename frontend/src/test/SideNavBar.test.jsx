import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ logout: vi.fn() }),
}))

import SideNavBar from '../components/layout/navigation/SideNavBar'

describe('SideNavBar', () => {
  it('renders app title and subtitle', () => {
    render(
      <BrowserRouter>
        <SideNavBar />
      </BrowserRouter>
    )
    expect(screen.getByText('Présence')).toBeInTheDocument()
    expect(screen.getByText('Portail Académique')).toBeInTheDocument()
  })

  it('renders all navigation links', () => {
    render(
      <BrowserRouter>
        <SideNavBar />
      </BrowserRouter>
    )
    expect(screen.getByText('Dashboard')).toBeInTheDocument()
    expect(screen.getByText('Étudiants')).toBeInTheDocument()
    expect(screen.getByText('Cours & UE/EC')).toBeInTheDocument()
    expect(screen.getByText('Emploi du temps')).toBeInTheDocument()
    expect(screen.getByText('Événements')).toBeInTheDocument()
    expect(screen.getByText('Présences')).toBeInTheDocument()
    expect(screen.getByText('Rapports')).toBeInTheDocument()
    expect(screen.getByText('Paramètres')).toBeInTheDocument()
  })

  it('has correct number of nav links', () => {
    render(
      <BrowserRouter>
        <SideNavBar />
      </BrowserRouter>
    )
    const links = screen.getAllByRole('link')
    expect(links.length).toBeGreaterThanOrEqual(8)
  })
})
