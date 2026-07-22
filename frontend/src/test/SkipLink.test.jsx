import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import SkipLink from '../components/ui/SkipLink'

describe('SkipLink', () => {
  it('renders a link with href #main-content', () => {
    render(<SkipLink />)
    const link = screen.getByText('Aller au contenu principal')
    expect(link).toHaveAttribute('href', '#main-content')
  })

  it('has sr-only class by default', () => {
    render(<SkipLink />)
    const link = screen.getByText('Aller au contenu principal')
    expect(link.className).toContain('sr-only')
  })
})
