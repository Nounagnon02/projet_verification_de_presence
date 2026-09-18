import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'

const auth = vi.hoisted(() => ({ user: null }))

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: auth.user }),
}))

import ProtectedRoute from '../components/auth/ProtectedRoute'

/**
 * Le garde a trois branches : non connecté, mauvais rôle (deux destinations
 * selon le rôle réel), accès accordé. L'unique test d'origine ne vérifiait que
 * la disparition du contenu — pas OÙ l'utilisateur atterrit, qui est tout
 * l'objet du composant.
 */
const monter = (chemin, role) =>
  render(
    <MemoryRouter initialEntries={[chemin]}>
      <Routes>
        <Route path="/login" element={<div>Page de connexion</div>} />
        <Route path="/dashboard" element={<div>Tableau de bord faculté</div>} />
        <Route path="/super-admin" element={<div>Tableau de bord super admin</div>} />
        <Route
          path="/zone"
          element={
            <ProtectedRoute role={role}>
              <div>Contenu protégé</div>
            </ProtectedRoute>
          }
        />
      </Routes>
    </MemoryRouter>,
  )

describe('ProtectedRoute', () => {
  beforeEach(() => {
    auth.user = null
  })

  it('renvoie un visiteur non connecté vers /login', () => {
    monter('/zone', 'faculte_admin')

    expect(screen.getByText('Page de connexion')).toBeInTheDocument()
    expect(screen.queryByText('Contenu protégé')).not.toBeInTheDocument()
  })

  it('affiche le contenu au bon rôle', () => {
    auth.user = { id: 1, role: 'faculte_admin' }
    monter('/zone', 'faculte_admin')

    expect(screen.getByText('Contenu protégé')).toBeInTheDocument()
  })

  it('affiche le contenu à tout utilisateur connecté quand aucun rôle n’est exigé', () => {
    auth.user = { id: 1, role: 'enseignant' }
    monter('/zone', undefined)

    expect(screen.getByText('Contenu protégé')).toBeInTheDocument()
  })

  it('renvoie un admin de faculté qui vise une zone super admin vers son propre tableau de bord', () => {
    auth.user = { id: 1, role: 'faculte_admin' }
    monter('/zone', 'super_admin')

    expect(screen.getByText('Tableau de bord faculté')).toBeInTheDocument()
    expect(screen.queryByText('Contenu protégé')).not.toBeInTheDocument()
  })

  it('renvoie un super admin qui vise une zone de faculté vers /super-admin', () => {
    auth.user = { id: 1, role: 'super_admin' }
    monter('/zone', 'faculte_admin')

    expect(screen.getByText('Tableau de bord super admin')).toBeInTheDocument()
    expect(screen.queryByText('Contenu protégé')).not.toBeInTheDocument()
  })
})
