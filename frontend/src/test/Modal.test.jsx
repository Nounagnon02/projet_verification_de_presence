import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Modal from '../components/ui/Modal'

describe('Modal', () => {
  it('does not render when isOpen is false', () => {
    const { container } = render(<Modal isOpen={false} onClose={() => {}}>Contenu</Modal>)
    expect(container.innerHTML).toBe('')
  })

  it('renders when isOpen is true', () => {
    render(<Modal isOpen={true} onClose={() => {}}>Contenu</Modal>)
    expect(screen.getByText('Contenu')).toBeInTheDocument()
  })

  it('renders title when provided', () => {
    render(<Modal isOpen={true} onClose={() => {}} title="Mon Titre">Contenu</Modal>)
    expect(screen.getByText('Mon Titre')).toBeInTheDocument()
  })

  it('has role dialog and aria-modal', () => {
    render(<Modal isOpen={true} onClose={() => {}}>Contenu</Modal>)
    const dialog = screen.getByRole('dialog', { hidden: true })
    expect(dialog).toHaveAttribute('aria-modal', 'true')
  })

  it('calls onClose when clicking close button', async () => {
    const user = userEvent.setup()
    let closed = false
    render(<Modal isOpen={true} onClose={() => { closed = true }}>Contenu</Modal>)
    const closeBtn = screen.getByLabelText('Fermer la fenêtre modale')
    await user.click(closeBtn)
    expect(closed).toBe(true)
  })

  it('calls onClose on Escape key', async () => {
    const user = userEvent.setup()
    let closed = false
    render(<Modal isOpen={true} onClose={() => { closed = true }}>Contenu</Modal>)
    await user.keyboard('{Escape}')
    expect(closed).toBe(true)
  })
})
