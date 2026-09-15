import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { screen, fireEvent, waitFor, within } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

// vi.mock est remonté en tête de module : les doublures sont construites dans vi.hoisted.
const { api, enregistrer } = vi.hoisted(() => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  enregistrer: vi.fn(),
}))

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'token' }))
vi.mock('../utils/telechargement', async (importOriginal) => ({ ...(await importOriginal()), enregistrer }))

import ReportsPage from '../pages/reports/ReportsPage'

const RAPPORT = {
  entite: 'IFRI — Institut de Formation et de Recherche en Informatique',
  taux_global: 16.4,
  total_evenements: 104,
  presences_attendues: 781,
  presences_valides: 128,
  absences: 653,
  presences_suspectes: 5,
  presences_rejetees: 17,
  total_presences: 150,
  total_etudiants: 83,
  evolution: [
    { semaine: '2026-08-24', seances: 3, attendus: 30, presents: 12, taux: 40 },
    { semaine: '2026-08-31', seances: 0, attendus: 0, presents: 0, taux: null },
  ],
  stats_par_ue: [
    { ue_id: 1, code: 'UE-HAUT', intitule: 'Algorithmique', semestre: 1, total_evenements: 4, presences_attendues: 40, total_presences: 36, total_etudiants: 10, taux: 90 },
    { ue_id: 2, code: 'UE-BAS', intitule: 'Probabilités', semestre: 1, total_evenements: 4, presences_attendues: 40, total_presences: 8, total_etudiants: 10, taux: 20 },
  ],
  filtres_appliques: {},
}

const absent = (i, absences) => ({
  etudiant_id: `uuid-${i}`, nom: `NOM${i}`, prenom: `Prenom${i}`, matricule: `24-000${i}`, filiere_code: 'IM-L1',
  attendus: 4, presents: 4 - absences, absences, taux: ((4 - absences) / 4) * 100,
  dernier_manque: { evenement_id: i, date: '2026-08-25', ec: 'Algorithmique' },
})

const ABSENTS = { etudiants_attendus: 14, etudiants: [absent(1, 3), ...Array.from({ length: 6 }, (_, i) => absent(i + 2, 1))] }

const FILIERES = [
  { id: 7, code: 'IM-L1', intitule: 'Informatique', niveau: 'L1', semestres: [1, 2] },
  { id: 8, code: 'GEA-L1', intitule: 'Gestion', niveau: 'L1', semestres: [1, 2] },
]

const CLASSEMENT = [
  { id: 7, code: 'IM-L1', intitule: 'Informatique', taux: 17.3, presences_attendues: 52, total_presences: 9 },
  { id: 8, code: 'GEA-L1', intitule: 'Gestion', taux: 9.8, presences_attendues: 102, total_presences: 10 },
]

const ANNEES = [
  { id: 3, libelle: '2025-2026', active: true, taux: 16.4, presences_attendues: 750, total_presences: 123, total_evenements: 102 },
  { id: 2, libelle: '2024-2025', active: false, taux: null, presences_attendues: 0, total_presences: 0, total_evenements: 0 },
]

const SEMESTRES = {
  filiere: { id: 7, code: 'IM-L1' },
  semestres: [
    { semestre: 1, label: 'S1', taux: 20, presences_attendues: 30, total_presences: 6, total_evenements: 3 },
    { semestre: 2, label: 'S2', taux: 0, presences_attendues: 0, total_presences: 0, total_evenements: 0 },
  ],
}

const ok = (data) => Promise.resolve({ data: { success: true, data } })
const appels = (cible) => api.get.mock.calls.filter(([url]) => url === cible)

const ouvrirExports = () => fireEvent.click(screen.getByRole('button', { name: /Exporter/ }))

