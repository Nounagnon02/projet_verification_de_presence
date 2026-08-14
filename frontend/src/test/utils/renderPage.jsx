import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'

/**
 * Rend une page dans un routeur mémoire, sans dépendance réseau.
 *
 * Ces pages n'avaient aucun test : c'est ce qui a laissé passer une icône
 * utilisée sans être importée, invisible à la compilation et fatale au rendu.
 * Un simple montage suffit à détecter cette classe d'erreur, ainsi que les
 * plantages d'accès à des données absentes.
 *
 * Les mocks de `api` et des contextes se déclarent dans le fichier de test
 * appelant, `vi.mock` étant remonté en tête de module.
 */
export function renderPage(ui, { route = '/' } = {}) {
  return render(<MemoryRouter initialEntries={[route]}>{ui}</MemoryRouter>)
}

/**
 * Réponse d'API vide, au format que ces pages attendent : certaines lisent
 * `data.data`, d'autres `data` directement, d'où les deux formes.
 */
export function reponseVide(charge = []) {
  return Promise.resolve({ data: { success: true, data: charge } })
}

/**
 * Client d'API factice : toute méthode renvoie une réponse vide. Permet de
 * monter une page sans se soucier des endpoints qu'elle appelle.
 */
export function apiFactice(reponses = {}) {
  const parDefaut = () => reponseVide()

  return {
    get: (url) => (reponses[url] ? reponses[url]() : parDefaut()),
    post: parDefaut,
    put: parDefaut,
    patch: parDefaut,
    delete: parDefaut,
  }
}
