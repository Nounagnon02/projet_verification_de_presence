import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, fireEvent, waitFor } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

/**
 * Régression : /admin/evenements est désormais paginé côté serveur (voir
 * backend/app/Http/Controllers/Api/Admin/EvenementController.php) — sans
 * filtre de date, la page chargeait auparavant TOUTES les séances jamais
 * créées. Ces tests verrouillent la consommation du contrat paginé
 * (data/meta), pas seulement sa tolérance à son absence.
 */
const { api } = vi.hoisted(() => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'token' }))

import EvenementManagementPage from '../pages/events/EvenementManagementPage'

const seance = (id, jour) => ({
  id,
  date: `2030-01-${jour}`,
  heure_debut: '08:00:00',
  heure_fin: '10:00:00',
  statut: 'planifie',
  ec: { id: 3, code: 'ALG', intitule: 'Algorithmique' },
  filiere: { id: 2, code: 'IM-L1' },
  annee_id: 1,
  salle: null,
  salle_id: null,
})

const pageDe = (page, total) => ({
  data: {
    success: true,
    data: [seance(page, String(10 + page).padStart(2, '0'))],
    meta: { current_page: page, last_page: 3, per_page: 1, total, from: page, to: page },
  },
})

describe('EvenementManagementPage — pagination', () => {
  beforeEach(() => {
    api.get.mockImplementation((url, config) => {
      if (url !== '/admin/evenements') return Promise.resolve({ data: { success: true, data: [] } })
      return Promise.resolve(pageDe(config?.params?.page ?? 1, 3))
    })
  })

  it('demande la page 1 au premier chargement et affiche le total', async () => {
    renderPage(<EvenementManagementPage />)

    await waitFor(() => expect(api.get).toHaveBeenCalledWith(
      '/admin/evenements',
      expect.objectContaining({ params: expect.objectContaining({ page: 1 }) }),
    ))
    expect(await screen.findByText(/sur 3/)).toBeInTheDocument()
  })

  it('change de page au clic sans réinitialiser les filtres', async () => {
    renderPage(<EvenementManagementPage />)
    await screen.findByText(/sur 3/)

    fireEvent.click(screen.getByRole('button', { name: '2' }))

    await waitFor(() => expect(api.get).toHaveBeenCalledWith(
      '/admin/evenements',
      expect.objectContaining({ params: expect.objectContaining({ page: 2 }) }),
    ))
  })

  it('revient à la page 1 quand un filtre change, sans requête intermédiaire sur l\'ancienne page', async () => {
    renderPage(<EvenementManagementPage />)
    await screen.findByText(/sur 3/)

    fireEvent.click(screen.getByRole('button', { name: '2' }))
    await waitFor(() => expect(api.get).toHaveBeenCalledWith(
      '/admin/evenements',
      expect.objectContaining({ params: expect.objectContaining({ page: 2 }) }),
    ))

    api.get.mockClear()
    fireEvent.change(screen.getByLabelText(/Statut/i, { selector: 'select' }), { target: { value: 'annule' } })

    await waitFor(() => expect(api.get).toHaveBeenCalledWith(
      '/admin/evenements',
      expect.objectContaining({ params: expect.objectContaining({ page: 1, statut: 'annule' }) }),
    ))
    // Une seule requête vers /admin/evenements : jamais page 2 + le nouveau filtre.
    expect(api.get.mock.calls.filter(([u]) => u === '/admin/evenements')).toHaveLength(1)
  })
})
