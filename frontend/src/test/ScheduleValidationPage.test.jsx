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
const FILIERES = [{ id: 5, code: 'IM-L2', intitule: 'Informatique L2', niveau: 'L2', semestres: [3, 4], etablissement_id: 1 }]

const SALLES = [
  { id: 9, nom: 'A-101', code: 'A-101', etablissement_id: 1, verifie_gps: true, verifie_wifi: false },
  { id: 10, nom: 'B-204', code: 'B-204', etablissement_id: 1, verifie_gps: false, verifie_wifi: false },
]

const cle = (nom) => nom.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim()

/** Reconnaissance telle que le serveur la rend : chaque nom, et sa salle ou null. */
const reconnaissance = (salles) => http.post(`${API}/admin/salles/reconnaitre`, async ({ request }) => {
  const { noms } = await request.json()
  return succes(noms.map((nom) => ({ nom, salle: salles.find((s) => cle(s.nom) === cle(nom)) ?? null, desactivee: false })))
})

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
    http.get(`${API}/admin/salles/disponibles`, () => succes(SALLES)),
    reconnaissance(SALLES),
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

async function choisirDestination(user, { attendre = true } = {}) {
  await waitFor(() => expect(screen.getByRole('option', { name: /IM-L2/ })).toBeInTheDocument())
  await user.selectOptions(screen.getByLabelText(/Filière/i), '5')
  // La reconnaissance des salles part avec la destination.
  if (attendre) await waitFor(() => expect(screen.getByLabelText('Salle pour « B-204 »')).toHaveValue('10'))
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

  // « Emploi du temps ... du 15 juin 2026 » : la version du document, lue par
  // l'IA, pre-remplit la validite, que l'on peut corriger.
  it('reprend la date lue dans le document et l envoie avec le lot', async () => {
    sessionStorage.setItem('import_analysis', JSON.stringify({ ...ANALYSE, result: { ...ANALYSE.result, valide_du: '2026-06-15' } }))
    server.use(http.post(`${API}/admin/import/schedule/verifier`,
      () => succes({ total: 2, valides: 2, lignes: [] })))

    const user = await afficher()
    expect(screen.getByLabelText('Valable du')).toHaveValue('2026-06-15')
    expect(screen.getByText(/Date lue dans le document/)).toBeInTheDocument()

    await choisirDestination(user)
    await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))

    await waitFor(() => expect(mouchard.filtrer('POST', 'schedule/verifier')).toHaveLength(1))
    expect(mouchard.filtrer('POST', 'schedule/verifier')[0].corps.valide_du).toBe('2026-06-15')
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

  describe('salle de chaque creneau', () => {
    it('pre-remplit les salles reconnues et envoie leur identifiant', async () => {
      server.use(http.post(`${API}/admin/import/schedule/verifier`,
        () => succes({ total: 2, valides: 2, lignes: [] })))

      const user = await afficher()
      await choisirDestination(user)

      expect(screen.getByLabelText('Salle pour « A-101 »')).toHaveValue('9')
      await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))

      await waitFor(() => expect(mouchard.filtrer('POST', 'schedule/verifier')).toHaveLength(1))
      const [premier, second] = mouchard.filtrer('POST', 'schedule/verifier')[0].corps.creneaux
      expect(premier).toMatchObject({ salle_id: 9, sans_salle: false })
      expect(second).toMatchObject({ salle_id: 10, sans_salle: false })
    })

    it('bloque la verification tant qu une salle lue n est pas choisie, et accepte « Aucune »', async () => {
      server.use(
        reconnaissance(SALLES.filter((s) => s.id === 9)),
        http.post(`${API}/admin/import/schedule/verifier`, () => succes({ total: 2, valides: 2, lignes: [] })),
      )

      const user = await afficher()
      await choisirDestination(user, { attendre: false })
      await waitFor(() => expect(screen.getByLabelText('Salle pour « A-101 »')).toHaveValue('9'))

      expect(screen.getByLabelText('Salle pour « B-204 »')).toHaveValue('a-choisir')
      expect(screen.getByText('Salle à choisir')).toBeInTheDocument()

      await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))
      expect(await screen.findByText(/1 créneau\(x\) ont une salle à choisir/)).toBeInTheDocument()
      expect(mouchard.filtrer('POST', 'schedule/verifier')).toHaveLength(0)

      await user.selectOptions(screen.getByLabelText('Salle pour « B-204 »'), 'aucune')
      await user.click(screen.getByRole('button', { name: /Vérifier sans enregistrer/i }))

      await waitFor(() => expect(mouchard.filtrer('POST', 'schedule/verifier')).toHaveLength(1))
      expect(mouchard.filtrer('POST', 'schedule/verifier')[0].corps.creneaux[1]).toMatchObject({ salle_id: null, sans_salle: true })
    })

    it('« Créer » cree la salle une fois et l applique a toutes les lignes du meme nom', async () => {
      sessionStorage.setItem('import_analysis', JSON.stringify({
        ...ANALYSE,
        result: {
          ...ANALYSE.result,
          events: [
            { ec_libelle: 'Algorithmique avancée', jour_semaine: 1, heure_debut: '08:00', heure_fin: '10:00', salle: 'Labo 3' },
            { ec_libelle: 'Bases de données', jour_semaine: 3, heure_debut: '08:00', heure_fin: '10:00', salle: 'labo 3' },
            { ec_libelle: 'Réseaux', jour_semaine: 4, heure_debut: '08:00', heure_fin: '10:00', salle: 'A-101' },
          ],
        },
      }))
      const LABO = { id: 42, nom: 'Labo 3', code: 'LABO-3', etablissement_id: 1, verifie_gps: false, verifie_wifi: false }
      server.use(http.post(`${API}/admin/salles/depuis-nom`,
        () => succes(LABO, 'Salle « Labo 3 » créée. Elle ne vérifie que le QR code : GPS et Wi-Fi à configurer dans Paramètres > Salles.')))

      const user = await afficher()
      await choisirDestination(user, { attendre: false })
      await waitFor(() => expect(screen.getByLabelText('Salle pour « A-101 »')).toHaveValue('9'))

      await user.selectOptions(screen.getByLabelText('Salle pour « Labo 3 »'), 'creer')

      await waitFor(() => expect(screen.getByLabelText('Salle pour « Labo 3 »')).toHaveValue('42'))
      expect(screen.getByLabelText('Salle pour « labo 3 »')).toHaveValue('42')
      expect(screen.getByLabelText('Salle pour « A-101 »')).toHaveValue('9')

      const creations = mouchard.filtrer('POST', 'salles/depuis-nom')
      expect(creations).toHaveLength(1)
      expect(creations[0].corps).toEqual({ filiere_id: 5, nom: 'Labo 3' })
      expect(screen.getByText(/GPS et Wi-Fi à configurer/)).toBeInTheDocument()
    })
  })
})
