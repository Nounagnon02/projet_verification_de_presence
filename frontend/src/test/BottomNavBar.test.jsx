import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'
import BottomNavBar from '../components/layout/navigation/BottomNavBar'

describe('BottomNavBar', () => {
  it('renders navigation links', () => {
    render(
      <BrowserRouter>
        <BottomNavBar />
      </BrowserRouter>
    )
    expect(screen.getByText('Accueil')).toBeInTheDocument()
    expect(screen.getByText('Étudiants')).toBeInTheDocument()
    expect(screen.getByText('Présence')).toBeInTheDocument()
    expect(screen.getByText('Emploi du temps')).toBeInTheDocument()
    expect(screen.getByText('Paramètres')).toBeInTheDocument()
  })

  it('renders 5 navigation items', () => {
    render(
      <BrowserRouter>
        <BottomNavBar />
      </BrowserRouter>
    )
    const links = screen.getAllByRole('link')
    expect(links).toHaveLength(5)
  })

  it('links have correct paths', () => {
    render(
      <BrowserRouter>
        <BottomNavBar />
      </BrowserRouter>
    )
    expect(screen.getByText('Accueil').closest('a')).toHaveAttribute('href', '/dashboard')
    expect(screen.getByText('Étudiants').closest('a')).toHaveAttribute('href', '/students')
    expect(screen.getByText('Présence').closest('a')).toHaveAttribute('href', '/attendance/validate')
  })
})
