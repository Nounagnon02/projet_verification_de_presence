import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../api/axios', () => ({
  default: { post: vi.fn() },
}))

import ResetPasswordPage from '../pages/auth/ResetPasswordPage'

/**
 * Cette page n'était couverte par aucun test, alors que LoginPage l'était. C'est
 * ce qui a laissé passer une icône utilisée sans être importée : le bundle se
 * construisait sans erreur et la page ne plantait qu'à l'affichage — or c'est le
 * parcours imposé aux administrateurs dont le mot de passe initial est aléatoire.
 *
 * Un simple rendu suffit à détecter ce type d'oubli : un identifiant absent lève
 * une ReferenceError au moment du rendu.
 */
describe('ResetPasswordPage', () => {
  function afficher(url = '/reset-password') {
    return render(
      <MemoryRouter initialEntries={[url]}>
        <ResetPasswordPage />
      </MemoryRouter>
    )
  }

  it('affiche les trois champs du formulaire', () => {
    afficher()

    // Le champ e-mail est précédé de l'icône FiMail : si son import manque, le
    // rendu échoue avant d'atteindre cette assertion.
    expect(screen.getByLabelText('Email académique')).toBeInTheDocument()
    expect(screen.getByLabelText('Nouveau mot de passe')).toBeInTheDocument()
    expect(screen.getByLabelText('Confirmer le mot de passe')).toBeInTheDocument()
  })

  it("préremplit l'e-mail depuis l'URL", () => {
    afficher('/reset-password?token=abc123&email=test%40uac.bj')

    expect(screen.getByLabelText('Email académique')).toHaveValue('test@uac.bj')
  })

  it('se rend sans lever d\'erreur, avec et sans jeton', () => {
    expect(() => afficher('/reset-password')).not.toThrow()
    expect(() => afficher('/reset-password?token=abc123')).not.toThrow()
  })
})
