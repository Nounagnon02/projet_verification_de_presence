import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'

// Types tels que le backend les stocke dans anomalies.type.
const ANOMALIES = [
  { type: 'verification_echouee', description: 'GPS hors zone' },
  { type: 'invalid_scan_challenge', description: 'Defi refuse' },
]

vi.mock('../hooks/useApi', () => ({
  default: (url) => {
    if (url === '/admin/dashboard') return { data: { total_etudiants: 1, taux_presence_global: 0 }, loading: false }
    if (url === '/admin/alerts') return { data: ANOMALIES }
    return { data: [], loading: false }
  },
}))

vi.mock('../components/charts/BarChart', () => ({
  default: () => <div>BarChart</div>,
}))

import DashboardPage from '../pages/dashboard/DashboardPage'

const afficher = () => render(<BrowserRouter><DashboardPage /></BrowserRouter>)

describe('Anomalies du tableau de bord', () => {
  // Regression : le titre de l'alerte reprenait la valeur brute de la colonne
  // « type ». L'administrateur lisait « verification_echouee », et
  // « invalid_scan_challenge » — en anglais, dans une application francaise.
  it('ne montre aucun identifiant technique', () => {
    afficher()

    expect(screen.queryByText(/verification_echouee/)).not.toBeInTheDocument()
    expect(screen.queryByText(/invalid_scan_challenge/)).not.toBeInTheDocument()
  })

  it('traduit le type en libelle lisible', () => {
    afficher()

    // Le bandeau ne montre que la premiere anomalie, plus un decompte.
    expect(screen.getByText(/Vérification de présence échouée/)).toBeInTheDocument()
  })
})

describe('Anomalie de type inconnu', () => {
  it('nomme la categorie plutot que de laisser passer l identifiant', async () => {
    vi.resetModules()
    vi.doMock('../hooks/useApi', () => ({
      default: (url) => {
        if (url === '/admin/dashboard') return { data: { total_etudiants: 1 }, loading: false }
        if (url === '/admin/alerts') return { data: [{ type: 'type_ajoute_plus_tard', description: 'x' }] }
        return { data: [], loading: false }
      },
    }))
    vi.doMock('../components/charts/BarChart', () => ({ default: () => <div /> }))

    const { default: Page } = await import('../pages/dashboard/DashboardPage')
    render(<BrowserRouter><Page /></BrowserRouter>)

    expect(screen.queryByText(/type_ajoute_plus_tard/)).not.toBeInTheDocument()
    expect(screen.getByText(/Anomalie de présence/)).toBeInTheDocument()
  })
})
