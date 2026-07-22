import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import KPICard from '../components/cards/KPICard'

describe('KPICard', () => {
  it('renders label and value', () => {
    render(<KPICard label="Total étudiants" value="150" />)
    expect(screen.getByText('Total étudiants')).toBeInTheDocument()
    expect(screen.getByText('150')).toBeInTheDocument()
  })

  it('renders change indicator', () => {
    render(<KPICard label="Test" value="10" change="+5%" />)
    expect(screen.getByText('+5%')).toBeInTheDocument()
  })

  it('renders icon when provided', () => {
    render(<KPICard label="Test" value="10" icon={<span>Icon</span>} />)
    expect(screen.getByText('Icon')).toBeInTheDocument()
  })
})
