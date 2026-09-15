import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { screen, within, fireEvent, waitFor } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import { invalidateApiCache } from '../api/cache'

const EN_COURS = {
  id: 3, libelle: '2025-2026', active: true, statut: 'en_cours', date_debut: '2025-10-01', date_fin: '2026-09-30',
  etudiants_count: 83, ues_count: 80, emplois_du_temps_count: 4, evenements_count: 132, seances_a_venir_count: 5,
  etablissements: [{ code: 'FAST', nom: 'Faculté des sciences', suit_universite: true }],
}
const CHOISIE = {
  id: 2, libelle: '2024-2025', active: false, statut: 'terminee', date_debut: '2024-10-01', date_fin: '2025-09-30',
  etudiants_count: 0, ues_count: 0, emplois_du_temps_count: 0, evenements_count: 0,
  etablissements: [{ code: 'IFRI', nom: 'Institut de formation', suit_universite: false }],
}
const VIDE = {
  id: 1, libelle: '2023-2024', active: false, statut: 'terminee', date_debut: '2023-10-01', date_fin: '2024-09-30',
  etudiants_count: 0, ues_count: 0, emplois_du_temps_count: 0, evenements_count: 0, etablissements: [],
}
const PLEINE = { ...VIDE, id: 5, libelle: '2022-2023', date_debut: '2022-10-01', date_fin: '2023-09-30', ues_count: 12 }

const { api, addToast } = vi.hoisted(() => {
  const vide = () => Promise.resolve({ data: { success: true, data: [] } })
  return {
    api: { get: vi.fn(vide), post: vi.fn(vide), put: vi.fn(vide), patch: vi.fn(vide), delete: vi.fn(vide) },
    addToast: vi.fn(),
  }
})

vi.mock('../api/axios', () => ({ default: api }))
vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast }) }))

import AnneesUniversitairesPage from '../pages/super-admin/AnneesUniversitairesPage'

function servir(annees) {
  api.get.mockImplementation(() => Promise.resolve({ data: { success: true, data: annees } }))
}

describe('Années académiques — super administrateur', () => {
  beforeEach(() => {
    invalidateApiCache()
    vi.clearAllMocks()
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 8, 14, 10, 0))
  })

  afterEach(() => vi.useRealTimers())

  it('dit pourquoi une année ne peut pas être supprimée', async () => {
    servir([EN_COURS, CHOISIE, VIDE, PLEINE])
    renderPage(<AnneesUniversitairesPage />)

    const enCours = await screen.findByRole('button', { name: 'Supprimer 2025-2026' })
    expect(enCours).toBeDisabled()
    expect(enCours).toHaveAttribute('title', expect.stringContaining("l'année en cours de l'université"))

    const choisie = screen.getByRole('button', { name: 'Supprimer 2024-2025' })
    expect(choisie).toBeDisabled()
    expect(choisie).toHaveAttribute('title', expect.stringContaining('Année active de : IFRI'))

    expect(screen.getByRole('button', { name: 'Supprimer 2022-2023' })).toHaveAttribute('title', expect.stringContaining('Elle contient 12 UE'))
    expect(screen.getByRole('button', { name: 'Supprimer 2023-2024' })).toBeEnabled()
  })

  it("prévient que l'année en cours s'achève sans successeur, et propose de créer la suivante", async () => {
    servir([EN_COURS, CHOISIE])
    renderPage(<AnneesUniversitairesPage />)

    const alerte = await screen.findByRole('alert')
    expect(alerte).toHaveTextContent('2025-2026 se termine le 30 septembre 2026, dans 16 jours')

    fireEvent.click(within(alerte).getByRole('button', { name: 'Créer 2026-2027' }))

    // Le formulaire s'ouvre sur l'année suivante, dates décalées d'un an.
    expect(screen.getByLabelText('Libellé')).toHaveValue('2026-2027')
    expect(screen.getByLabelText('Début')).toHaveValue('2026-10-01')
    expect(screen.getByLabelText('Fin')).toHaveValue('2027-09-30')
  })

  it("affiche sous chaque champ l'erreur que renvoie le serveur", async () => {
    servir([EN_COURS])
    api.post.mockRejectedValue({ response: { status: 422, data: { errors: { date_debut: ['Ces dates chevauchent 2025-2026 (du 01/10/2025 au 30/09/2026).'] } } } })
    renderPage(<AnneesUniversitairesPage />)

    fireEvent.click(await screen.findByRole('button', { name: /Nouvelle année/ }))
    fireEvent.change(screen.getByLabelText('Début'), { target: { value: '2026-09-15' } })
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Créer 2026-2027' }))

    expect(await screen.findByText('Ces dates chevauchent 2025-2026 (du 01/10/2025 au 30/09/2026).')).toBeInTheDocument()
    expect(screen.getByLabelText('Début')).toHaveAttribute('aria-invalid', 'true')
    expect(api.post).toHaveBeenCalledWith('/super-admin/annees-academiques', {
      libelle: '2026-2027', date_debut: '2026-09-15', date_fin: '2027-09-30',
    })
  })

  it("annonce qui suit l'année en cours avant d'en désigner une autre", async () => {
    const suivante = { ...VIDE, id: 7, libelle: '2026-2027', statut: 'a_venir', date_debut: '2026-10-01', date_fin: '2027-09-30' }
    servir([suivante, EN_COURS, CHOISIE])
    api.patch.mockResolvedValue({ data: { success: true, message: "2026-2027 est l'année en cours de l'université." } })
    renderPage(<AnneesUniversitairesPage />)

    const ligne = (await screen.findByText('2026-2027')).closest('tr')
    fireEvent.click(within(ligne).getByRole('button', { name: /Définir en cours/ }))

    const fenetre = screen.getByRole('dialog', { name: "Faire de 2026-2027 l'année en cours ?" })
    expect(fenetre).toHaveTextContent("Passent sur 2026-2027, parce qu'ils suivent l'année de l'université : FAST")
    expect(fenetre).toHaveTextContent("Gardent l'année qu'ils ont choisie : IFRI (2024-2025)")
    expect(fenetre).toHaveTextContent("5 séances déjà planifiées de 2025-2026 après aujourd'hui seront retirées")
    expect(fenetre).toHaveTextContent("2026-2027 n'a encore aucun emploi du temps")

    fireEvent.click(within(fenetre).getByRole('button', { name: 'Définir 2026-2027 en cours' }))
    await waitFor(() => expect(api.patch).toHaveBeenCalledWith('/super-admin/annees-academiques/7/en-cours'))
  })
})
