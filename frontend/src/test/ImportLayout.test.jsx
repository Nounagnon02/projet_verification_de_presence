import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'

import ImportLayout from '../components/layout/ImportLayout'

const monter = (chemin = '/import/cours-csv') => render(
  <MemoryRouter initialEntries={[chemin]}>
    <Routes>
      <Route path="/import" element={<ImportLayout />}>
        <Route path=":type" element={<div>contenu de l&apos;onglet</div>} />
      </Route>
    </Routes>
  </MemoryRouter>,
)

describe('ImportLayout', () => {
  it('expose les quatre imports en onglets', () => {
    monter()
    for (const libelle of ['Cours (CSV)', 'EDT (CSV)', 'EDT (IA)', 'Cours (IA)']) {
      expect(screen.getByText(libelle)).toBeInTheDocument()
    }
  })

  it('n\'expose pas l\'import des etudiants', () => {
    // Il appartient a la page de gestion des etudiants, au plus pres des
    // donnees qu'il alimente.
    monter()
    expect(screen.queryByText('Étudiants')).not.toBeInTheDocument()
  })

  it('chaque onglet pointe vers sa propre route', () => {
    monter()
    const attendus = {
      'Cours (CSV)': '/import/cours-csv',
      'EDT (CSV)':   '/import/edt-csv',
      'EDT (IA)':    '/import/edt-ia',
      'Cours (IA)':  '/import/cours-ia',
    }
    for (const [libelle, chemin] of Object.entries(attendus)) {
      expect(screen.getByText(libelle).closest('a')).toHaveAttribute('href', chemin)
    }
  })

  it('rend le contenu de l\'onglet actif', () => {
    monter()
    expect(screen.getByText("contenu de l'onglet")).toBeInTheDocument()
  })

  it('la barre d\'onglets est annoncee aux lecteurs d\'ecran', () => {
    monter()
    // Sans role ni libelle, une barre d'onglets n'est qu'une suite de liens
    // pour un lecteur d'ecran.
    expect(screen.getByRole('tablist', { name: /importation/i })).toBeInTheDocument()
  })
})
