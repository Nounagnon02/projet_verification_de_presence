import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter, MemoryRouter } from 'react-router-dom'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ logout: vi.fn() }),
}))

import SideNavBar from '../components/layout/navigation/SideNavBar'

describe('SideNavBar', () => {
  it('affiche le logo et le sous-titre', () => {
    render(
      <BrowserRouter>
        <SideNavBar />
      </BrowserRouter>
    )
    expect(screen.getByAltText('UAC Présences')).toBeInTheDocument()
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

  // ── Mise en evidence de l'entree active ────────────────────────────────────
  //
  // Chaque lien portait « end », qui impose une correspondance EXACTE.
  // « Presences » et « Parametres » ne s'allumaient donc JAMAIS : leur index
  // redirige vers une sous-route, leur chemin nu n'est jamais atteint. Aucun
  // test ne le voyait — ils ne verifiaient que la presence des libelles.

  const entreesActives = (chemin) => {
    const { container } = render(
      <MemoryRouter initialEntries={[chemin]}>
        <SideNavBar />
      </MemoryRouter>
    )
    return [...container.querySelectorAll('a')]
      .filter((a) => a.className.includes('font-semibold'))
      .map((a) => a.textContent.trim())
  }

  it.each([
    ['/attendance/queue',   'Présences'],
    ['/attendance/alerts',  'Présences'],
    ['/settings/salles',    'Paramètres'],
    ['/settings/security',  'Paramètres'],
    ['/courses/ues',        'Cours & UE/EC'],
    ['/reports/filtered',   'Rapports'],
    ['/schedules/events',   'Événements'],
    ['/dashboard',          'Dashboard'],
  ])('sur %s, l\'entree « %s » est mise en evidence', (chemin, attendu) => {
    expect(entreesActives(chemin)).toEqual([attendu])
  })

  it('n\'allume jamais deux entrees a la fois', () => {
    // Aucun chemin de la barre n'est prefixe d'un autre : la correspondance par
    // prefixe ne peut donc pas en allumer deux. Ce test le verrouille, car
    // ajouter une entree « /schedules » romprait l'invariant.
    for (const chemin of ['/schedules/weekly', '/schedules/events', '/attendance/history', '/settings/filieres']) {
      expect(entreesActives(chemin)).toHaveLength(1)
    }
  })
})
