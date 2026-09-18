import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

const { api } = vi.hoisted(() => {
  const vide = () => Promise.resolve({ data: { success: true, data: [] } })
  return { api: { get: vi.fn(vide), post: vi.fn(vide), put: vi.fn(vide), patch: vi.fn(vide), delete: vi.fn(vide) } }
})

vi.mock('../api/axios', () => ({ default: api }))
vi.mock('../context/ToastContext', () => ({ useToastCtx: () => ({ addToast: vi.fn() }) }))

import ProfilePage from '../pages/profile/ProfilePage'

const PROFIL = {
  name: 'Admin IFRI', email: 'admin@test.local', role: 'faculte_admin',
  two_factor_enabled: true, created_at: '2026-01-10',
}

const ok = (data) => Promise.resolve({ data: { success: true, data } })

/**
 * La sécurité du compte vivait dans Paramètres, qui configure l'établissement.
 * Elle concerne la personne connectée : elle est désormais dans Profil.
 */
describe('Profil — sécurité du compte', () => {
  beforeEach(() => {
    api.get.mockReset()
    api.put.mockReset()
    api.get.mockImplementation((url) => (url === '/admin/profile' ? ok(PROFIL) : ok([])))
    api.put.mockImplementation(() => ok({}))
  })

  it('réunit les informations personnelles et la sécurité du compte', async () => {
    renderPage(<ProfilePage />)

    expect(await screen.findByRole('heading', { name: 'Informations personnelles' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Mot de passe' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Authentification à deux facteurs' })).toBeInTheDocument()

    // L'état de la double authentification vient du profil déjà chargé : un
    // seul appel, et « Désactiver » puisqu'elle est active.
    expect(screen.getByRole('button', { name: 'Désactiver' })).toBeInTheDocument()
    expect(api.get.mock.calls.filter(([url]) => url === '/admin/profile')).toHaveLength(1)
  })

  it('change le mot de passe depuis le profil', async () => {
    renderPage(<ProfilePage />)
    await screen.findByRole('heading', { name: 'Mot de passe' })

    fireEvent.change(screen.getByLabelText('Mot de passe actuel'), { target: { value: 'ancien-mdp-123' } })
    fireEvent.change(screen.getByLabelText('Nouveau mot de passe'), { target: { value: 'nouveau-mdp-456' } })
    fireEvent.change(screen.getByLabelText('Confirmer le mot de passe'), { target: { value: 'nouveau-mdp-456' } })
    fireEvent.click(screen.getByRole('button', { name: 'Mettre à jour' }))

    await waitFor(() => expect(api.put).toHaveBeenCalledWith('/admin/profile/password', {
      current_password: 'ancien-mdp-123',
      password: 'nouveau-mdp-456',
      password_confirmation: 'nouveau-mdp-456',
    }))
  })

  // Un super admin sans 2FA est renvoyé ici par l'intercepteur axios
  // (?securite=requise) quand /super-admin/* répond 403
  // { code: 'two_factor_setup_required' } : c'est cette page qui porte
  // l'activation, juste au-dessous.
  it('affiche un message quand la route venait de refuser faute de 2FA', async () => {
    renderPage(<ProfilePage />, { route: '/profile?securite=requise' })

    expect(await screen.findByText(/authentification à deux facteurs est obligatoire/i)).toBeInTheDocument()
  })

  it("n'affiche rien sans ce paramètre", async () => {
    renderPage(<ProfilePage />)

    await screen.findByRole('heading', { name: 'Informations personnelles' })
    expect(screen.queryByText(/authentification à deux facteurs est obligatoire/i)).not.toBeInTheDocument()
  })
})
