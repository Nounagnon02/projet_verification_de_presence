import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, within, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

const { api } = vi.hoisted(() => ({ api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api }))
vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast: vi.fn() }) }))

import StudentManagementPage from '../pages/students/StudentManagementPage'

const ok = (data) => Promise.resolve({ data: { success: true, data } })

const NIVEAUX = ['L1', 'L2', 'L3', 'M1', 'M2'].map((code, i) => ({ code, libelle: code, semestres: [2 * i + 1, 2 * i + 2] }))

const FILIERES = [
  { id: 10, code: 'IM-L1', intitule: 'Informatique (L1)', niveau: 'L1', programme_id: 1, semestres: [] },
  { id: 11, code: 'IM-L2', intitule: 'Informatique (L2)', niveau: 'L2', programme_id: 1, semestres: [] },
  { id: 13, code: 'IM-L3', intitule: 'Informatique (L3)', niveau: 'L3', programme_id: 1, semestres: [] },
  { id: 12, code: 'GEA-M2', intitule: 'Gestion (M2)', niveau: 'M2', programme_id: 2, semestres: [] },
]

async function ouvrirLaPromotion() {
  renderPage(<StudentManagementPage />)
  fireEvent.click(await screen.findByRole('button', { name: /Promouvoir/ }))
  const depart = await screen.findByLabelText('Filière de départ *')
  await waitFor(() => expect(within(depart).getByRole('option', { name: /IM-L1/ })).toBeInTheDocument())
  return depart
}

/**
 * La destination d'une promotion se choisissait sans aide, et rien ne
 * signalait IM-L1 → GEA-M2.
 */
describe('Promotion — filière suivante', () => {
  beforeEach(() => {
    for (const methode of ['get', 'post']) api[methode].mockReset()
    api.get.mockImplementation((url) => {
      if (url === '/admin/students') return Promise.resolve({ data: { success: true, data: [], meta: null } })
      if (url === '/admin/annees-academiques') return ok([{ id: 3, libelle: '2025-2026', active: true }])
      if (url === '/admin/filieres') return ok(FILIERES)
      if (url === '/admin/niveaux') return ok(NIVEAUX)
      return ok([])
    })
    api.post.mockImplementation(() => ok({ etudiants_concernes: 13 }))
  })

  it('propose le niveau suivant du même programme, et signale un parcours inhabituel', async () => {
    const depart = await ouvrirLaPromotion()

    fireEvent.change(depart, { target: { value: '10' } })
    expect(screen.getByLabelText('Filière de destination *')).toHaveValue('11')
    expect(screen.getByText('Proposée : IM-L2, niveau suivant du même programme.')).toBeInTheDocument()
    expect(screen.queryByText(/ce n'est pas le niveau suivant/)).toBeNull()

    fireEvent.change(screen.getByLabelText('Filière de destination *'), { target: { value: '12' } })
    expect(screen.getByText(/IM-L1 → GEA-M2 : ce n'est pas le niveau suivant du même programme/)).toBeInTheDocument()
  })

  it('dit quand le programme n’a pas de niveau suivant', async () => {
    const depart = await ouvrirLaPromotion()

    fireEvent.change(depart, { target: { value: '13' } })
    expect(screen.getByLabelText('Filière de destination *')).toHaveValue('')
    expect(screen.getByText(/Aucune filière de niveau suivant dans ce programme/)).toBeInTheDocument()
  })
})
