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

import SaisieManuellePage from '../pages/attendance/SaisieManuellePage'

const SEANCES = [
  { id: 7, date: '2026-03-10', heure_debut: '08:00:00', heure_fin: '10:00:00', statut: 'termine', ec: { code: 'PROG-C', intitule: 'Programmation en C' }, filiere: { code: 'IM-L1' }, salle: 'Amphi A' },
  { id: 8, date: '2026-03-10', heure_debut: '14:00:00', heure_fin: '16:00:00', statut: 'annule', ec: { code: 'ALG', intitule: 'Algèbre' }, filiere: { code: 'IM-L1' } },
]

const LISTE = {
  seance: { id: 7, date: '2026-03-10', heure_debut: '08:00', heure_fin: '10:00', cours: 'Programmation en C' },
  etudiants: [
    { id: 'e1', nom: 'ADJOVI', prenom: 'Marie', matricule: '2024004', presence: { id: 1, statut: 'valide' } },
    { id: 'e2', nom: 'AGOSSOU', prenom: 'Marc', matricule: '2024001', presence: { id: 2, statut: 'suspect' } },
    { id: 'e3', nom: 'KOUASSI', prenom: 'Jean', matricule: '2024010', presence: null },
  ],
}

const ok = (data) => Promise.resolve({ data: { success: true, data } })
const appelsSeances = () => api.get.mock.calls.filter(([url]) => url === '/admin/evenements')

const ouvrirSeance = async () => {
  renderPage(<SaisieManuellePage />)
  fireEvent.click(await screen.findByRole('button', { name: /Programmation en C/ }))
  await screen.findAllByText('Jean KOUASSI')
}

describe('Saisie manuelle', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.post.mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/admin/evenements') return ok(SEANCES)
      if (url === '/admin/presence/manuelle/7/etudiants') return ok(LISTE)
      return ok([])
    })
  })

  it('liste les séances de la date choisie, une séance annulée restant inaccessible', async () => {
    renderPage(<SaisieManuellePage />)

    expect(await screen.findByRole('button', { name: /Programmation en C/ })).toBeEnabled()
    expect(screen.getByRole('button', { name: /Algèbre/ })).toBeDisabled()
    expect(screen.getByText('Annulée')).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Date de la séance'), { target: { value: '2026-03-09' } })
    await waitFor(() => {
      expect(appelsSeances().some(([, o]) => o?.params?.date_debut === '2026-03-09' && o?.params?.date_fin === '2026-03-09')).toBe(true)
    })
  })

  it('montre chaque étudiant attendu avec sa présence, et ne propose la saisie qu\'aux absents', async () => {
    await ouvrirSeance()

    expect(screen.getAllByText('Présent').length).toBeGreaterThan(0)
    expect(screen.getAllByText("Suspect — file d'attente").length).toBeGreaterThan(0)
    expect(screen.getAllByText('Absent').length).toBeGreaterThan(0)
    expect(screen.getByText(/3 attendu\(s\) · 1 présent\(s\) · 1 absent\(s\) · 1 à arbitrer/)).toBeInTheDocument()

    // Un seul absent, donc une seule action (le tableau peut doubler ses lignes
    // pour l'affichage mobile : on compte les lignes distinctes).
    const actions = screen.getAllByRole('button', { name: 'Marquer présent' })
    expect(new Set(actions.map((b) => b.closest('tr, li, [role=row]')?.textContent)).size).toBe(1)
  })

  it('marque présent avec un motif obligatoire', async () => {
    api.post.mockResolvedValue({ data: { success: true, message: 'Présence enregistrée.', data: { presence: { id: 30, statut: 'valide' } } } })
    await ouvrirSeance()

    fireEvent.click(screen.getAllByRole('button', { name: 'Marquer présent' })[0])
    const fenetre = screen.getByRole('dialog')
    const confirmer = within(fenetre).getByRole('button', { name: /Marquer présent/ })
    expect(confirmer).toBeDisabled()

    fireEvent.change(within(fenetre).getByLabelText(/Motif/), { target: { value: 'Téléphone déchargé' } })
    fireEvent.click(confirmer)

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/admin/presence/manuelle', { evenement_id: 7, etudiant_id: 'e3', motif: 'Téléphone déchargé' })
    })
    await waitFor(() => expect(screen.queryAllByRole('button', { name: 'Marquer présent' })).toHaveLength(0))
  })

  it('affiche le refus du serveur dans la fenêtre', async () => {
    api.post.mockRejectedValue({ response: { status: 422, data: { message: "Cette séance n'a pas encore commencé." } } })
    await ouvrirSeance()

    fireEvent.click(screen.getAllByRole('button', { name: 'Marquer présent' })[0])
    const fenetre = screen.getByRole('dialog')
    fireEvent.change(within(fenetre).getByLabelText(/Motif/), { target: { value: 'Présent' } })
    fireEvent.click(within(fenetre).getByRole('button', { name: /Marquer présent/ }))

    expect(await within(fenetre).findByText("Cette séance n'a pas encore commencé.")).toBeInTheDocument()
  })
})
