import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { screen, waitFor, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import { invalidateApiCache } from '../api/cache'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))

import CalendrierPage from '../pages/settings/CalendrierPage'
import AlerteCalendrier from '../components/ui/AlerteCalendrier'

const ok = (data, message) => Promise.resolve({ data: { success: true, data, message } })

const CALENDRIER = {
  annee: { id: 3, libelle: '2025-2026', date_debut: '2025-10-01', date_fin: '2026-09-30', close: false },
  periodes: [{ id: 1, parite: 'impair', date_debut: '2025-10-06', date_fin: '2026-02-21' }],
  fermetures: [{
    id: 9, type: 'ferie', type_libelle: 'Jour férié', libelle: 'Fête du Vodoun',
    date_debut: '2026-01-10', date_fin: '2026-01-10', portee: 'universite',
  }],
  alertes: [{ parite: 'pair', message: "Semestres pairs (S2, S4, S6…) : aucune période déclarée pour 2025-2026. Leurs séances ne sont pas générées depuis l'emploi du temps." }],
}

describe('Calendrier de la faculté', () => {
  beforeEach(() => {
    invalidateApiCache()
    for (const methode of ['get', 'post', 'put', 'delete']) api[methode].mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true, close: false }])
      if (url === '/admin/calendrier') return ok(CALENDRIER)
      return ok([])
    })
  })

  afterEach(() => vi.restoreAllMocks())

  it("signale le semestre sans période, et laisse à l'université ses jours fériés", async () => {
    renderPage(<CalendrierPage />)

    expect(await screen.findByText('Fête du Vodoun')).toBeInTheDocument()
    expect(screen.getByText(/Aucune période : les séances de ces semestres ne sont pas générées/)).toBeInTheDocument()
    expect(document.getElementById('periode-impair-debut')).toHaveValue('2025-10-06')
    expect(screen.queryByRole('button', { name: 'Retirer « Fête du Vodoun »' })).not.toBeInTheDocument()
  })

  // Retirer des séances planifiées sans prévenir : la fermeture s'annonce d'abord.
  it('annonce les séances retirées avant de déclarer une fermeture', async () => {
    const confirmer = vi.spyOn(window, 'confirm').mockReturnValue(true)
    api.post.mockImplementation((url, corps) => (corps.apercu
      ? ok({ seances_a_retirer: 3 })
      : ok({ id: 10, seances_retirees: 3 }, '« Examens du S1 » déclarée.')))

    renderPage(<CalendrierPage />)
    await screen.findByText('Fête du Vodoun')

    fireEvent.change(screen.getByLabelText('Type'), { target: { value: 'examens' } })
    fireEvent.change(screen.getByLabelText('Libellé'), { target: { value: 'Examens du S1' } })
    fireEvent.change(screen.getByLabelText('Du'), { target: { value: '2026-02-23' } })
    fireEvent.change(screen.getByLabelText('Au'), { target: { value: '2026-03-06' } })
    fireEvent.click(screen.getByRole('button', { name: 'Déclarer' }))

    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(2))
    expect(confirmer).toHaveBeenCalledWith(expect.stringContaining('3 séances à venir'))
    expect(api.post).toHaveBeenLastCalledWith('/admin/calendrier/fermetures', {
      type: 'examens', libelle: 'Examens du S1', date_debut: '2026-02-23', date_fin: '2026-03-06', annee_id: 3,
    })
  })

  it('ne déclare rien si la personne renonce', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    api.post.mockImplementation(() => ok({ seances_a_retirer: 2 }))

    renderPage(<CalendrierPage />)
    await screen.findByText('Fête du Vodoun')

    fireEvent.change(screen.getByLabelText('Libellé'), { target: { value: 'Vacances de Pâques' } })
    fireEvent.change(screen.getByLabelText('Du'), { target: { value: '2026-04-06' } })
    fireEvent.click(screen.getByRole('button', { name: 'Déclarer' }))

    await waitFor(() => expect(window.confirm).toHaveBeenCalled())
    expect(api.post).toHaveBeenCalledTimes(1)
  })

  it('le tableau de bord mène au calendrier quand un semestre est sans période', async () => {
    renderPage(<AlerteCalendrier />)

    expect(await screen.findByText(/Semestres pairs .* aucune période déclarée/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Déclarer les semestres/ })).toHaveAttribute('href', '/settings/calendrier')
  })
})
