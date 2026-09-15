import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, within, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))
vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast: vi.fn() }) }))

import FilieresPage from '../pages/settings/FilieresPage'

const ok = (data) => Promise.resolve({ data: { success: true, data } })

const NIVEAUX = [
  { code: 'L1', libelle: 'Licence 1', semestres: [1, 2] },
  { code: 'L2', libelle: 'Licence 2', semestres: [3, 4] },
  { code: 'L3', libelle: 'Licence 3', semestres: [5, 6] },
  { code: 'M1', libelle: 'Master 1', semestres: [7, 8] },
  { code: 'M2', libelle: 'Master 2', semestres: [9, 10] },
]

const PROGRAMMES = [
  { id: 1, code: 'IM', intitule: 'Informatique et Mathématiques', filieres_count: 3 },
  { id: 2, code: 'MIAGE', intitule: 'Mathématiques, Informatique et Gestion', filieres_count: 1 },
]

const filiere = (id, code, niveau, programme, totaux = {}) => ({
  id, code, niveau, programme_id: programme, intitule: `${code} — intitulé`,
  etudiants_total: 0, ues_total: 0, evenements_total: 0, ues_par_semestre: {}, ...totaux,
})

// Toutes années : la structure de la grille, et ce qui bloque une suppression.
const TOUTES = [
  filiere(10, 'IM-L1', 'L1', 1, { etudiants_total: 13, ues_total: 6, ues_par_semestre: { 1: 3, 2: 3 } }),
  filiere(11, 'IM-L2', 'L2', 1, { etudiants_total: 9, ues_total: 22, ues_par_semestre: { 3: 11, 4: 11 } }),
  filiere(12, 'IM-L3', 'L3', 1),
  filiere(20, 'MIAGE-M1', 'M1', 2, { etudiants_total: 10, ues_total: 6, ues_par_semestre: { 7: 3, 8: 3 } }),
]

// L'année choisie : ses effectifs. IM-L3 n'y a aucune activité.
const DE_L_ANNEE = [
  { ...TOUTES[0], etudiants_count: 13, ues_count: 6, ues_par_semestre: { 1: 3, 2: 3 } },
  { ...TOUTES[1], etudiants_count: 9, ues_count: 22, ues_par_semestre: { 3: 11, 4: 10, 5: 1 } },
  { ...TOUTES[3], etudiants_count: 10, ues_count: 6, ues_par_semestre: { 7: 3, 8: 3 } },
]

const ligneDu = (intituleProgramme) => screen.getByText(intituleProgramme).closest('tr')

