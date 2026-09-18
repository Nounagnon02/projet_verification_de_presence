import { useEffect } from 'react'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, act } from '@testing-library/react'

const { api } = vi.hoisted(() => ({ api: { post: vi.fn() } }))

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'auth_token' }))
vi.mock('../api/cache', () => ({ invalidateApiCache: vi.fn() }))

import { AuthProvider, useAuth } from '../context/AuthContext'
import { invalidateApiCache } from '../api/cache'

/**
 * Jusqu'ici couvert seulement par ricochet, à travers les tests de la page de
 * connexion. Le contexte porte trois choses qui méritent d'être verrouillées :
 * la restauration synchrone de la session, la purge d'un stockage corrompu, et
 * l'effacement complet à la déconnexion (jeton, utilisateur, cache d'API).
 */
// Le contexte courant, relevé dans un effet : réaffecter une variable de module
// pendant le rendu serait un effet de bord (react-hooks/globals).
const capture = { courant: null }
function Sonde() {
  const auth = useAuth()
  useEffect(() => {
    capture.courant = auth
  })
  return <div>{auth.user ? `connecté : ${auth.user.email}` : 'anonyme'}</div>
}

const monter = () =>
  render(
    <AuthProvider>
      <Sonde />
    </AuthProvider>,
  )

describe('AuthContext', () => {
  beforeEach(() => {
    localStorage.clear()
    api.post.mockReset()
    invalidateApiCache.mockReset()
  })

  it('démarre anonyme sans session stockée', () => {
    monter()

    expect(screen.getByText('anonyme')).toBeInTheDocument()
  })

  it('restaure la session stockée dès le premier rendu, sans attendre le réseau', () => {
    localStorage.setItem(
      'presence_user',
      JSON.stringify({ id: 7, name: 'Admin', email: 'admin@uac.bj', role: 'faculte_admin', extra: 'ignoré' }),
    )
    monter()

    expect(screen.getByText('connecté : admin@uac.bj')).toBeInTheDocument()
    // Seuls les champs d'interface sont conservés.
    expect(capture.courant.user).toEqual({ id: 7, name: 'Admin', email: 'admin@uac.bj', role: 'faculte_admin' })
    expect(api.post).not.toHaveBeenCalled()
  })

  it('purge un stockage illisible et le jeton avec lui plutôt que de restaurer à moitié', () => {
    localStorage.setItem('presence_user', '{pas du json')
    localStorage.setItem('auth_token', 'jeton-orphelin')
    monter()

    expect(screen.getByText('anonyme')).toBeInTheDocument()
    expect(localStorage.getItem('presence_user')).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
  })

  it('ignore un utilisateur stocké sans identifiant', () => {
    localStorage.setItem('presence_user', JSON.stringify({ name: 'Sans id' }))
    monter()

    expect(screen.getByText('anonyme')).toBeInTheDocument()
  })

  it('login range le jeton et l’utilisateur, et ne garde que les champs d’interface', async () => {
    api.post.mockResolvedValue({
      data: {
        success: true,
        data: {
          token: 'jeton-123',
          user: { id: 3, name: 'Kofi', email: 'kofi@uac.bj', role: 'super_admin', two_factor_secret: 'NE-DOIT-PAS-FUIR' },
        },
      },
    })
    monter()

    await act(async () => {
      await capture.courant.login('kofi@uac.bj', 'motdepasse')
    })

    expect(api.post).toHaveBeenCalledWith('/login', { email: 'kofi@uac.bj', password: 'motdepasse' }, expect.any(Object))
    expect(localStorage.getItem('auth_token')).toBe('jeton-123')
    expect(JSON.parse(localStorage.getItem('presence_user'))).toEqual({
      id: 3,
      name: 'Kofi',
      email: 'kofi@uac.bj',
      role: 'super_admin',
    })
    expect(screen.getByText('connecté : kofi@uac.bj')).toBeInTheDocument()
  })

  it('logout efface la session et vide le cache, même si le serveur échoue', async () => {
    localStorage.setItem('presence_user', JSON.stringify({ id: 1, name: 'A', email: 'a@uac.bj', role: 'faculte_admin' }))
    localStorage.setItem('auth_token', 'jeton')
    api.post.mockRejectedValue(new Error('réseau coupé'))
    monter()

    await act(async () => {
      await capture.courant.logout()
    })

    expect(screen.getByText('anonyme')).toBeInTheDocument()
    expect(localStorage.getItem('presence_user')).toBeNull()
    expect(localStorage.getItem('auth_token')).toBeNull()
    // Sans ce vidage, l'utilisateur suivant du navigateur verrait le cache du précédent.
    expect(invalidateApiCache).toHaveBeenCalledTimes(1)
  })
})
