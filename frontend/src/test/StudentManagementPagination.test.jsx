import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, fireEvent, waitFor } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

/**
 * Régression : la liste et les groupes de la promotion passent par
 * TanStack Query (voir src/api/resources/etudiants.js) au lieu de deux effets
 * manuels avec AbortController. Ces tests verrouillent le contrat consommé
 * (data/meta, pagination, invalidation après une action), pas seulement sa
 * tolérance à un format absent.
 */
const { api } = vi.hoisted(() => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn(), patch: vi.fn() },
}))

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'token' }))
vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast: vi.fn() }) }))

import StudentManagementPage from '../pages/students/StudentManagementPage'

const etudiant = (id) => ({
  id, nom: `NOM${id}`, prenom: `Prenom${id}`, matricule: `MAT-${id}`, email: `e${id}@uac.bj`,
  filiere: { id: 1, code: 'IM-L1' }, annee: { id: 9, annee: '2025-2026', active: true }, groupes: [],
})

const pageDe = (page, total) => ({
  data: {
    success: true,
    data: [etudiant(page)],
    meta: { current_page: page, last_page: 3, per_page: 1, total, from: page, to: page },
  },
})

const REFERENCE = new Set(['/admin/annees-academiques', '/admin/filieres', '/admin/niveaux', '/admin/groupes']);

describe('StudentManagementPage — pagination et rafraîchissement', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.get.mockImplementation((url, config) => {
      if (url === '/admin/students') return Promise.resolve(pageDe(config?.params?.page ?? 1, 3))
      if (REFERENCE.has(url)) return Promise.resolve({ data: { success: true, data: [] } })
      return Promise.resolve({ data: { success: true, data: [] } })
    })
  })

  it('demande la page 1 au premier chargement et affiche le total', async () => {
    renderPage(<StudentManagementPage />)

    await waitFor(() => expect(api.get).toHaveBeenCalledWith(
      '/admin/students',
      expect.objectContaining({ params: expect.objectContaining({ page: 1, per_page: 15 }) }),
    ))
    expect(await screen.findByText(/sur 3/)).toBeInTheDocument()
    expect(screen.getByText('NOM1')).toBeInTheDocument()
  })

  it('change de page au clic', async () => {
    renderPage(<StudentManagementPage />)
    await screen.findByText(/sur 3/)

    fireEvent.click(screen.getByRole('button', { name: '2' }))

    await waitFor(() => expect(api.get).toHaveBeenCalledWith(
      '/admin/students',
      expect.objectContaining({ params: expect.objectContaining({ page: 2 }) }),
    ))
    expect(await screen.findByText('NOM2')).toBeInTheDocument()
  })

  it('rafraîchit la liste après une suppression', async () => {
    api.delete.mockResolvedValue({ data: { success: true } })
    window.confirm = vi.fn(() => true)

    renderPage(<StudentManagementPage />)
    await screen.findByText('NOM1')

    const nbAppelsInitial = api.get.mock.calls.filter(([u]) => u === '/admin/students').length
    fireEvent.click(screen.getAllByTitle('Supprimer')[0])
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer' }))

    await waitFor(() => expect(api.delete).toHaveBeenCalledWith('/admin/students/1'))
    await waitFor(() => expect(
      api.get.mock.calls.filter(([u]) => u === '/admin/students').length,
    ).toBeGreaterThan(nbAppelsInitial))
  })
})
