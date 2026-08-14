import { describe, it, expect, vi, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderPage } from './utils/renderPage'

// vi.mock est remonté en tête de module : le client factice doit donc être
// construit dans vi.hoisted, sans quoi la référence n'existe pas encore.
const { api } = vi.hoisted(() => {
  const vide = () => Promise.resolve({ data: { success: true, data: [] } })
  return {
    api: {
      get: vi.fn(vide),
      post: vi.fn(vide),
      put: vi.fn(vide),
      patch: vi.fn(vide),
      delete: vi.fn(vide),
    },
  }
})

vi.mock('../api/axios', () => ({ default: api }))
vi.mock('../context/ToastContext', () => ({
  useToastCtx: () => ({ addToast: vi.fn() }),
}))

import SallesPage from '../pages/settings/SallesPage'

const vide = () => Promise.resolve({ data: { success: true, data: [] } })

describe('SallesPage', () => {
  beforeEach(() => {
    api.get.mockImplementation(vide)
  })

  it('se monte et sort de son état de chargement', async () => {
    renderPage(<SallesPage />)

    await waitFor(() => {
      expect(screen.queryByText(/chargement/i)).not.toBeInTheDocument()
    })
  })

  it("ne lève pas d'erreur au montage", () => {
    expect(() => renderPage(<SallesPage />)).not.toThrow()
  })

  /**
   * Régression : la page n'annulait pas sa requête au démontage. Une réponse
   * arrivant après coup écrivait dans un composant démonté, et deux recherches
   * rapprochées pouvaient se résoudre dans le désordre — la plus ancienne
   * écrasant alors la plus récente.
   */
  it('ignore une réponse arrivée après le démontage', async () => {
    let resoudre
    const lente = new Promise((r) => { resoudre = r })
    api.get.mockImplementation((url) => (url === '/admin/salles' ? lente : vide()))

    const { unmount } = renderPage(<SallesPage />)
    unmount()

    resoudre({ data: { success: true, data: [{ id: 1, nom: 'Amphi', code: 'A1' }] } })
    await lente

    expect(api.get).toHaveBeenCalledWith('/admin/salles', expect.anything())
  })
})
