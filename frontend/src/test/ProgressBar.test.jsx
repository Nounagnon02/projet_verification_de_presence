import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import ProgressBar from '../components/charts/ProgressBar'

describe('ProgressBar', () => {
  it('renders percentage text', () => {
    render(<ProgressBar value={75} />)
    expect(screen.getByText('75%')).toBeInTheDocument()
  })

  it('renders label when provided', () => {
    render(<ProgressBar value={50} label="Progression" />)
    expect(screen.getByText('Progression')).toBeInTheDocument()
  })

  it('hides value when showValue is false', () => {
    render(<ProgressBar value={50} showValue={false} />)
    expect(screen.queryByText('50%')).not.toBeInTheDocument()
  })

  it('caps at 100%', () => {
    render(<ProgressBar value={200} />)
    expect(screen.getByText('100%')).toBeInTheDocument()
  })

  it('renders with custom color', () => {
    const { container } = render(<ProgressBar value={50} color="success" />)
    const bar = container.querySelector('.bg-\\[\\#2E7D32\\]')
    expect(bar).toBeInTheDocument()
  })
})
