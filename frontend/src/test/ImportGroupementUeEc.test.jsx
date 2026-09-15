import { describe, it, expect, beforeAll, afterAll, beforeEach, afterEach, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { http } from 'msw'
import { server } from './msw/server'
import { succes } from './msw/handlers'
import { installerMouchard } from './msw/mouchard'

const API = '*/api'

// Resultat de Gemini, au format de la consigne : les UE portent leurs EC,
// imbriques, avec leurs heures par type.
const ANALYSE = {
  analysis_id: 7,
  type: 'courses',
  status: 'completed',
  result: {
    ues: [
      {
        code: 'INF1322', intitule: 'Approche orientée objet', semestre: 3, credits: 6,
        ecs: [
          { code: '1INF1322', intitule: 'Analyse et conception', cm: 10, td_tp: 15, tpe: 25, ctt: 50 },
          { code: '2INF1322', intitule: 'Programmation', cm: 20, td_tp: 30, tpe: 50, ctt: 100 },
        ],
      },
      { code: 'ANG1325', intitule: 'Anglais scientifique', semestre: 3, credits: 3, ecs: [] },
    ],
  },
}

const FILIERES = [
  { id: 4, code: 'IM-L1', intitule: 'Informatique L1', niveau: 'L1', semestres: [1, 2] },
  { id: 5, code: 'IM-L2', intitule: 'Informatique L2', niveau: 'L2', semestres: [3, 4] },
]

const ANNEES = [{ id: 3, libelle: '2025-2026', active: true }]

let mouchard

vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast: () => {} }) }))

import CourseValidationPage from '../pages/import/CourseValidationPage'

beforeAll(() => server.listen({ onUnhandledRequest: 'bypass' }))
afterAll(() => server.close())

beforeEach(() => {
  sessionStorage.clear()
  sessionStorage.setItem('import_courses_analysis', JSON.stringify(ANALYSE))
  server.use(
    http.get(`${API}/admin/annees-academiques`, () => succes(ANNEES)),
    http.get(`${API}/admin/filieres`, () => succes(FILIERES)),
    http.post(`${API}/admin/import/validate-courses`, () => succes({ created: [] })),
  )
  mouchard = installerMouchard()
})

afterEach(() => {
  mouchard.desinstaller()
  server.resetHandlers()
})

async function afficher() {
  const user = userEvent.setup()
  render(<MemoryRouter><CourseValidationPage /></MemoryRouter>)
  await screen.findByText(/Données extraites/)
  return user
}

describe('Validation des cours extraits', () => {
  // Regression : l'ecran mettait UE et EC a plat, puis envoyait CHAQUE ligne en
  // tant qu'UE avec « ecs: [] ». Un catalogue de 2 UE et 2 EC creait 4 UE, dont
  // aucune ne portait d'EC.
  it('distingue les UE des EC a l ecran', async () => {
    await afficher()

    expect(screen.getByText(/2 UE/)).toBeInTheDocument()
    expect(screen.getByText(/2 EC/)).toBeInTheDocument()
  })

  it('renvoie les EC SOUS leur UE, et non comme des UE', async () => {
    const user = await afficher()

    await waitFor(() => expect(screen.getByRole('option', { name: /IM-L2/ })).toBeInTheDocument())
    await user.selectOptions(screen.getByLabelText(/Filière/i), '5')

    await user.click(screen.getByRole('button', { name: /Valider et enregistrer/i }))

    await waitFor(() => expect(mouchard.filtrer('POST', 'validate-courses')).toHaveLength(1))

    const { ues } = mouchard.filtrer('POST', 'validate-courses')[0].corps

    expect(ues).toHaveLength(2)
    expect(ues[0].code).toBe('INF1322')
    expect(ues[0].ecs.map((e) => e.code)).toEqual(['1INF1322', '2INF1322'])
    expect(ues[1].code).toBe('ANG1325')
    expect(ues[1].ecs).toEqual([])
    // Les heures par type, jamais le TPE ni le CTT.
    expect(ues[0].ecs[0]).toEqual({ code: '1INF1322', intitule: 'Analyse et conception', volume_cm: 10, volume_td: 0, volume_tp: 0, volume_td_tp: 15 })
  })

  // Maquette reelle de l'IFRI : l'IA ne rendait qu'un total, le CTT (125 h pour
  // 5 credits, TPE compris). L'ecran le disait « Pret » et l'enregistrait
  // « a ventiler » : l'EC ne se serait jamais termine.
  it("ne tient pas un total pour des heures de cours : l'EC reste a completer", async () => {
    sessionStorage.setItem('import_courses_analysis', JSON.stringify({
      ...ANALYSE,
      result: { ues: [{ code: 'MTH1321', intitule: 'Structures algébriques', semestre: 3, credits: 5, ecs: [
        { code: '1MTH1321', intitule: 'Structures algébriques', volume_horaire: 125 },
      ] }] },
    }))
    const user = await afficher()

    expect(screen.getByText('Heures à saisir')).toBeInTheDocument()
    expect(screen.getByText(/Lu dans le document : total 125 h/)).toBeInTheDocument()

    await waitFor(() => expect(screen.getByRole('option', { name: /IM-L2/ })).toBeInTheDocument())
    await user.selectOptions(screen.getByLabelText(/Filière/i), '5')
    await user.click(screen.getByRole('button', { name: /Valider et enregistrer/i }))

    await waitFor(() => expect(mouchard.filtrer('POST', 'validate-courses')).toHaveLength(1))
    const { ues } = mouchard.filtrer('POST', 'validate-courses')[0].corps
    expect(ues[0].ecs).toEqual([])
  })

  // Regression : filiere_id et annee_id etaient ecrits en dur a 1. Toute analyse
  // atterrissait dans la premiere filiere et la premiere annee de la base.
  it('envoie la filiere et l annee choisies, non des constantes', async () => {
    const user = await afficher()

    await waitFor(() => expect(screen.getByRole('option', { name: /IM-L2/ })).toBeInTheDocument())
    await user.selectOptions(screen.getByLabelText(/Filière/i), '5')
    await user.click(screen.getByRole('button', { name: /Valider et enregistrer/i }))

    await waitFor(() => expect(mouchard.filtrer('POST', 'validate-courses')).toHaveLength(1))

    const { ues } = mouchard.filtrer('POST', 'validate-courses')[0].corps

    expect(ues.every((u) => u.filiere_id === 5)).toBe(true)
    expect(ues.every((u) => u.annee_id === 3)).toBe(true)
    expect(ues.some((u) => u.filiere_id === 1)).toBe(false)
  })

  // Le semestre determine le niveau : S3 est en L2. Proposer IM-L1 conduisait a
  // un refus du serveur, ou pire, a une maquette incoherente.
  it('ne propose que les filieres du niveau designe par les semestres', async () => {
    await afficher()

    await waitFor(() => expect(screen.getByRole('option', { name: /IM-L2/ })).toBeInTheDocument())
    expect(screen.queryByRole('option', { name: /IM-L1/ })).not.toBeInTheDocument()
  })

  it('refuse de valider sans destination', async () => {
    const user = await afficher()

    expect(screen.getByRole('button', { name: /Valider et enregistrer/i })).toBeDisabled()

    await user.click(screen.getByRole('button', { name: /Valider et enregistrer/i }))
    expect(mouchard.filtrer('POST', 'validate-courses')).toHaveLength(0)
  })
})
