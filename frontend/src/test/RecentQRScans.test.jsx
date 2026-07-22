import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import RecentQRScans from '../components/RecentQRScans'

describe('RecentQRScans', () => {
  it('shows empty message when no scans', () => {
    render(<RecentQRScans scans={[]} />)
    expect(screen.getByText(/Aucun scan récent/)).toBeInTheDocument()
  })

  it('renders scan items', () => {
    const scans = [
      { name: 'Alice Dupont', course: 'Maths', status: 'SUCCÈS', time: '08:30', image: '' },
      { name: 'Bob Martin', course: 'Physique', status: 'ÉCHEC', time: '09:15', image: '' },
    ]
    render(<RecentQRScans scans={scans} />)
    expect(screen.getByText('Alice Dupont')).toBeInTheDocument()
    expect(screen.getByText('Bob Martin')).toBeInTheDocument()
    expect(screen.getByText('Maths')).toBeInTheDocument()
    expect(screen.getByText('SUCCÈS')).toBeInTheDocument()
  })
})
