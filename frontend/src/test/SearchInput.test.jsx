import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import SearchInput from '../components/ui/SearchInput'

describe('SearchInput', () => {
  it('renders with default placeholder', () => {
    render(<SearchInput value="" onChange={() => {}} />)
    expect(screen.getByRole('searchbox')).toHaveAttribute('placeholder', 'Rechercher...')
  })

  it('renders with custom placeholder', () => {
    render(<SearchInput value="" onChange={() => {}} placeholder="Chercher..." />)
    expect(screen.getByRole('searchbox')).toHaveAttribute('placeholder', 'Chercher...')
  })

  it('displays the provided value', () => {
    render(<SearchInput value="test" onChange={() => {}} />)
    expect(screen.getByRole('searchbox')).toHaveValue('test')
  })

  it('calls onChange when typing', async () => {
    const user = userEvent.setup()
    let val = ''
    render(<SearchInput value="" onChange={(v) => { val = v }} />)
    await user.type(screen.getByRole('searchbox'), 'a')
    expect(val).toBe('a')
  })

  it('sets aria-label from placeholder', () => {
    render(<SearchInput value="" onChange={() => {}} placeholder="Chercher..." />)
    expect(screen.getByLabelText('Chercher...')).toBeInTheDocument()
  })

  it('sets aria-label from prop', () => {
    render(<SearchInput value="" onChange={() => {}} aria-label="Recherche personnalisée" />)
    expect(screen.getByLabelText('Recherche personnalisée')).toBeInTheDocument()
  })
})
