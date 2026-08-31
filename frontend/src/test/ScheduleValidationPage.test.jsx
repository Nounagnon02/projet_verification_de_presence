import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { http } from 'msw'
import { server } from './msw/server'
import { succes, echec } from './msw/handlers'
import { installerMouchard } from './msw/mouchard'

const API = '*/api'

// Analyse telle que le nouveau pipeline la depose : des creneaux HEBDOMADAIRES,
// sans date. L'ancien pipeline les ecartait tous.
const ANALYSE = {
  analysis_id: 11,
  type: 'schedule',
  status: 'completed',
  score_de_confiance: 1,
  result: {
    events: [
      { ec_libelle: 'Algorithmique avancée', jour_semaine: 1, date: null, heure_debut: '08:00', heure_fin: '10:00', salle: 'A-101', enseignants: ['Ratheil HOUNDJI'] },
      { ec_libelle: 'Bases de données', jour_semaine: 2, date: null, heure_debut: '14:00', heure_fin: '16:00', salle: 'B-204', enseignants: [] },
    ],
    diagnostic: { statut: 'valide', message: '2 créneau(x) extrait(s).', retenus: [], ecartes: [], remarques: [] },
  },
  metadata: { filename: 'edt-ifri.pdf' },
}

const ANNEES = [{ id: 3, libelle: '2025-2026', active: true }]
const FILIERES = [{ id: 5, code: 'IM-L2', intitule: 'Informatique L2', niveau: 'L2', semestres: [3, 4] }]

let mouchard

vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast: () => {} }) }))

import ScheduleValidationPage from '../pages/import/ScheduleValidationPage'

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }))
afterAll(() => server.close())

beforeEach(() => {
  sessionStorage.clear()
  sessionStorage.setItem('import_analysis', JSON.stringify(ANALYSE))
  server.use(
    http.get(`${API}/admin/annees-academiques`, () => succes(ANNEES)),
    http.get(`${API}/admin/filieres`, () => succes(FILIERES)),
  )
  mouchard = installerMouchard()
})

afterEach(() => {
  mouchard.desinstaller()
  server.resetHandlers()
})

async function afficher() {
  const user = userEvent.setup()
  render(<MemoryRouter><ScheduleValidationPage /></MemoryRouter>)
  await screen.findByText(/Destination/)
  return user
}

async function choisirDestination(user) {
  await waitFor(() => expect(screen.getByRole('option', { name: /IM-L2/ })).toBeInTheDocument())
  await user.selectOptions(screen.getByLabelText(/Filière/i), '5')
}

