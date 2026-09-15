import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, within } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import { invalidateApiCache } from '../api/cache'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))

import UEManagementPage from '../pages/courses/UEManagementPage'

const ok = (data) => Promise.resolve({ data: { success: true, data } })

const ANNEES = [
  { id: 3, libelle: '2025-2026', active: true, close: false },
  { id: 2, libelle: '2024-2025', active: false, close: true },
]

const UES = [
  { id: 71, code: 'UEOLD', intitule: 'UE de 2024-2025', semestre: 1, filiere_id: 21, filiere: { id: 21, code: 'IM-L1' }, annee_id: 2, volume_horaire: 30, ecs: [] },
  { id: 72, code: 'UENEW', intitule: 'UE de 2025-2026', semestre: 1, filiere_id: 21, filiere: { id: 21, code: 'IM-L1' }, annee_id: 3, volume_horaire: 30, ecs: [] },
]

/** Action de la ligne qui porte ce texte : le plus proche ancêtre qui la contient. */
const actionDe = (texte, titre) => {
  let element = screen.getByText(texte)
  while (element && !within(element).queryByTitle(titre)) element = element.parentElement
  return within(element).getByTitle(titre)
}

describe('Écrans en année close', () => {
  beforeEach(() => {
    invalidateApiCache()
    for (const methode of ['get', 'post', 'put']) api[methode].mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/admin/annees-academiques') return ok(ANNEES)
      if (url === '/admin/filieres') return ok([{ id: 21, code: 'IM-L1', intitule: 'Informatique (L1)', niveau: 'L1', semestres: [1, 2] }])
      if (url === '/admin/niveaux') return ok([{ code: 'L1', libelle: 'Licence 1', semestres: [1, 2] }])
      if (url === '/admin/ues') return ok(UES)
      return ok([])
    })
  })

  it("passe l'écran des UE en consultation quand l'année choisie est close", async () => {
    renderPage(<UEManagementPage />, { route: '/?annee=2' })

    expect(await screen.findByRole('status')).toHaveTextContent('2024-2025 est close pour votre établissement : consultation seulement.')
    expect(screen.getByRole('link', { name: 'Années académiques' })).toHaveAttribute('href', '/settings/academic-years')
    expect(screen.getByRole('button', { name: /Nouvelle UE/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: /Import en masse/ })).toBeDisabled()
  })

  it("grise les actions d'une UE d'année close, pas celles d'une UE de l'année active", async () => {
    renderPage(<UEManagementPage />)
    await screen.findByText('UE de 2024-2025')

    expect(actionDe('UE de 2024-2025', "Modifier l'UE")).toBeDisabled()
    expect(actionDe('UE de 2024-2025', "Supprimer l'UE")).toBeDisabled()
    expect(actionDe('UE de 2025-2026', "Modifier l'UE")).toBeEnabled()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Nouvelle UE/ })).toBeEnabled()
  })
})
