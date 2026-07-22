import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Toggle from '../components/ui/Toggle'

describe('Toggle', () => {
  it('renders label when provided', () => {
    render(<Toggle enabled={false} onChange={() => {}} label="Activer" />)
    expect(screen.getByText('Activer')).toBeInTheDocument()
  })

  it('renders description when provided', () => {
    render(<Toggle enabled={false} onChange={() => {}} description="Description" />)
    expect(screen.getByText('Description')).toBeInTheDocument()
  })

  it('shows enabled state', () => {
    const { container } = render(<Toggle enabled={true} onChange={() => {}} />)
    const toggle = container.querySelector('div.relative')
    expect(toggle.className).toContain('bg-primary')
  })

  it('shows disabled state', () => {
    const { container } = render(<Toggle enabled={false} onChange={() => {}} />)
    const toggle = container.querySelector('div.relative')
    expect(toggle.className).toContain('bg-surface-container-high')
  })

  it('calls onChange when clicked', async () => {
    const user = userEvent.setup()
    let toggled = false
    render(<Toggle enabled={false} onChange={() => { toggled = true }} />)
    await user.click(screen.getByRole('checkbox'))
    expect(toggled).toBe(true)
  })
})
