import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import EmptyState from '../components/ui/EmptyState'

describe('EmptyState', () => {
  it('renders default message', () => {
    render(<EmptyState />)
    expect(screen.getByText('Aucune donnée')).toBeInTheDocument()
  })

  it('renders custom message', () => {
    render(<EmptyState message="Rien à afficher" />)
    expect(screen.getByText('Rien à afficher')).toBeInTheDocument()
  })

  it('renders action element when provided', () => {
    render(<EmptyState action={<button>Ajouter</button>} />)
    expect(screen.getByText('Ajouter')).toBeInTheDocument()
  })
})
