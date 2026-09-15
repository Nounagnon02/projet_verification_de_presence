import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { screen, within, fireEvent, waitFor } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import { invalidateApiCache } from '../api/cache'

const A_VENIR = {
  id: 4, libelle: '2026-2027', active: false, en_cours_universite: false, statut: 'a_venir',
  date_debut: '2026-10-01', date_fin: '2027-09-30',
  etudiants_count: 0, ues_count: 0, emplois_du_temps_count: 0, evenements_count: 0,
}
const ACTIVE = {
  id: 3, libelle: '2025-2026', active: true, en_cours_universite: true, statut: 'en_cours',
  date_debut: '2025-10-01', date_fin: '2026-09-30',
  etudiants_count: 83, ues_count: 80, emplois_du_temps_count: 4, evenements_count: 132, seances_a_venir_count: 12,
}
const PASSEE = {
  id: 2, libelle: '2024-2025', active: false, en_cours_universite: false, statut: 'terminee',
  date_debut: '2024-10-01', date_fin: '2025-09-30',
  etudiants_count: 1, ues_count: 0, emplois_du_temps_count: 0, evenements_count: 0,
}

const { api, addToast } = vi.hoisted(() => {
  const vide = () => Promise.resolve({ data: { success: true, data: [] } })
  return {
    api: { get: vi.fn(vide), post: vi.fn(vide), put: vi.fn(vide), patch: vi.fn(vide), delete: vi.fn(vide) },
    addToast: vi.fn(),
  }
})

vi.mock('../api/axios', () => ({ default: api }))
vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast }) }))

import AcademicYearsPage from '../pages/settings/AcademicYearsPage'

const APERCU = {
  source: { id: 3, libelle: '2025-2026' },
  cible: { id: 4, libelle: '2026-2027' },
  filieres: [
    { id: 1, code: 'IM-L1', intitule: 'Informatique (L1)', source: { ues: 6, ecs: 12, creneaux: 4 }, cible: { ues: 0, ecs: 0, creneaux: 0 }, deja_preparee: false },
    { id: 2, code: 'IM-L2', intitule: 'Informatique (L2)', source: { ues: 6, ecs: 12, creneaux: 0 }, cible: { ues: 16, ecs: 22, creneaux: 0 }, deja_preparee: true },
  ],
}

function servir(annees) {
  api.get.mockImplementation((url) => Promise.resolve({
    data: { success: true, data: url.endsWith('/preparation') ? APERCU : annees },
  }))
}

