import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'
import NotFoundPage from '../pages/NotFoundPage'

describe('NotFoundPage', () => {
  it('renders 404 message', () => {
    render(
      <BrowserRouter>
        <NotFoundPage />
      </BrowserRouter>
    )
    expect(screen.getByText(/404/i)).toBeInTheDocument()
  })

  it('renders a link to go home', () => {
    render(
      <BrowserRouter>
        <NotFoundPage />
      </BrowserRouter>
    )
    const link = screen.getByRole('link')
    expect(link).toBeInTheDocument()
  })
})
