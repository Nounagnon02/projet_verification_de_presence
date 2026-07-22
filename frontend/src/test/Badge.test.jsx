import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import Badge from '../components/ui/Badge'

describe('Badge', () => {
  it('renders children text', () => {
    render(<Badge>Actif</Badge>)
    expect(screen.getByText('Actif')).toBeInTheDocument()
  })

  it('applies neutral variant by default', () => {
    render(<Badge>Default</Badge>)
    const el = screen.getByText('Default')
    expect(el.className).toContain('bg-surface-container-high')
  })

  it('applies success variant classes', () => {
    render(<Badge variant="success">OK</Badge>)
    const el = screen.getByText('OK')
    expect(el.className).toContain('bg-[#E8F5E9]')
  })

  it('applies error variant classes', () => {
    render(<Badge variant="error">Erreur</Badge>)
    const el = screen.getByText('Erreur')
    expect(el.className).toContain('bg-[#FFEBEE]')
  })

  it('applies warning variant classes', () => {
    render(<Badge variant="warning">Attention</Badge>)
    const el = screen.getByText('Attention')
    expect(el.className).toContain('bg-[#FFF8E1]')
  })

  it('applies info variant classes', () => {
    render(<Badge variant="info">Info</Badge>)
    const el = screen.getByText('Info')
    expect(el.className).toContain('bg-[#E3F2FD]')
  })

  it('merges custom className', () => {
    render(<Badge className="extra-class">Test</Badge>)
    expect(screen.getByText('Test').className).toContain('extra-class')
  })
})
