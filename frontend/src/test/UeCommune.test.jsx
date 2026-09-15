import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, within, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import { invalidateApiCache } from '../api/cache'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))

import UEManagementPage from '../pages/courses/UEManagementPage'

const ok = (data) => Promise.resolve({ data: { success: true, data } })

const FILIERES = [
  { id: 31, code: 'GL-L2', intitule: 'Génie logiciel (L2)', niveau: 'L2', semestres: [3, 4] },
  { id: 32, code: 'IM-L2', intitule: 'Informatique (L2)', niveau: 'L2', semestres: [3, 4] },
  { id: 33, code: 'IM-L1', intitule: 'Informatique (L1)', niveau: 'L1', semestres: [1, 2] },
]

describe('UE — cours commun', () => {
  beforeEach(() => {
    invalidateApiCache()
    for (const methode of ['get', 'post']) api[methode].mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true, close: false }])
      if (url === '/admin/filieres') return ok(FILIERES)
      if (url === '/admin/niveaux') return ok([{ code: 'L1', libelle: 'Licence 1', semestres: [1, 2] }, { code: 'L2', libelle: 'Licence 2', semestres: [3, 4] }])
      if (url === '/admin/ues') return ok([{
        id: 70, code: 'TRPR', intitule: 'Technique de résolution de problèmes', semestre: 4, filiere_id: 31, filiere: { id: 31, code: 'GL-L2' },
        filieres: [{ id: 31, code: 'GL-L2' }, { id: 32, code: 'IM-L2' }], annee_id: 3, volume_horaire: 40, ecs: [],
      }])
      return ok([])
    })
    api.post.mockImplementation(() => ok({}))
  })

  it('montre les filières qui suivent un cours commun', async () => {
    renderPage(<UEManagementPage />)
    expect(await screen.findByText('Commune à GL-L2, IM-L2')).toBeInTheDocument()
  })

  // Un cours commun devait être dupliqué par filière : deux UE, deux séances.
  it("ne propose que les filières du même niveau, et envoie celles qui suivent l'UE", async () => {
    renderPage(<UEManagementPage />)
    await screen.findByText('Technique de résolution de problèmes')

    fireEvent.click(screen.getByRole('button', { name: /Nouvelle UE/ }))
    fireEvent.change(screen.getByLabelText('Filière *'), { target: { value: '31' } })

    const suiviePar = screen.getByRole('group', { name: 'Aussi suivie par' })
    expect(within(suiviePar).getByLabelText('IM-L2')).toBeInTheDocument()
    expect(within(suiviePar).queryByLabelText('IM-L1')).not.toBeInTheDocument()
    fireEvent.click(within(suiviePar).getByLabelText('IM-L2'))

    const formulaire = suiviePar.closest('form')
    const [code, intitule] = within(formulaire).getAllByRole('textbox')
    fireEvent.change(code, { target: { value: 'COM1' } })
    fireEvent.change(intitule, { target: { value: 'Cours commun' } })
    fireEvent.submit(formulaire)

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/admin/ues', expect.objectContaining({ filiere_id: '31', filiere_ids: ['32'] })))
  })
})
