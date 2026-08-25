import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'

vi.mock('../api/axios', () => ({
  default: { post: vi.fn(), get: vi.fn() },
}))

import { ToastProvider } from '../context/ToastContext'
import ImportPage from '../pages/import/ImportPage'

/**
 * ImportPage lit desormais son type d'import dans l'URL : les onglets vivent
 * dans ImportLayout, plus dans la page. Le montage doit donc passer par une
 * route porteuse du parametre, sinon useParams ne renvoie rien.
 */
const monter = (segment) => render(
  <MemoryRouter initialEntries={[`/import/${segment}`]}>
    <ToastProvider>
      <Routes>
        <Route path="/import/:type" element={<ImportPage />} />
      </Routes>
    </ToastProvider>
  </MemoryRouter>,
)

describe('ImportPage', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('titre et zone de depot pour l\'import des etudiants', () => {
    monter('etudiants')
    expect(screen.getByText('Import des étudiants')).toBeInTheDocument()
    expect(screen.getByText(/Importez votre fichier étudiants/)).toBeInTheDocument()
  })

  it.each([
    ['cours-csv',    'Import des cours (CSV)',                 /Importez vos cours/],
    ['edt-csv',      "Import de l'emploi du temps (CSV)",      /emploi du temps au format CSV/],
    ['edt-ia',       "Import de l'emploi du temps (IA)",       /emploi du temps PDF/],
    ['cours-ia',     'Import de la maquette pédagogique (IA)', /catalogue de cours PDF/],
  ])('le segment « %s » selectionne son import', (segment, titre, consigne) => {
    monter(segment)
    expect(screen.getByText(titre)).toBeInTheDocument()
    expect(screen.getByText(consigne)).toBeInTheDocument()
  })

  it('un segment inconnu retombe sur l\'import des etudiants', () => {
    // Plutot qu'un ecran vide : le layout ne propose que des segments valides,
    // mais une URL saisie a la main ne doit pas casser la page.
    monter('segment-inexistant')
    expect(screen.getByText('Import des étudiants')).toBeInTheDocument()
  })

  it('n\'affiche plus de barre d\'onglets : elle appartient au layout', () => {
    monter('etudiants')
    // Les libelles des onglets ne doivent plus apparaitre dans la page elle-meme,
    // sinon ils seraient affiches deux fois sous le layout.
    expect(screen.queryByText('EDT (IA)')).not.toBeInTheDocument()
    expect(screen.queryByText('Cours (IA)')).not.toBeInTheDocument()
  })

  it('propose le modele CSV sur les imports qui en ont un', () => {
    monter('cours-csv')
    expect(screen.getByText(/Modèle Cours/)).toBeInTheDocument()
  })
})
