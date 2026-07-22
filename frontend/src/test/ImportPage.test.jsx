import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { BrowserRouter } from 'react-router-dom'

vi.mock('../api/axios', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
  },
}))

import ImportPage from '../pages/import/ImportPage'

describe('ImportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders import page title', () => {
    render(
      <BrowserRouter>
        <ImportPage />
      </BrowserRouter>
    )
    expect(screen.getByText('Importation')).toBeInTheDocument()
  })

  it('renders all tabs', () => {
    render(
      <BrowserRouter>
        <ImportPage />
      </BrowserRouter>
    )
    expect(screen.getByText('Import Étudiants')).toBeInTheDocument()
    expect(screen.getByText('Cours (CSV)')).toBeInTheDocument()
    expect(screen.getByText('EDT (CSV)')).toBeInTheDocument()
    expect(screen.getByText('EDT (IA)')).toBeInTheDocument()
    expect(screen.getByText('Cours (IA)')).toBeInTheDocument()
  })

  it('renders file upload area on students tab', () => {
    render(
      <BrowserRouter>
        <ImportPage />
      </BrowserRouter>
    )
    expect(screen.getByText(/Importez votre fichier étudiants/)).toBeInTheDocument()
  })

  it('shows student tab content by default', () => {
    render(
      <BrowserRouter>
        <ImportPage />
      </BrowserRouter>
    )
    expect(screen.getByText(/Importez votre fichier étudiants/)).toBeInTheDocument()
  })

  it('switches tab content when clicking Cours (CSV)', async () => {
    const user = userEvent.setup()
    render(
      <BrowserRouter>
        <ImportPage />
      </BrowserRouter>
    )
    await user.click(screen.getByText('Cours (CSV)'))
    expect(screen.getByText(/Importez vos cours/)).toBeInTheDocument()
  })
})
