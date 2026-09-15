import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, within, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import { invalidateApiCache } from '../api/cache'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))

import UEManagementPage from '../pages/courses/UEManagementPage'

const ok = (data) => Promise.resolve({ data: { success: true, data } })

const UES = [{
  id: 55, code: 'UE1', intitule: 'UE de test', semestre: 1, filiere_id: 21, filiere: { id: 21, code: 'IM-L1' },
  annee_id: 3, volume_horaire: 20, ecs: [{ id: 91, code: 'EC1', intitule: 'EC de test', volume_horaire: 20 }],
}]

describe('EC — modification', () => {
  beforeEach(() => {
    invalidateApiCache()
    for (const methode of ['get', 'put']) api[methode].mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true, close: false }])
      if (url === '/admin/filieres') return ok([{ id: 21, code: 'IM-L1', intitule: 'Informatique (L1)', niveau: 'L1', semestres: [1, 2] }])
      if (url === '/admin/niveaux') return ok([{ code: 'L1', libelle: 'Licence 1', semestres: [1, 2] }])
      if (url === '/admin/ues') return ok(UES)
      return ok([])
    })
    api.put.mockImplementation(() => ok({}))
  })

  // Retrouvé par son code, un EC dont on changeait le code n'était pas modifié,
  // et l'écran annonçait pourtant « EC mis à jour avec succès ».
  it('modifie l’EC par son identifiant, même quand son code change', async () => {
    renderPage(<UEManagementPage />)
    fireEvent.click(await screen.findByText('UE de test'))

    fireEvent.click(await screen.findByTitle('Modifier'))
    const fenetre = screen.getByText("Modifier l'EC").closest('div.fixed, [role="dialog"]') ?? document.body
    const code = within(fenetre).getAllByRole('textbox')[0]
    expect(code).toHaveValue('EC1')
    fireEvent.change(code, { target: { value: 'EC1-BIS' } })
    fireEvent.submit(code.closest('form'))

    await waitFor(() => expect(api.put).toHaveBeenCalledWith('/admin/ecs/91', expect.objectContaining({ code: 'EC1-BIS', ue_id: 55 })))
  })
})
