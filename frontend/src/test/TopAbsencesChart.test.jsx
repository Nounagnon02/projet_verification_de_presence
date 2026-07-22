import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import TopAbsencesChart from '../components/charts/TopAbsencesChart'

describe('TopAbsencesChart', () => {
  it('shows empty state when no data', () => {
    render(<TopAbsencesChart data={[]} />)
    expect(screen.getByText('Aucune donnée disponible')).toBeInTheDocument()
  })

  it('shows empty state when data is null', () => {
    render(<TopAbsencesChart />)
    expect(screen.getByText('Aucune donnée disponible')).toBeInTheDocument()
  })

  it('renders absence items', () => {
    const data = [
      { course: 'Maths', percentage: 45, color: 'error' },
      { course: 'Physique', percentage: 30, color: 'tertiary-container' },
    ]
    render(<TopAbsencesChart data={data} />)
    expect(screen.getByText('Maths')).toBeInTheDocument()
    expect(screen.getByText('Physique')).toBeInTheDocument()
    expect(screen.getByText(/45%/)).toBeInTheDocument()
    expect(screen.getByText(/30%/)).toBeInTheDocument()
  })
})
