import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Toaster from '../components/ui/Toast'

describe('Toaster', () => {
  const toasts = [
    { id: 1, message: 'Succès !', type: 'success' },
    { id: 2, message: 'Erreur !', type: 'error' },
  ]

  it('renders nothing when toasts is empty', () => {
    const { container } = render(<Toaster toasts={[]} onRemove={() => {}} />)
    expect(container.innerHTML).toBe('')
  })

  it('renders nothing when toasts is null', () => {
    const { container } = render(<Toaster onRemove={() => {}} />)
    expect(container.innerHTML).toBe('')
  })

  it('renders all toasts', () => {
    render(<Toaster toasts={toasts} onRemove={() => {}} />)
    expect(screen.getByText('Succès !')).toBeInTheDocument()
    expect(screen.getByText('Erreur !')).toBeInTheDocument()
  })

  it('calls onRemove when clicking close button', async () => {
    const user = userEvent.setup()
    const onRemove = vi.fn()
    render(<Toaster toasts={toasts} onRemove={onRemove} />)
    const closeBtns = screen.getAllByLabelText(/Fermer la notification/)
    await user.click(closeBtns[0])
    expect(onRemove).toHaveBeenCalledWith(1)
  })

  it('has role alert on each toast', () => {
    render(<Toaster toasts={toasts} onRemove={() => {}} />)
    const alerts = screen.getAllByRole('alert')
    expect(alerts).toHaveLength(2)
  })
})
