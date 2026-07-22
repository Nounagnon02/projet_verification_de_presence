import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import BarChart from '../components/charts/BarChart'

describe('BarChart', () => {
  const data = [
    { label: 'Jan', value: 30 },
    { label: 'Fév', value: 50 },
    { label: 'Mar', value: 20 },
  ]

  it('returns null when data is empty', () => {
    const { container } = render(<BarChart data={[]} bars="value" />)
    expect(container.innerHTML).toBe('')
  })

  it('renders bars for each data point', () => {
    const { container } = render(<BarChart data={data} bars="value" />)
    const bars = container.querySelectorAll('[class*="w-full bg-gradient"]')
    expect(bars.length).toBe(3)
  })

  it('renders axis labels when showAxis is true', () => {
    render(<BarChart data={data} bars="value" showAxis={true} />)
    expect(screen.getByText('Jan')).toBeInTheDocument()
    expect(screen.getByText('Fév')).toBeInTheDocument()
    expect(screen.getByText('Mar')).toBeInTheDocument()
  })

  it('hides axis labels when showAxis is false', () => {
    render(<BarChart data={data} bars="value" showAxis={false} />)
    expect(screen.queryByText('Jan')).not.toBeInTheDocument()
  })

  it('sets correct height style', () => {
    render(<BarChart data={data} bars="value" height={300} />)
    const container = document.querySelector('[style*="height: 300px"]')
    expect(container).toBeInTheDocument()
  })
})
