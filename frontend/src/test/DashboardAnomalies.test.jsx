import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'

const { etat, appels } = vi.hoisted(() => ({ etat: { dashboard: {} }, appels: [] }))

vi.mock('../hooks/useApi', () => ({
  default: (url) => {
    appels.push(url)
    if (url === '/admin/dashboard') return { data: etat.dashboard, loading: false }
    return { data: [], loading: false }
  },
}))

vi.mock('../components/charts/BarChart', () => ({
  default: () => <div>BarChart</div>,
}))

import DashboardPage from '../pages/dashboard/DashboardPage'

const afficher = (dashboard) => {
  etat.dashboard = dashboard
  return render(<BrowserRouter><DashboardPage /></BrowserRouter>)
}

/**
 * Le bandeau reprenait toutes les anomalies ouvertes, scans refusés compris :
 * ils ne demandent aucune décision et ne se fermaient jamais. Il ne signale
 * plus que les scans suspects, qui attendent une décision dans la file.
 */
describe('Alertes du tableau de bord', () => {
  it('signale les scans suspects et mène à la file d\'attente', () => {
    afficher({ total_etudiants: 1, scans_a_arbitrer: 3, scans_refuses_du_jour: 2 })

    expect(screen.getByText('3 scans suspects à arbitrer')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /voir la liste/i })).toHaveAttribute('href', '/attendance/queue')
  })

  it('n\'affiche aucun bandeau quand rien n\'est à arbitrer', () => {
    afficher({ total_etudiants: 1, scans_a_arbitrer: 0, scans_refuses_du_jour: 4 })

    expect(screen.queryByRole('link', { name: /voir la liste/i })).not.toBeInTheDocument()
  })

  it('donne les refus du jour pour information', () => {
    afficher({ scans_a_arbitrer: 0, scans_refuses_du_jour: 4 })

    expect(screen.getByText('Scans à arbitrer')).toBeInTheDocument()
    expect(screen.getByText(/4 refus aujourd'hui/)).toBeInTheDocument()
  })

  it('ne consulte plus la liste des anomalies', () => {
    appels.length = 0
    afficher({ scans_a_arbitrer: 0 })

    expect(appels).not.toContain('/admin/alerts')
  })
})
