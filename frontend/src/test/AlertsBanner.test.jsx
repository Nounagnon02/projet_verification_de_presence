import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import AlertsBanner from '../components/ui/AlertsBanner'

describe('AlertsBanner', () => {
  const alerts = [{ title: 'Alerte test', message: 'Message test' }]

  it('renders alert title and message', () => {
    render(<AlertsBanner alerts={alerts} />)
    expect(screen.getByText('Alerte test')).toBeInTheDocument()
    expect(screen.getByText('Message test')).toBeInTheDocument()
  })

  it('renders "Voir la liste" button', () => {
    render(<AlertsBanner alerts={alerts} />)
    expect(screen.getByText('Voir la liste')).toBeInTheDocument()
  })
})
