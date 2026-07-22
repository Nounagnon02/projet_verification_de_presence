import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'
import FloatingActionButton from '../components/layout/buttons/FloatingActionButton'

describe('FloatingActionButton', () => {
  it('renders a link to attendance validation', () => {
    render(
      <BrowserRouter>
        <FloatingActionButton />
      </BrowserRouter>
    )
    const link = screen.getByRole('link')
    expect(link).toHaveAttribute('href', '/attendance/validate')
  })
})
