import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, within, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import { invalidateApiCache } from '../api/cache'

const { api, addToast } = vi.hoisted(() => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  addToast: vi.fn(),
}))

vi.mock('../api/axios', () => ({ default: api }))
vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast }) }))

import FilieresPage from '../pages/settings/FilieresPage'

const ok = (data) => Promise.resolve({ data: { success: true, data } })

const FILIERES = [{
  id: 10, code: 'IM-L1', niveau: 'L1', programme_id: 1, intitule: 'Informatique et Mathématiques (L1)',
  etudiants_total: 0, ues_total: 0, evenements_total: 0, ues_par_semestre: {},
}]

describe('Filières — renommer un programme', () => {
  beforeEach(() => {
    invalidateApiCache()
    for (const methode of ['get', 'put']) api[methode].mockReset()
    addToast.mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true }])
      if (url === '/admin/niveaux') return ok([{ code: 'L1', libelle: 'Licence 1', semestres: [1, 2] }])
      if (url === '/admin/programmes') return ok([{ id: 1, code: 'IM', intitule: 'Informatique et Mathématiques', filieres_count: 1 }])
      if (url === '/admin/filieres') return ok(FILIERES)
      return ok([])
    })
    api.put.mockResolvedValue({ data: { success: true, message: 'Programme IM renommé. Filières renommées : IM-L1.' } })
  })

  it('renomme le programme, et annonce ce que deviendront ses filières au nom dérivé', async () => {
    renderPage(<FilieresPage />)

    fireEvent.click(await screen.findByRole('button', { name: 'Renommer le programme IM' }))
    const fenetre = screen.getByRole('dialog', { name: 'Renommer le programme IM' })

    const intitule = within(fenetre).getByLabelText('Intitulé')
    expect(intitule).toHaveValue('Informatique et Mathématiques')
    fireEvent.change(intitule, { target: { value: 'Informatique' } })

    expect(fenetre).toHaveTextContent('« Informatique et Mathématiques (L1) » devient « Informatique (L1) »')
    expect(fenetre).toHaveTextContent('Le code IM ne change pas')
    expect(within(fenetre).getByLabelText(/Renommer aussi les filières/)).toBeChecked()

    fireEvent.click(within(fenetre).getByRole('button', { name: 'Renommer' }))

    await waitFor(() => expect(api.put).toHaveBeenCalledWith('/admin/programmes/1', { intitule: 'Informatique', renommer_filieres: true }))
    await waitFor(() => expect(addToast).toHaveBeenCalledWith('Programme IM renommé. Filières renommées : IM-L1.', 'success'))
  })
})
