import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { BrowserRouter } from 'react-router-dom'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({
    login: vi.fn(),
    user: null,
  }),
}))

import LoginPage from '../pages/auth/LoginPage'

describe('Debug', () => {
  it('debug invalid email', async () => {
    const user = userEvent.setup()
    render(
      <BrowserRouter>
        <LoginPage />
      </BrowserRouter>
    )
    
    // Check what labels exist
    screen.debug()
    
    // Check if we can find the email input
    const emailInput = screen.getByLabelText('Email académique')
    console.log('Email input found:', emailInput)
    
    const pwInput = screen.getByLabelText('Mot de passe')
    console.log('Password input found:', pwInput)
    
    await user.type(emailInput, 'invalid')
    console.log('After typing email, value should be:', emailInput.value)
    
    await user.type(pwInput, 'password123')
    console.log('After typing password, value should be:', pwInput.value)
    
    await user.click(screen.getByRole('button', { name: /Se connecter/ }))
    
    // Check for error
    await new Promise(r => setTimeout(r, 100))
    screen.debug()
  })
})