describe('Filières — grille programme × niveau', () => {
  beforeEach(() => {
    for (const methode of ['get', 'post', 'put', 'delete']) api[methode].mockReset()
    api.get.mockImplementation((url, config) => {
      if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true }, { id: 2, libelle: '2024-2025' }])
      if (url === '/admin/niveaux') return ok(NIVEAUX)
      if (url === '/admin/programmes') return ok(PROGRAMMES)
      if (url === '/admin/filieres') return ok(config?.params?.annee_id ? DE_L_ANNEE : TOUTES)
      return ok([])
    })
    api.post.mockImplementation(() => ok({}))
    api.put.mockImplementation(() => ok({}))
    api.delete.mockImplementation(() => ok(null))
  })

  it('range chaque filière dans son programme et son niveau, avec les effectifs de l’année', async () => {
    renderPage(<FilieresPage />)
    await screen.findByRole('table', { name: 'Filières par programme et par niveau' })

    const im = ligneDu('Informatique et Mathématiques')
    expect(within(im).getByText('IM-L1')).toBeInTheDocument()
    expect(within(im).getByText('13 étudiants · 6 UE')).toBeInTheDocument()
    expect(within(im).getByText('S1 : 3 · S2 : 3')).toBeInTheDocument()
    // Une UE de S5 dans une filière de L2 : la maquette est incohérente.
    expect(within(im).getByText('UE hors niveau : S5')).toBeInTheDocument()
    expect(within(im).getByText('Aucune activité cette année')).toBeInTheDocument()
    expect(within(im).getByRole('button', { name: 'Ouvrir le niveau M1 du programme IM' })).toBeInTheDocument()

    const miage = ligneDu('Mathématiques, Informatique et Gestion')
    expect(within(miage).getByText('MIAGE-M1')).toBeInTheDocument()
    expect(within(miage).getAllByRole('button', { name: /^Ouvrir le niveau/ })).toHaveLength(4)

    expect(api.get).toHaveBeenCalledWith('/admin/filieres', { params: { annee_id: '3' } })
  })

  it('ouvre un niveau manquant avec le code et l’intitulé proposés', async () => {
    renderPage(<FilieresPage />)
    fireEvent.click(await screen.findByRole('button', { name: 'Ouvrir le niveau L3 du programme MIAGE' }))

    expect(screen.getByLabelText('Programme')).toHaveValue('2')
    expect(screen.getByLabelText('Niveau')).toHaveValue('L3')
    expect(screen.getByLabelText('Code')).toHaveValue('MIAGE-L3')
    expect(screen.getByLabelText('Intitulé')).toHaveValue('Mathématiques, Informatique et Gestion (L3)')
    expect(screen.getByText("Elle sera rattachée à l'année active (2025-2026).")).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/admin/filieres', {
      niveau: 'L3', code: 'MIAGE-L3', intitule: 'Mathématiques, Informatique et Gestion (L3)', programme_id: 2,
    }))
  })

  it('crée une filière dans un nouveau programme', async () => {
    renderPage(<FilieresPage />)
    fireEvent.click(await screen.findByRole('button', { name: /Nouvelle filière/ }))

    fireEvent.change(screen.getByLabelText('Programme'), { target: { value: 'nouveau' } })
    fireEvent.change(screen.getByLabelText('Code du programme'), { target: { value: 'rit' } })
    fireEvent.change(screen.getByLabelText('Intitulé du programme'), { target: { value: 'Réseaux et Informatique Télécom' } })
    fireEvent.change(screen.getByLabelText('Niveau'), { target: { value: 'L3' } })

    expect(screen.getByLabelText('Code')).toHaveValue('RIT-L3')

    fireEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/admin/filieres', {
      niveau: 'L3', code: 'RIT-L3', intitule: 'Réseaux et Informatique Télécom (L3)',
      programme_code: 'RIT', programme_intitule: 'Réseaux et Informatique Télécom',
    }))
  })

  it('verrouille le niveau d’une filière qui a déjà des UE', async () => {
    renderPage(<FilieresPage />)
    fireEvent.click(await screen.findByRole('button', { name: 'Modifier IM-L1' }))

    expect(screen.getByLabelText('Niveau')).toBeDisabled()
    expect(screen.getByText(/Ses UE sont en S1, S2 : le niveau ne change plus/)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Annuler' }))
    fireEvent.click(screen.getByRole('button', { name: 'Modifier IM-L3' }))
    expect(screen.getByLabelText('Niveau')).toBeEnabled()
  })

  it('explique pourquoi une filière ne peut pas être supprimée, et supprime une filière vide', async () => {
    renderPage(<FilieresPage />)

    const bloquee = await screen.findByRole('button', { name: 'Supprimer IM-L1' })
    expect(bloquee).toBeDisabled()
    expect(bloquee).toHaveAttribute('title', 'Suppression impossible : 13 étudiants, 6 UE')

    fireEvent.click(screen.getByRole('button', { name: 'Supprimer IM-L3' }))
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Supprimer' }))

    await waitFor(() => expect(api.delete).toHaveBeenCalledWith('/admin/filieres/12'))
  })

  it('mène aux UE et aux étudiants de la filière, dans l’année choisie', async () => {
    renderPage(<FilieresPage />)
    await screen.findByRole('table')

    const im = ligneDu('Informatique et Mathématiques')
    expect(within(im).getAllByRole('link', { name: 'UE →' })[0]).toHaveAttribute('href', '/courses?filiere=10&annee=3')
    expect(within(im).getAllByRole('link', { name: 'Étudiants →' })[0]).toHaveAttribute('href', '/students?filiere=10&annee=3')

    fireEvent.change(screen.getByLabelText('Année'), { target: { value: '2' } })
    await waitFor(() => expect(api.get).toHaveBeenCalledWith('/admin/filieres', { params: { annee_id: '2' } }))
  })
})
