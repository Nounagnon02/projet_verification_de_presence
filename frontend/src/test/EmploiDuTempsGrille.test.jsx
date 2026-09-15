import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, fireEvent, within } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import { invalidateApiCache } from '../api/cache'
import { disposer } from '../utils/emploiDuTemps'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))

import WeeklySchedulePage from '../pages/schedules/WeeklySchedulePage'

const ok = (data, message) => Promise.resolve({ data: { success: true, data, message } })

const creneau = (valeurs) => ({
  groupe_id: null, groupe: null, salle_id: null, salle: null, enseignant: null,
  valide_du: null, valide_au: null, conflits: [], filieres: ['IM-L2'], ...valeurs,
})

const INF = { id: 11, code: 'INF1322', intitule: 'Approche orientée objet' }
const MTH = { id: 12, code: 'MTH1321', intitule: 'Structures algébriques' }

const CRENEAUX = [
  creneau({ id: 1, ec_id: 11, ec: INF, jour_semaine: 1, heure_debut: '08:00', heure_fin: '10:00', type_cours: 'td', groupe_id: 5, groupe: 'G1', salle: 'Amphi A' }),
  creneau({ id: 2, ec_id: 11, ec: INF, jour_semaine: 1, heure_debut: '08:00', heure_fin: '10:00', type_cours: 'td', groupe_id: 6, groupe: 'G2', salle: 'Salle TD2' }),
  creneau({
    id: 3, ec_id: 12, ec: MTH, jour_semaine: 2, heure_debut: '10:00', heure_fin: '12:00', type_cours: 'cm', salle: 'Amphi A',
    conflits: ['Conflit de salle : Amphi A est déjà occupée Mardi de 10:00 à 12:00 par INF1325.'],
  }),
]

const ECS = [
  { ...INF, ue: { annee_id: 3, semestre: 3, filiere_id: 31, filieres: [{ id: 31, code: 'IM-L2' }, { id: 32, code: 'GL-L2' }] } },
  { ...MTH, ue: { annee_id: 3, semestre: 3, filiere_id: 31, filieres: [{ id: 31, code: 'IM-L2' }] } },
]

const reponses = (plus = {}) => (url) => {
  if (url in plus) return plus[url]()
  if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true, close: false }])
  if (url === '/admin/emploi-du-temps') return ok(CRENEAUX)
  if (url === '/admin/ecs') return ok(ECS)
  return ok([])
}

describe('Emploi du temps — la semaine type', () => {
  beforeEach(() => {
    invalidateApiCache()
    for (const methode of ['get', 'post', 'put', 'delete']) api[methode].mockReset()
    api.get.mockImplementation(reponses())
  })

  it('range les deux groupes côte à côte et signale le créneau en conflit', async () => {
    renderPage(<WeeklySchedulePage />)

    const groupes = await screen.findAllByRole('button', { name: 'INF1322, Lundi 08:00–10:00' })
    expect(groupes).toHaveLength(2)
    // Côte à côte : chacun sur la moitié de la colonne, au lieu de se recouvrir.
    expect(groupes.map((b) => b.style.width)).toEqual(['calc(50% - 4px)', 'calc(50% - 4px)'])

    expect(screen.getByRole('button', { name: 'MTH1321, Mardi 10:00–12:00, en conflit' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: /1 créneau en conflit/ })).toBeInTheDocument()
  })

  it("ajoute un créneau, et montre le refus du serveur tel qu'il est", async () => {
    api.post.mockImplementation(() => Promise.reject({
      response: { status: 422, data: { success: false, message: 'Conflit de promotion : IM-L2 a déjà INF1322 (groupe G1) Lundi de 08:00 à 10:00.' } },
    }))

    renderPage(<WeeklySchedulePage />)
    await screen.findAllByRole('button', { name: 'INF1322, Lundi 08:00–10:00' })

    fireEvent.click(screen.getByRole('button', { name: /Ajouter un créneau/ }))
    const cours = await screen.findByLabelText('Cours *')
    await waitFor(() => expect(within(cours).getByRole('option', { name: /MTH1321/ })).toBeInTheDocument())
    expect(within(cours).getByRole('option', { name: /INF1322 .* \(commun à IM-L2, GL-L2\)/ })).toBeInTheDocument()

    fireEvent.change(cours, { target: { value: '12' } })
    fireEvent.change(screen.getByLabelText('Enseignant'), { target: { value: ' Dr AGBO ' } })
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText(/Conflit de promotion : IM-L2 a déjà INF1322/)).toBeInTheDocument()
    expect(api.post).toHaveBeenCalledWith('/admin/emploi-du-temps', {
      ec_id: 12, jour_semaine: 1, heure_debut: '08:00', heure_fin: '10:00', type_cours: 'cm',
      groupe_id: null, salle_id: null, enseignant: 'Dr AGBO', valide_du: null, valide_au: null,
    })
  })

  it('le rapport liste les conflits déjà en base, sans rien corriger', async () => {
    api.get.mockImplementation(reponses({
      '/admin/emploi-du-temps/conflits': () => ok({
        creneaux: [],
        seances: [{
          quand: 'le 21/09/2026', a_venir: true,
          a: { ec_code: 'INF1322', groupe: null, heure_debut: '08:00', heure_fin: '10:00' },
          b: { ec_code: 'MTH1321', groupe: null, heure_debut: '09:00', heure_fin: '11:00' },
          motifs: ['Conflit de salle : Amphi A est déjà occupée le 21/09/2026 de 09:00 à 11:00 par MTH1321.'],
        }],
      }),
    }))

    renderPage(<WeeklySchedulePage />)
    await screen.findAllByRole('button', { name: 'INF1322, Lundi 08:00–10:00' })
    fireEvent.click(screen.getByRole('button', { name: 'Vérifier' }))

    expect(await screen.findByText('Entre séances : 1 conflit')).toBeInTheDocument()
    expect(screen.getByText("Dans l'emploi du temps : aucun conflit")).toBeInTheDocument()
    expect(api.get).toHaveBeenCalledWith('/admin/emploi-du-temps/conflits', { params: { annee_id: '3' } })
  })
})

describe('disposer', () => {
  it('range côte à côte ce qui se chevauche, en pleine largeur ce qui ne chevauche rien', () => {
    const places = disposer([
      { id: 'a', debut: 480, fin: 600 },
      { id: 'c', debut: 600, fin: 720 },
      { id: 'b', debut: 480, fin: 600 },
    ])

    expect(places.map((c) => [c.id, c.voie, c.voies])).toEqual([['a', 0, 2], ['b', 1, 2], ['c', 0, 1]])
  })
})
