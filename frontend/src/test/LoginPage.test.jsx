import { describe, it, expect, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { BrowserRouter } from 'react-router-dom'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({
    login: vi.fn(),
    user: null,
  }),
}))

import LoginPage from '../pages/auth/LoginPage'

describe('LoginPage', () => {
  it('renders email and password fields', () => {
    render(
      <BrowserRouter>
        <LoginPage />
      </BrowserRouter>
    )
    expect(screen.getByLabelText('Email académique')).toBeInTheDocument()
    expect(screen.getByLabelText('Mot de passe')).toBeInTheDocument()
  })

  it('renders submit button', () => {
    render(
      <BrowserRouter>
        <LoginPage />
      </BrowserRouter>
    )
    expect(screen.getByRole('button', { name: /Se connecter/ })).toBeInTheDocument()
  })

  it('shows error on empty fields', async () => {
    const user = userEvent.setup()
    render(
      <BrowserRouter>
        <LoginPage />
      </BrowserRouter>
    )
    await user.click(screen.getByRole('button', { name: /Se connecter/ }))
    expect(screen.getByText('Veuillez remplir tous les champs')).toBeInTheDocument()
  })

  it('shows error on invalid email', async () => {
    const user = userEvent.setup()
    render(
      <BrowserRouter>
        <LoginPage />
      </BrowserRouter>
    )
    await user.type(screen.getByLabelText('Email académique'), 'test@test')
    await user.type(screen.getByLabelText('Mot de passe'), 'password123')
    await user.click(screen.getByRole('button', { name: /Se connecter/ }))
    expect(await screen.findByText('Veuillez saisir une adresse email valide')).toBeInTheDocument()
  })

  it('renders forgot password link', () => {
    render(
      <BrowserRouter>
        <LoginPage />
      </BrowserRouter>
    )
    expect(screen.getByText('Mot de passe oublié?')).toBeInTheDocument()
  })
})