describe('Rapports de présence', () => {
  beforeEach(() => {
    // Lundi 14 septembre 2026 : seule la date est simulée.
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 8, 14, 10, 0))
    api.get.mockReset()
    enregistrer.mockReset()
    api.get.mockImplementation((url, config) => {
      if (config?.responseType === 'blob') return Promise.resolve({ data: 'fichier', headers: {} })
      if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true }])
      if (url === '/admin/filieres') return ok(FILIERES)
      if (url === '/admin/reports/filtered') return ok(RAPPORT)
      if (url === '/admin/reports/etudiants-absents') return ok(ABSENTS)
      if (url === '/admin/reports/filiere-stats') return ok(CLASSEMENT)
      if (url === '/admin/reports/annee-stats') return ok(ANNEES)
      if (url === '/admin/reports/semester-comparison') return ok(SEMESTRES)
      return ok([])
    })
  })

  afterEach(() => vi.useRealTimers())

  it('affiche le taux du tableau de bord, et des cartes qui ne mélangent pas les statuts', async () => {
    renderPage(<ReportsPage />)

    expect(await screen.findByText('16.4%')).toBeInTheDocument()
    const chiffres = screen.getByRole('region', { name: 'Chiffres clés' })
    expect(within(chiffres).getByText('128 présents sur 781 attendus')).toBeInTheDocument()
    for (const [libelle, valeur] of [['Séances terminées', '104'], ['Absences', '653'], ['Suspects à arbitrer', '5'], ['Rejetés', '17']]) {
      expect(within(chiffres).getByText(libelle).closest('div')).toHaveTextContent(valeur)
    }
    // Les suspects se traitent dans la file d'attente : la carte y mène.
    expect(within(chiffres).getByRole('link', { name: /File d'attente/ })).toHaveAttribute('href', '/attendance/queue')
  })

  it("dit ce que l'on regarde : entité, année, période et filière", async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    expect(screen.getByText('IFRI')).toBeInTheDocument()
    expect(screen.getByText('Année 2025-2026')).toBeInTheDocument()
    expect(screen.getByText('du 16/08/2026 au 14/09/2026')).toBeInTheDocument()
    expect(screen.getByText('toutes filières')).toBeInTheDocument()
  })

  it('applique une seule période, les 30 derniers jours par défaut, sans champ « Jours »', async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    expect(screen.queryByText('Jours')).toBeNull()
    const params = appels('/admin/reports/filtered').at(-1)[1].params
    expect(params).toMatchObject({ annee_id: '3', date_debut: '2026-08-16', date_fin: '2026-09-14' })
    expect(params.jours).toBeUndefined()
  })

  it('ne répète plus le taux dans une jauge', async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    expect(screen.queryByText('Taux Global de Présence')).toBeNull()
  })

  it('trace chaque semaine sur 0–100 %, et laisse vide une semaine sans séance', async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    const graphe = screen.getByRole('img', { name: /Taux de présence par semaine/ })
    expect(graphe.getAttribute('aria-label')).toContain('semaine du 24/08, 40 %')
    expect(graphe.getAttribute('aria-label')).toContain('1 semaine(s) sans séance terminée')
    expect(within(graphe).getByText('100 %')).toBeInTheDocument()
  })

  it('classe les UE de la plus faible à la plus forte', async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    const lignes = within(screen.getByRole('region', { name: 'UE les plus faibles' })).getAllByRole('listitem')
    expect(lignes[0]).toHaveTextContent('UE-BAS')
    expect(lignes[1]).toHaveTextContent('UE-HAUT')
  })

  it("liste les étudiants les plus absents, six d'abord puis tous, avec les filtres du rapport", async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    const bloc = screen.getByRole('region', { name: 'Étudiants les plus absents' })
    expect(within(bloc).getAllByRole('row')).toHaveLength(1 + 6)
    expect(within(bloc).getByText('7 étudiants sur les 14 attendus ont au moins une absence.')).toBeInTheDocument()
    expect(within(bloc).getAllByText('Algorithmique · 25/08')).toHaveLength(6)
    expect(within(bloc).getByRole('link', { name: 'Prenom1 NOM1' })).toHaveAttribute('href', '/attendance/student-stats/uuid-1')

    fireEvent.click(within(bloc).getByRole('button', { name: 'Afficher les 7' }))
    expect(within(bloc).getAllByRole('row')).toHaveLength(1 + 7)

    expect(appels('/admin/reports/etudiants-absents').at(-1)[1].params).toEqual(appels('/admin/reports/filtered').at(-1)[1].params)
  })

  it('exporte la liste des présences avec tous les filtres affichés', async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    fireEvent.change(screen.getByLabelText('Semestre'), { target: { value: '2' } })
    ouvrirExports()
    fireEvent.click(screen.getByRole('menuitem', { name: /Liste des présences/ }))

    await waitFor(() => {
      const appel = appels('/admin/reports/excel/export').at(-1)
      expect(appel?.[1].params).toMatchObject({ semestre: '2', annee_id: '3', date_debut: '2026-08-16', date_fin: '2026-09-14' })
    })
    await waitFor(() => expect(enregistrer).toHaveBeenCalled())
  })

  it('exporte la liste entière des absents en CSV, avec les mêmes filtres', async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    ouvrirExports()
    fireEvent.click(screen.getByRole('menuitem', { name: /Étudiants les plus absents/ }))

    await waitFor(() => {
      const appel = appels('/admin/reports/etudiants-absents').find(([, config]) => config?.params?.format === 'csv')
      expect(appel?.[1]).toMatchObject({ responseType: 'blob', params: { annee_id: '3', date_debut: '2026-08-16', format: 'csv' } })
    })
  })

  it("n'offre le rapport de filière qu'une fois une filière choisie", async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    ouvrirExports()
    expect(screen.getByRole('menuitem', { name: /Rapport de filière/ })).toBeDisabled()
    fireEvent.keyDown(document, { key: 'Escape' })
    expect(screen.queryByRole('menu')).toBeNull()

    fireEvent.change(screen.getByLabelText('Filière'), { target: { value: '7' } })
    ouvrirExports()
    expect(screen.getByRole('menuitem', { name: /Rapport de filière/ })).toBeEnabled()
  })

  it("l'onglet Comparaisons suit l'année des filtres et attend une filière pour les semestres", async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    fireEvent.click(screen.getByRole('tab', { name: 'Comparaisons' }))

    const classement = screen.getByRole('region', { name: 'Classement des filières' })
    expect(await within(classement).findByText('GEA-L1')).toBeInTheDocument()
    expect(appels('/admin/reports/filiere-stats').at(-1)[1].params).toEqual({ annee_id: '3' })

    expect(screen.getByText('Choisissez une filière dans les filtres du haut pour comparer ses semestres.')).toBeInTheDocument()
    expect(appels('/admin/reports/semester-comparison')).toHaveLength(0)

    // Une année sans séance terminée n'apparaît pas.
    const annees = screen.getByRole('region', { name: 'Années académiques' })
    expect(await within(annees).findByText('16.4%')).toBeInTheDocument()
    expect(within(annees).queryByText('2024-2025')).toBeNull()

    // La période ne s'applique pas aux comparaisons.
    expect(screen.getByLabelText('Du')).toBeDisabled()
  })

  it('compare les semestres de la filière choisie dans les filtres', async () => {
    renderPage(<ReportsPage />)
    await screen.findByText('16.4%')

    fireEvent.change(screen.getByLabelText('Filière'), { target: { value: '7' } })
    fireEvent.click(screen.getByRole('tab', { name: 'Comparaisons' }))

    const bloc = await screen.findByRole('region', { name: 'Semestres de IM-L1' })
    expect(within(bloc).getByText('20%')).toBeInTheDocument()
    // Un semestre sans séance n'affiche pas un 0 % trompeur.
    expect(within(bloc).getByText('aucune séance')).toBeInTheDocument()
    expect(appels('/admin/reports/semester-comparison').at(-1)[1].params).toEqual({ annee_id: '3', filiere_id: '7' })
  })
})