describe('Années académiques — établissement', () => {
  beforeEach(() => {
    invalidateApiCache()
    vi.clearAllMocks()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 8, 14, 10, 0))
  })

  afterEach(() => vi.useRealTimers())

  it("montre les années sans permettre de les créer, modifier ou supprimer", async () => {
    servir([A_VENIR, ACTIVE, PASSEE])
    renderPage(<AcademicYearsPage />)

    const active = await screen.findByRole('article', { name: '2025-2026' })
    expect(within(active).getByText('Active pour votre établissement')).toBeInTheDocument()
    expect(within(active).getByText('En cours')).toBeInTheDocument()
    expect(within(active).getByText("83 étudiants · 80 UE · 4 créneaux d'emploi du temps · 132 séances")).toBeInTheDocument()
    // Dates lues comme des jours : le 1er octobre, pas le 30 septembre.
    expect(within(active).getByText('1 octobre 2025')).toBeInTheDocument()

    expect(within(screen.getByRole('article', { name: '2026-2027' })).getByText('Rien encore pour votre établissement')).toBeInTheDocument()
    expect(within(screen.getByRole('article', { name: '2024-2025' })).getByText('Terminée')).toBeInTheDocument()

    expect(screen.queryByRole('button', { name: /Nouvelle année/ })).not.toBeInTheDocument()
    expect(screen.queryByTitle(/Supprimer|Modifier/)).not.toBeInTheDocument()
  })

  it("ne propose de préparer qu'une année qui suit l'année active", async () => {
    servir([A_VENIR, ACTIVE, PASSEE])
    renderPage(<AcademicYearsPage />)

    const preparer = await screen.findAllByRole('button', { name: /^Préparer / })
    expect(preparer).toHaveLength(1)
    expect(screen.getByRole('article', { name: '2026-2027' })).toContainElement(preparer[0])
  })

  it("prépare l'année suivante sans refaire une maquette déjà en place", async () => {
    servir([A_VENIR, ACTIVE, PASSEE])
    api.post.mockResolvedValue({ data: { success: true, message: "1 filière(s) préparée(s) pour 2026-2027 : 6 UE, 12 EC, 4 créneau(x) d'emploi du temps.", data: {
      filieres: [{ code: 'IM-L1', ues: 6, ecs: 12, creneaux: 4, maquette_gardee: false, edt_garde: false }],
    } } })
    renderPage(<AcademicYearsPage />)

    fireEvent.click(await screen.findByRole('button', { name: 'Préparer 2026-2027' }))
    const fenetre = screen.getByRole('dialog', { name: 'Préparer 2026-2027' })

    expect(await within(fenetre).findByLabelText(/IM-L1/)).toBeChecked()
    expect(within(fenetre).getByLabelText(/IM-L2/)).not.toBeChecked()
    expect(fenetre).toHaveTextContent('6 UE · 12 EC · 4 créneaux à copier')
    expect(fenetre).toHaveTextContent('Déjà 16 UE en 2026-2027 : maquette gardée')
    expect(within(fenetre).getByLabelText(/Copier aussi l'emploi du temps/)).toBeChecked()

    fireEvent.click(within(fenetre).getByRole('button', { name: 'Préparer 2026-2027' }))

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/admin/annees-academiques/4/preparer', {
      source_annee_id: 3, filiere_ids: [1], avec_edt: true,
    }))
    expect(await within(fenetre).findByText(/1 filière\(s\) préparée\(s\) pour 2026-2027/)).toBeInTheDocument()
    expect(fenetre).toHaveTextContent('IM-L1 : 6 UE et 12 EC copiés ; 4 créneaux copiés')
    expect(within(fenetre).getByRole('link', { name: 'Aller à la promotion' })).toHaveAttribute('href', '/students')
  })

  it("prévient quand l'année active s'achève et que la suivante n'existe pas", async () => {
    servir([ACTIVE, PASSEE])
    renderPage(<AcademicYearsPage />)

    const alerte = await screen.findByRole('alert')
    expect(alerte).toHaveTextContent("2025-2026 se termine le 30 septembre 2026, dans 16 jours, et l'année suivante n'a pas encore été créée")
    expect(alerte).toHaveTextContent('Seul le super administrateur')
  })

  it("annonce ce que change le passage à une autre année, puis bascule l'établissement", async () => {
    servir([A_VENIR, ACTIVE, PASSEE])
    api.patch.mockResolvedValue({ data: { success: true, message: "2026-2027 est désormais l'année active de votre établissement." } })
    renderPage(<AcademicYearsPage />)

    fireEvent.click(await screen.findByRole('button', { name: 'Travailler sur 2026-2027' }))

    const fenetre = screen.getByRole('dialog', { name: 'Passer sur 2026-2027 ?' })
    expect(fenetre).toHaveTextContent('les nouvelles inscriptions iront dans 2026-2027')
    expect(fenetre).toHaveTextContent("aucune séance ne sera générée : 2026-2027 n'a pas encore d'emploi du temps")
    expect(fenetre).toHaveTextContent("12 séances déjà planifiées de 2025-2026 après aujourd'hui seront retirées")
    expect(fenetre).toHaveTextContent('Les autres établissements ne sont pas concernés.')

    fireEvent.click(within(fenetre).getByRole('button', { name: 'Passer sur 2026-2027' }))

    await waitFor(() => expect(api.patch).toHaveBeenCalledWith('/admin/annees-academiques/4/activate'))
    await waitFor(() => expect(addToast).toHaveBeenCalledWith("2026-2027 est désormais l'année active de votre établissement.", 'success'))
  })

  it("met en garde avant de revenir sur une année terminée", async () => {
    servir([A_VENIR, ACTIVE, PASSEE])
    renderPage(<AcademicYearsPage />)

    fireEvent.click(await screen.findByRole('button', { name: 'Travailler sur 2024-2025' }))

    expect(screen.getByRole('dialog')).toHaveTextContent("2024-2025 est terminée : y revenir ne sert qu'à corriger")
  })
})
