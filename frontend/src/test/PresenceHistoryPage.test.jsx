import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, fireEvent, waitFor } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

// vi.mock est remonté en tête de module : le client factice doit être construit
// dans vi.hoisted.
const { api } = vi.hoisted(() => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'token' }))
vi.mock('../context/ToastContext', () => ({
  useToastCtx: () => ({ addToast: vi.fn() }),
  ToastProvider: ({ children }) => children,
}))

import PresenceHistoryPage from '../pages/attendance/PresenceHistoryPage'

const SEANCE = { id: 1, cours: 'Programmation en C', date: '2026-09-14', heure_debut: '08:13', heure_fin: '10:13' }
const LIGNES = [
  {
    id: 1, etudiant: { nom: 'KOUASSI', prenom: 'Jean', matricule: '2024003' }, evenement: SEANCE,
    heure_scan: '2026-09-14 10:03:57', statut: 'valide',
    origine: { origine: 'scan', libelle: 'Scan', decide_par: null, motif: null },
  },
  {
    id: 2, etudiant: { nom: 'OUSSENI', prenom: 'Olympe', matricule: '23-8122' }, evenement: SEANCE,
    heure_scan: '2026-09-14 10:04:10', statut: 'rejete',
    origine: { origine: 'rejetee_apres_examen', libelle: 'Rejeté après examen', decide_par: 'Administrateur IFRI', motif: 'Téléphone prêté' },
  },
  {
    id: 3, etudiant: { nom: 'NONVI', prenom: 'Franck', matricule: '24-1297' }, evenement: SEANCE,
    heure_scan: '2026-09-14 10:13:00', statut: 'valide',
    origine: { origine: 'saisie_manuelle', libelle: 'Saisie manuelle', decide_par: 'Administrateur IFRI', motif: 'Téléphone déchargé' },
  },
]

const reponse = { data: { success: true, data: LIGNES, meta: { current_page: 1, last_page: 1, per_page: 20, total: 3 } } }
const appelsHistorique = () => api.get.mock.calls.filter(([url]) => url === '/admin/presence/history')

describe('Historique des présences', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.get.mockImplementation((url) => Promise.resolve(
      url === '/admin/presence/history' ? reponse : { data: { success: true, data: [] } },
    ))
  })

  it('propose « Rejetés » et plus « Absents », qui ne pouvait rien trouver', async () => {
    renderPage(<PresenceHistoryPage />)
    await screen.findByText('Jean KOUASSI')

    expect(screen.getByRole('button', { name: 'Rejetés' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Absents' })).toBeNull()
    expect(screen.getByRole('link', { name: 'saisie manuelle' })).toHaveAttribute('href', '/attendance/scan')
  })

  it('nomme le statut rejeté au lieu de « rejete »', async () => {
    renderPage(<PresenceHistoryPage />)
    await screen.findByText('Jean KOUASSI')

    expect(screen.getByText('Rejeté')).toBeInTheDocument()
    expect(screen.queryByText('rejete')).toBeNull()
  })

  it('dit l\'origine de chaque présence, qui a décidé et pourquoi', async () => {
    renderPage(<PresenceHistoryPage />)
    await screen.findByText('Jean KOUASSI')

    expect(screen.getByText('Rejeté après examen')).toBeInTheDocument()
    expect(screen.getByText('Saisie manuelle')).toBeInTheDocument()
    expect(screen.getAllByText('par Administrateur IFRI')).toHaveLength(2)
    expect(screen.getByText('« Téléphone prêté »')).toBeInTheDocument()
  })

  it('affiche la date, l\'heure et l\'horaire de la séance lisiblement', async () => {
    renderPage(<PresenceHistoryPage />)
    await screen.findByText('Jean KOUASSI')

    expect(screen.getAllByText('14/09/2026')).toHaveLength(3)
    expect(screen.getByText('10:03')).toBeInTheDocument()
    expect(screen.getAllByText('Séance 08:13 – 10:13')).toHaveLength(3)
  })

  it('trie côté serveur quand on clique sur un en-tête', async () => {
    renderPage(<PresenceHistoryPage />)
    await screen.findByText('Jean KOUASSI')

    // Par défaut : les plus récentes d'abord.
    expect(appelsHistorique()[0][1].params).toMatchObject({ tri: 'date', sens: 'desc' })

    fireEvent.click(screen.getByText('Étudiant'))

    await waitFor(() => {
      expect(appelsHistorique().some(([, o]) => o.params.tri === 'etudiant' && o.params.sens === 'asc')).toBe(true)
    })
  })
})
