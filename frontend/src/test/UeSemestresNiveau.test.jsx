import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, within, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))

import UEManagementPage from '../pages/courses/UEManagementPage'

const ok = (data) => Promise.resolve({ data: { success: true, data } })

const NIVEAUX = [
  { code: 'L1', libelle: 'Licence 1', semestres: [1, 2] },
  { code: 'M1', libelle: 'Master 1', semestres: [7, 8] },
]

const FILIERES = [
  { id: 20, code: 'GL-M1', intitule: 'Génie Logiciel (M1)', niveau: 'M1', programme_id: 1, semestres: [7, 8] },
  { id: 21, code: 'IM-L1', intitule: 'Informatique (L1)', niveau: 'L1', programme_id: 2, semestres: [1, 2] },
]

const UES = [{
  id: 55, code: 'UEM1', intitule: 'UE de Master', semestre: 7, filiere_id: 20,
  filiere: { id: 20, code: 'GL-M1' }, annee_id: 3, volume_horaire: 30, ecs: [],
}]

const semestresProposes = () => within(screen.getByLabelText('Semestre *')).getAllByRole('option').map((o) => o.textContent)

describe('UE — semestres du niveau de la filière', () => {
  beforeEach(() => {
    for (const methode of ['get', 'post', 'put']) api[methode].mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true }])
      if (url === '/admin/filieres') return ok(FILIERES)
      if (url === '/admin/niveaux') return ok(NIVEAUX)
      if (url === '/admin/ues') return ok(UES)
      return ok([])
    })
    api.put.mockImplementation(() => ok({}))
  })

  // « Semestre 1 à 6 » pour toutes les filières : le Master était impossible à
  // saisir, et un S3 pouvait atterrir dans une filière de L1.
  it('ne propose que les semestres du niveau de la filière choisie', async () => {
    renderPage(<UEManagementPage />)
    await screen.findByText('UE de Master')

    fireEvent.click(screen.getByRole('button', { name: /Nouvelle UE/ }))
    const filiere = screen.getByLabelText('Filière *')
    await waitFor(() => expect(within(filiere).getByRole('option', { name: /GL-M1/ })).toBeInTheDocument())

    fireEvent.change(filiere, { target: { value: '20' } })
    expect(semestresProposes()).toEqual(['Semestre 7', 'Semestre 8'])
    expect(screen.getByLabelText('Semestre *')).toHaveValue('7')

    fireEvent.change(filiere, { target: { value: '21' } })
    expect(semestresProposes()).toEqual(['Semestre 1', 'Semestre 2'])
    expect(screen.getByLabelText('Semestre *')).toHaveValue('1')
  })

  // L'UE modifiée était retrouvée par son code : le changer, ou un code partagé
  // par deux filières, visait la mauvaise UE — ou aucune.
  it('modifie l’UE par son identifiant, même après un changement de code', async () => {
    renderPage(<UEManagementPage />)
    await screen.findByText('UE de Master')

    fireEvent.click(screen.getByTitle("Modifier l'UE"))
    fireEvent.change(screen.getByLabelText('Code *'), { target: { value: 'UEM1-BIS' } })
    fireEvent.click(screen.getByRole('button', { name: /Mettre à jour/ }))

    await waitFor(() => expect(api.put).toHaveBeenCalledWith(
      '/admin/ues/55',
      expect.objectContaining({ code: 'UEM1-BIS', semestre: 7 }),
    ))
  })
})
