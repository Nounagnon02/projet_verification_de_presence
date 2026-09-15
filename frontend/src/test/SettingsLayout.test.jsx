import { describe, it, expect } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import SettingsLayout from '../components/layout/SettingsLayout'

const monter = (chemin) => render(
  <MemoryRouter initialEntries={[chemin]}>
    <Routes>
      <Route path="/settings" element={<SettingsLayout />}>
        <Route path="salles" element={<p>Contenu des salles</p>} />
        <Route path="filieres" element={<p>Contenu des filières</p>} />
      </Route>
    </Routes>
  </MemoryRouter>,
)

describe('Paramètres', () => {
  it("ne configure que l'établissement : la sécurité du compte est passée dans Profil", () => {
    monter('/settings/salles')

    const nav = screen.getByRole('navigation', { name: 'Paramètres' })
    expect(within(nav).getAllByRole('link').map((l) => l.textContent)).toEqual(['Années académiques', 'Filières', 'Salles', 'Calendrier'])
    expect(screen.queryByRole('link', { name: /Sécurité/ })).toBeNull()
  })

  // Les rôles tab annonçaient « non sélectionné » partout, et un second
  // <main id="main-content"> doublait celui de la mise en page.
  it('signale la page courante par aria-current, sans faux onglets ni second main', () => {
    const { container } = monter('/settings/salles')

    expect(screen.getByRole('link', { name: 'Salles' })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: 'Filières' })).not.toHaveAttribute('aria-current')
    expect(screen.queryAllByRole('tab')).toHaveLength(0)
    expect(container.querySelector('main')).toBeNull()
    expect(screen.getByText('Contenu des salles')).toBeInTheDocument()
  })
})
