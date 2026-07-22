import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'

vi.mock('../hooks/useApi', () => ({
  default: (url) => {
    if (url === '/admin/dashboard') return { data: { total_etudiants: 150, taux_presence_global: 85 }, loading: false }
    if (url === '/admin/dashboard/attendance-trend') return { data: [] }
    if (url === '/admin/dashboard/top-absences') return { data: [] }
    if (url === '/admin/dashboard/today-events') return { data: [] }
    if (url === '/admin/alerts') return { data: [] }
    return { data: null, loading: false }
  },
}))

vi.mock('../components/charts/BarChart', () => ({
  default: ({ data }) => <div>BarChart: {data?.length} bars</div>,
}))

import DashboardPage from '../pages/dashboard/DashboardPage'

describe('DashboardPage', () => {
  it('renders KPI cards with data', async () => {
    render(
      <BrowserRouter>
        <DashboardPage />
      </BrowserRouter>
    )
    expect(screen.getByText('Total Étudiants')).toBeInTheDocument()
    expect(screen.getByText('150')).toBeInTheDocument()
  })

  it('renders chart section titles', () => {
    render(
      <BrowserRouter>
        <DashboardPage />
      </BrowserRouter>
    )
    expect(screen.getByText(/Taux de Présence/)).toBeInTheDocument()
  })

  it('shows Top Absences section', () => {
    render(
      <BrowserRouter>
        <DashboardPage />
      </BrowserRouter>
    )
    expect(screen.getByText(/Top Absences/)).toBeInTheDocument()
  })
})