describe('Validation d un emploi du temps extrait', () => {
  // Regression : getDayName ne savait lire qu'une date. Un creneau hebdomadaire
  // n'en a pas : toute la liste affichait « — ».
  it('affiche le jour de la semaine d un creneau sans date', async () => {
    await afficher()

    expect(screen.getByText('Lundi')).toBeInTheDocument()
    expect(screen.getByText('Mardi')).toBeInTheDocument()
    expect(screen.queryByText('—')).not.toBeInTheDocument()
  })

  it('indique que le creneau est hebdomadaire plutot qu une date absente', async () => {
    await afficher()

    expect(screen.getAllByText(/chaque semaine/i).length).toBeGreaterThan(0)
  })

  // Regression : la page postait vers /import/validate-events, qui exige ec_id,
  // filiere_id et annee_id par evenement. Elle envoyait les creneaux bruts :
  // l'appel repartait en 422 a tous les coups.
  it('verifie aupres du serveur SANS enregistrer', async () => {
    server.use(http.post(`${API}/admin/import/schedule/verifier`,
      () => succes({ total: 2, valides: 2, lignes: [] })))

    const user = await afficher()
    await choisirDestination(user)

    await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))

    await waitFor(() => expect(mouchard.filtrer('POST', 'schedule/verifier')).toHaveLength(1))

    const corps = mouchard.filtrer('POST', 'schedule/verifier')[0].corps
    expect(corps.filiere_id).toBe(5)
    expect(corps.annee_id).toBe(3)
    expect(corps.creneaux).toHaveLength(2)

    // Rien n'a ete confirme.
    expect(mouchard.filtrer('POST', 'schedule/confirmer')).toHaveLength(0)
  })

  it('n autorise l enregistrement qu apres verification', async () => {
    const user = await afficher()
    await choisirDestination(user)

    expect(screen.getByRole('button', { name: /Valider et enregistrer/i })).toBeDisabled()

    server.use(http.post(`${API}/admin/import/schedule/verifier`,
      () => succes({ total: 2, valides: 2, lignes: [] })))

    await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /Valider et enregistrer/i })).not.toBeDisabled())
  })

  it('refuse de verifier sans destination', async () => {
    await afficher()

    expect(screen.getByRole('button', { name: /Vérifier sans enregistrer/i })).toBeDisabled()
    expect(mouchard.filtrer('POST', 'schedule/verifier')).toHaveLength(0)
  })

  // Le rapport du serveur doit etre LU par l'utilisateur : c'est ce qui manquait
  // quand l'ecran devinait les conflits lui-meme.
  it('affiche le motif de chaque ligne refusee', async () => {
    server.use(http.post(`${API}/admin/import/schedule/verifier`, () => succes({
      total: 2,
      valides: 1,
      lignes: [
        { rang: 1, statut: 'valide', motifs: [] },
        { rang: 2, statut: 'introuvable', motifs: ["L'enseignement « Bases de données » n'existe pas dans la filière IM-L2."] },
      ],
    })))

    const user = await afficher()
    await choisirDestination(user)
    await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))

    await waitFor(() => expect(screen.getByText(/Ligne 2 — introuvable/)).toBeInTheDocument())
    expect(screen.getByText(/n'existe pas dans la filière IM-L2/)).toBeInTheDocument()
  })

  it('propose d ignorer les lignes refusees, sans le faire par defaut', async () => {
    server.use(
      http.post(`${API}/admin/import/schedule/verifier`, () => succes({
        total: 2, valides: 1,
        lignes: [{ rang: 1, statut: 'valide', motifs: [] }, { rang: 2, statut: 'conflit', motifs: ['Conflit de salle.'] }],
      })),
      http.post(`${API}/admin/import/schedule/confirmer`, () => succes({ enregistres: 1, refuses: 1 })),
    )

    const user = await afficher()
    await choisirDestination(user)
    await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))

    const case_ = await screen.findByRole('checkbox', { name: /N'enregistrer que/i })
    expect(case_).not.toBeChecked()

    await user.click(case_)
    await user.click(screen.getByRole('button', { name: /Valider et enregistrer/i }))

    await waitFor(() => expect(mouchard.filtrer('POST', 'schedule/confirmer')).toHaveLength(1))
    expect(mouchard.filtrer('POST', 'schedule/confirmer')[0].corps.ignorer_les_refuses).toBe(true)
  })

  it('affiche le rapport quand le serveur refuse l enregistrement', async () => {
    server.use(
      http.post(`${API}/admin/import/schedule/verifier`, () => succes({ total: 1, valides: 1, lignes: [] })),
      http.post(`${API}/admin/import/schedule/confirmer`, () => echec(
        "1 créneau(x) sur 1 ne peuvent pas être enregistrés. Rien n'a été écrit.",
        422,
        { data: { total: 1, valides: 0, lignes: [{ rang: 1, statut: 'conflit', motifs: ['Conflit de promotion.'] }] } },
      )),
    )

    const user = await afficher()
    await choisirDestination(user)
    await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))
    await waitFor(() => expect(screen.getByRole('button', { name: /Valider et enregistrer/i })).not.toBeDisabled())

    await user.click(screen.getByRole('button', { name: /Valider et enregistrer/i }))

    await waitFor(() => expect(screen.getByText(/Rien n'a été écrit/)).toBeInTheDocument())
    expect(screen.getByText(/Conflit de promotion/)).toBeInTheDocument()
  })
})
