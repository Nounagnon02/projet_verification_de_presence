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

import PresenceQueuePage from '../pages/attendance/PresenceQueuePage'

const SUSPECT = {
  id: 11,
  statut: 'suspect',
  heure_scan: '2026-04-14T14:47:00+01:00',
  latitude: 6.4,
  longitude: 2.3,
  device_fingerprint: 'tel-1',
  etudiant: { id: 'e2', nom: 'SOTON', prenom: 'Victorin', matricule: '24-2068', filiere: { code: 'MIAGE-M1' } },
  evenement: {
    heure_debut: '13:00:00', heure_fin: '15:00:00', salle: 'Salle 301',
    ec: { code: 'GEST-PROJ-SI', intitule: 'Gestion de Projet SI' },
  },
  meme_appareil: [
    { id: 10, etudiant: { nom: 'AHOUANDJINOU', prenom: 'Valérie' }, heure_scan: '2026-04-14T14:46:00+01:00', statut: 'suspect' },
    { id: 9, etudiant: { nom: 'AGOSSOU', prenom: 'Yves' }, heure_scan: '2026-04-14T14:45:00+01:00', statut: 'rejete' },
  ],
}

const paginateur = (lignes) => ({
  data: {
    success: true,
    data: { current_page: 1, data: lignes, from: 1, to: lignes.length, last_page: 1, per_page: 20, total: lignes.length },
  },
})

const appelsFile = () => api.get.mock.calls.filter(([url]) => url === '/admin/presence/pending')

describe('File de validation des présences', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.get.mockImplementation((url) => Promise.resolve(
      url === '/admin/presence/pending' ? paginateur([SUSPECT]) : { data: { success: true, data: [] } },
    ))
  })

  it('montre la raison : les autres étudiants passés par le même téléphone', async () => {
    renderPage(<PresenceQueuePage />)

    expect((await screen.findAllByText('Même téléphone que :')).length).toBeGreaterThan(0)
    expect(screen.getAllByText(/Valérie AHOUANDJINOU/).length).toBeGreaterThan(0)
    // Un voisin déjà rejeté est signalé comme tel.
    expect(screen.getAllByText(/Yves AGOSSOU \(.*rejeté\)/).length).toBeGreaterThan(0)
  })

  it('ne propose plus les onglets de statuts qui n\'existent pas', async () => {
    renderPage(<PresenceQueuePage />)
    await screen.findAllByText('Même téléphone que :')

    for (const onglet of ['Tous', 'Suspects', 'En attente', 'Invalides']) {
      expect(screen.queryByRole('button', { name: onglet })).toBeNull()
    }
  })

  it('envoie la recherche au serveur', async () => {
    renderPage(<PresenceQueuePage />)
    await screen.findAllByText('Même téléphone que :')

    fireEvent.change(screen.getByPlaceholderText(/Rechercher un étudiant/), { target: { value: 'SOTON' } })

    await waitFor(() => {
      expect(appelsFile().some(([, options]) => options?.params?.search === 'SOTON')).toBe(true)
    })
  })

  it('affiche les heures de séance sans les secondes', async () => {
    renderPage(<PresenceQueuePage />)

    expect((await screen.findAllByText('Séance 13:00 – 15:00')).length).toBeGreaterThan(0)
  })
})
