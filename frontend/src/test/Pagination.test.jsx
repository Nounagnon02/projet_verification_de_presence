import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import Pagination from '../components/ui/Pagination'

describe('Pagination', () => {
  const pagination = {
    current_page: 3,
    last_page: 10,
    from: 21,
    to: 30,
    total: 100,
  }

  it('renders nothing when last_page <= 1', () => {
    const { container } = render(
      <Pagination pagination={{ ...pagination, last_page: 1, current_page: 1 }} onPageChange={() => {}} />
    )
    expect(container.innerHTML).toBe('')
  })

  it('renders page info text', () => {
    render(<Pagination pagination={pagination} onPageChange={() => {}} />)
    expect(screen.getByText(/21.*30.*sur.*100/)).toBeInTheDocument()
  })

  it('shows current page as active', () => {
    render(<Pagination pagination={pagination} onPageChange={() => {}} />)
    const page3 = screen.getByText('3')
    expect(page3.className).toContain('bg-primary')
  })

  it('calls onPageChange with next page on next button click', async () => {
    const user = userEvent.setup()
    let page = 0
    render(<Pagination pagination={pagination} onPageChange={(p) => { page = p }} />)
    const nextBtn = screen.getByText('4').parentElement // The "4" button is next to current

    // Actually, let's click the "Suivant" icon button
    const buttons = screen.getAllByRole('button')
    const nextIconBtn = buttons[buttons.length - 1] // Last button should be next
    await user.click(nextIconBtn)
    expect(page).toBe(4)
  })

  it('calls onPageChange with previous page on prev button click', async () => {
    const user = userEvent.setup()
    let page = 0
    render(<Pagination pagination={pagination} onPageChange={(p) => { page = p }} />)
    const buttons = screen.getAllByRole('button')
    const prevIconBtn = buttons[0] // First button should be prev
    await user.click(prevIconBtn)
    expect(page).toBe(2)
  })

  it('disables prev button on first page', () => {
    render(
      <Pagination pagination={{ ...pagination, current_page: 1 }} onPageChange={() => {}} />
    )
    const buttons = screen.getAllByRole('button')
    expect(buttons[0]).toBeDisabled()
  })

  it('disables next button on last page', () => {
    render(
      <Pagination pagination={{ ...pagination, current_page: 10 }} onPageChange={() => {}} />
    )
    const buttons = screen.getAllByRole('button')
    expect(buttons[buttons.length - 1]).toBeDisabled()
  })
})
