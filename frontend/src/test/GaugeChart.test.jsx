import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import GaugeChart from '../components/charts/GaugeChart'

describe('GaugeChart', () => {
  it('renders percentage value', () => {
    render(<GaugeChart value={75} />)
    expect(screen.getByText('75%')).toBeInTheDocument()
  })

  it('renders label when provided', () => {
    render(<GaugeChart value={50} label="Présence" />)
    expect(screen.getByText('Présence')).toBeInTheDocument()
  })

  it('renders 0% for zero value', () => {
    render(<GaugeChart value={0} />)
    expect(screen.getByText('0%')).toBeInTheDocument()
  })

  it('renders 100% for max value', () => {
    render(<GaugeChart value={100} />)
    expect(screen.getByText('100%')).toBeInTheDocument()
  })

  it('renders SVG element', () => {
    const { container } = render(<GaugeChart value={50} />)
    expect(container.querySelector('svg')).toBeInTheDocument()
  })
})
