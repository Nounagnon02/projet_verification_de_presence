import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import AttendanceChart from '../components/charts/AttendanceChart'

describe('AttendanceChart', () => {
  it('shows loading state when no data', () => {
    render(<AttendanceChart data={[]} />)
    expect(screen.getByText('Chargement des données...')).toBeInTheDocument()
  })

  it('shows loading state when data is null', () => {
    render(<AttendanceChart />)
    expect(screen.getByText('Chargement des données...')).toBeInTheDocument()
  })

  it('renders bars when data provided', () => {
    const data = [
      { label: 'Lun', value: 80 },
      { label: 'Mar', value: 90 },
    ]
    const { container } = render(<AttendanceChart data={data} />)
    const bars = container.querySelectorAll('[class*="bg-primary/10"]')
    expect(bars.length).toBe(2)
  })
})
