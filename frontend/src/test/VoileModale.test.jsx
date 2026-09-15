import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { renderPage } from './utils/renderPage'
import Modal from '../components/ui/Modal'

// vi.mock est remonté en tête de module : le client factice doit être construit
// dans vi.hoisted.
const { api } = vi.hoisted(() => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

vi.mock('../api/axios', () => ({ default: api, TOKEN_KEY: 'token' }))

import EvenementManagementPage from '../pages/events/EvenementManagementPage'

/**
 * Le voile d'une modale doit couvrir tout l'écran.
 *
 * Rendu dans la page, il était l'enfant d'un conteneur « space-y-6 » : Tailwind
 * lui donnait une marge haute de 24 px, qui décalait ce « fixed inset-0 » vers
 * le bas et laissait une bande découverte en haut de l'écran. jsdom ne calcule
 * pas la mise en page : on vérifie donc la cause, le voile doit être un enfant
 * direct de <body>.
 */
describe('Voile des modales', () => {
  beforeEach(() => {
    api.get.mockImplementation(() => Promise.resolve({ data: { success: true, data: [] } }))
  })

  it('le composant Modal se rend hors du conteneur de la page', () => {
    render(
      <div className="space-y-6">
        <p>Contenu de la page</p>
        <Modal isOpen onClose={() => {}} title="Titre">Contenu</Modal>
      </div>,
    )

    expect(screen.getByRole('dialog', { hidden: true }).parentElement).toBe(document.body)
  })

  it('le formulaire des événements se rend hors du conteneur de la page', async () => {
    renderPage(<EvenementManagementPage />)
    fireEvent.click(await screen.findByRole('button', { name: /Nouvel événement/ }))

    const voile = screen.getByRole('heading', { name: 'Nouvel événement' }).closest('.fixed')
    expect(voile.parentElement).toBe(document.body)
  })

  it('aucune modale de page ne se rend dans la mise en page', () => {
    // Garde-fou pour les modales écrites à la main dans les pages : chaque voile
    // « fixed inset-0 » doit passer par createPortal.
    const sources = import.meta.glob(['../pages/**/*.jsx', '../components/**/*.jsx'], {
      query: '?raw', import: 'default', eager: true,
    })
    const fautifs = Object.entries(sources)
      .filter(([, code]) => (code.match(/fixed inset-0/g) || []).length > (code.match(/createPortal\(/g) || []).length)
      .map(([fichier]) => fichier)

    expect(fautifs).toEqual([])
  })
})
