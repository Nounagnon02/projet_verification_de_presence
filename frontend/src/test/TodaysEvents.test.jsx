import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import TodaysEvents from '../components/TodaysEvents'

describe('TodaysEvents', () => {
  it('shows empty message when no events', () => {
    render(<TodaysEvents events={[]} />)
    expect(screen.getByText(/Aucun événement prévu/)).toBeInTheDocument()
  })

  it('renders list of events', () => {
    const events = [
      { time: '08:00 - 10:00', title: 'Cours de Maths', location: 'Salle A', status: 'À venir' },
      { time: '10:00 - 12:00', title: 'TD Physique', location: 'Salle B', status: 'En cours' },
    ]
    render(<TodaysEvents events={events} />)
    expect(screen.getByText('Cours de Maths')).toBeInTheDocument()
    expect(screen.getByText('TD Physique')).toBeInTheDocument()
  })
})
