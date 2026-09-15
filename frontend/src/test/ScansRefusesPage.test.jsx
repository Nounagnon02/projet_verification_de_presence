import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, fireEvent, waitFor, within } from '@testing-library/react'
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

import ScansRefusesPage from '../pages/attendance/ScansRefusesPage'

const SEANCE = { id: 5, date: '2026-08-25', heure_debut: '08:00', heure_fin: '10:00', cours: 'Programmation en C', code: 'PROG-C' }
const ETUDIANT = { id: 'e1', nom: 'ADJOVI', prenom: 'Ange', matricule: 'DEMO-1', filiere: 'IM-L1' }

const REFUS = [
  // Séance connue, aucune présence : on peut rattraper l'étudiant.
  { id: 1, type: 'verification_echouee', description: 'Vous êtes à 320 m de la salle (rayon autorisé : 50 m).', etudiant: ETUDIANT, evenement: SEANCE, presence: null, creee_le: '2026-08-25T09:52:00+01:00' },
  // Refus ancien : séance non notée, rien à rattacher.
  { id: 2, type: 'invalid_scan_challenge', description: 'Challenge de scan invalide.', etudiant: ETUDIANT, evenement: null, presence: null, creee_le: '2026-08-18T10:50:00+01:00' },
  // Second scan : l'étudiant est déjà présent.
  { id: 3, type: 'double_scan_device_mismatch', description: 'Déjà scanné avec un appareil différent.', etudiant: ETUDIANT, evenement: SEANCE, presence: { id: 40, statut: 'valide', depuis_ce_refus: false }, creee_le: '2026-08-25T09:55:00+01:00' },
]

const paginateur = (lignes) => ({
  data: {
    success: true,
    data: { current_page: 1, data: lignes, from: 1, to: lignes.length, last_page: 1, per_page: 20, total: lignes.length },
  },
})

const appelsRefus = () => api.get.mock.calls.filter(([url]) => url === '/admin/alerts')

describe('Scans refusés', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.post.mockReset()
    api.get.mockImplementation((url) => Promise.resolve(
      url === '/admin/alerts' ? paginateur(REFUS) : { data: { success: true, data: [] } },
    ))
  })

  it('nomme chaque raison au lieu de « Inconnu »', async () => {
    renderPage(<ScansRefusesPage />)

    expect((await screen.findAllByText('Hors de la salle (GPS ou Wi-Fi)')).length).toBeGreaterThan(0)
    expect(screen.getAllByText('Défi de sécurité invalide').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Second scan depuis un autre téléphone').length).toBeGreaterThan(0)
    expect(screen.queryByText('Inconnu')).toBeNull()
  })

  it('ne propose ni valider ni rejeter : il n\'y a pas de présence à trancher', async () => {
    renderPage(<ScansRefusesPage />)
    await screen.findAllByText('Hors de la salle (GPS ou Wi-Fi)')

    for (const action of [/^Valide$/, /^Ignorer$/, /Valider/, /Rejeter/]) {
      expect(screen.queryByRole('button', { name: action })).toBeNull()
    }
  })

  it('donne la suite de chaque refus : présence possible, séance inconnue, déjà présent', async () => {
    renderPage(<ScansRefusesPage />)

    expect((await screen.findAllByRole('button', { name: 'Enregistrer la présence' })).length).toBeGreaterThan(0)
    expect(screen.getAllByText('Séance inconnue : rien à rattacher').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Présent').length).toBeGreaterThan(0)
    expect(screen.getAllByText('25/08/2026 · 08:00 – 10:00').length).toBeGreaterThan(0)
  })

  it('enregistre la présence avec un motif obligatoire', async () => {
    api.post.mockResolvedValue({ data: { success: true, message: 'Présence enregistrée.', data: { presence: { id: 99, statut: 'valide' } } } })
    renderPage(<ScansRefusesPage />)

    fireEvent.click((await screen.findAllByRole('button', { name: 'Enregistrer la présence' }))[0])
    const fenetre = screen.getByRole('dialog', { hidden: true })
    const confirmer = within(fenetre).getByRole('button', { name: 'Enregistrer la présence', hidden: true })

    // Sans motif, rien ne part.
    expect(confirmer).toBeDisabled()

    fireEvent.change(within(fenetre).getByLabelText(/Motif/), { target: { value: "Présent, confirmé par l'enseignant" } })
    fireEvent.click(confirmer)

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/admin/alerts/1/presence', { motif: "Présent, confirmé par l'enseignant" })
    })
    expect((await screen.findAllByText('Présence enregistrée depuis ce refus')).length).toBeGreaterThan(0)
  })

  it('affiche le refus du serveur dans la fenêtre', async () => {
    api.post.mockRejectedValue({ response: { status: 422, data: { message: "Ange ADJOVI n'est pas inscrit(e) au cours de cette séance." } } })
    renderPage(<ScansRefusesPage />)

    fireEvent.click((await screen.findAllByRole('button', { name: 'Enregistrer la présence' }))[0])
    const fenetre = screen.getByRole('dialog', { hidden: true })
    fireEvent.change(within(fenetre).getByLabelText(/Motif/), { target: { value: 'Présent' } })
    fireEvent.click(within(fenetre).getByRole('button', { name: 'Enregistrer la présence', hidden: true }))

    expect(await within(fenetre).findByText(/n'est pas inscrit\(e\) au cours/)).toBeInTheDocument()
  })

  it('envoie la recherche au serveur', async () => {
    renderPage(<ScansRefusesPage />)
    await screen.findAllByText('Hors de la salle (GPS ou Wi-Fi)')

    fireEvent.change(screen.getByPlaceholderText(/Rechercher un étudiant/), { target: { value: 'ADJOVI' } })

    await waitFor(() => {
      expect(appelsRefus().some(([, options]) => options?.params?.search === 'ADJOVI')).toBe(true)
    })
  })
})
