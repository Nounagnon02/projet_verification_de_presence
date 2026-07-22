import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Drawer from '../components/ui/Drawer'

describe('Drawer', () => {
  it('does not render when isOpen is false', () => {
    const { container } = render(<Drawer isOpen={false} onClose={() => {}} title="Test">Contenu</Drawer>)
    expect(container.innerHTML).toBe('')
  })

  it('renders when isOpen is true', () => {
    render(<Drawer isOpen={true} onClose={() => {}} title="Mon Drawer">Contenu</Drawer>)
    expect(screen.getByText('Contenu')).toBeInTheDocument()
    expect(screen.getByText('Mon Drawer')).toBeInTheDocument()
  })

  it('calls onClose when clicking overlay', async () => {
    const user = userEvent.setup()
    const onClose = vi.fn()
    render(<Drawer isOpen={true} onClose={onClose} title="Test">Contenu</Drawer>)
    const overlay = document.querySelector('.fixed.inset-0 > .absolute.inset-0')
    if (overlay) await user.click(overlay)
    expect(onClose).toHaveBeenCalled()
  })
})
