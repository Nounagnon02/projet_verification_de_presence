import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import Modal from '../components/ui/Modal'

/**
 * Le composant Modal est l'enveloppe de toutes les boîtes de dialogue de
 * l'application depuis la migration des modales écrites à la main. Seul son
 * rendu hors de la page était testé (VoileModale) ; le piège de focus, lui,
 * laissait Tab sortir de la modale dès que le dernier bouton était désactivé.
 */
const monter = (enfants, props = {}) => {
  const onClose = props.onClose ?? vi.fn()
  render(
    <Modal isOpen onClose={onClose} title="Titre" {...props}>
      {enfants}
    </Modal>,
  )
  return onClose
}

const tab = (options = {}) => {
  const evenement = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true, ...options })
  document.dispatchEvent(evenement)
  return evenement
}

describe('Modal — sémantique', () => {
  it('se déclare comme boîte de dialogue modale, nommée par son titre', () => {
    monter(<p>Contenu</p>)

    const dialogue = screen.getByRole('dialog', { name: 'Titre' })
    expect(dialogue).toHaveAttribute('aria-modal', 'true')
  })

  it('ne rend rien tant qu’elle est fermée', () => {
    render(<Modal isOpen={false} onClose={() => {}} title="Titre">Contenu</Modal>)

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('masque l\'application aux lecteurs d\'écran, jamais la modale elle-même', () => {
    const racine = document.createElement('div')
    racine.id = 'root'
    document.body.appendChild(racine)

    const { unmount } = render(<Modal isOpen={true} onClose={() => {}} title="Titre">Contenu</Modal>, { container: racine })

    expect(racine).toHaveAttribute('aria-hidden', 'true')
    expect(document.body).not.toHaveAttribute('aria-hidden')
    // Accessible sans l'option « hidden » : elle n'est plus sous un aria-hidden.
    expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true')

    unmount()
    expect(racine).not.toHaveAttribute('aria-hidden')
    racine.remove()
  })
})

describe('Modal — fermeture', () => {
  it('se ferme par Échap', () => {
    const onClose = monter(<p>Contenu</p>)

    fireEvent.keyDown(document, { key: 'Escape' })

    expect(onClose).toHaveBeenCalledTimes(1)
  })

  it('se ferme par la croix', () => {
    const onClose = monter(<p>Contenu</p>)

    fireEvent.click(screen.getByRole('button', { name: 'Fermer la fenêtre modale' }))

    expect(onClose).toHaveBeenCalledTimes(1)
  })

  it('se ferme par un clic sur le voile, pas par un clic dans la boîte', () => {
    const onClose = monter(<p>Contenu</p>)

    fireEvent.click(screen.getByText('Contenu'))
    expect(onClose).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('dialog'))
    expect(onClose).toHaveBeenCalledTimes(1)
  })
})

describe('Modal — piège de focus', () => {
  it('ramène du dernier au premier élément avec Tab', () => {
    monter(
      <>
        <input aria-label="Premier" />
        <button type="button">Dernier</button>
      </>,
    )
    screen.getByRole('button', { name: 'Dernier' }).focus()

    const evenement = tab()

    expect(evenement.defaultPrevented).toBe(true)
    // Le premier élément focalisable est la croix de fermeture, avant le contenu.
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Fermer la fenêtre modale' }))
  })

  it('ramène du premier au dernier élément avec Maj+Tab', () => {
    monter(<button type="button">Dernier</button>)
    screen.getByRole('button', { name: 'Fermer la fenêtre modale' }).focus()

    const evenement = tab({ shiftKey: true })

    expect(evenement.defaultPrevented).toBe(true)
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Dernier' }))
  })

  it('ignore un dernier bouton désactivé : Tab depuis le champ précédent reboucle', () => {
    // Le cas de la double authentification : « Confirmer » est désactivé tant que
    // le code n’a pas six chiffres, et Tab depuis le champ sortait de la modale.
    monter(
      <>
        <input aria-label="Code" />
        <button type="button" disabled>Confirmer</button>
      </>,
    )
    screen.getByRole('textbox', { name: 'Code' }).focus()

    const evenement = tab()

    expect(evenement.defaultPrevented).toBe(true)
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Fermer la fenêtre modale' }))
  })

  it('ignore un champ de fichier masqué', () => {
    monter(
      <>
        <button type="button">Choisir</button>
        <input type="file" className="hidden" aria-label="Fichier" />
      </>,
    )
    screen.getByRole('button', { name: 'Choisir' }).focus()

    const evenement = tab()

    // « Choisir » est le dernier élément visible : on reboucle, on ne file pas
    // vers l’<input> masqué.
    expect(evenement.defaultPrevented).toBe(true)
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Fermer la fenêtre modale' }))
  })

  it('laisse Tab circuler normalement au milieu de la boîte', () => {
    monter(
      <>
        <input aria-label="A" />
        <input aria-label="B" />
      </>,
    )
    screen.getByRole('textbox', { name: 'A' }).focus()

    expect(tab().defaultPrevented).toBe(false)
  })

  it('Maj+Tab depuis la boîte elle-même, qui reçoit le focus à l’ouverture, ne sort pas', () => {
    monter(<button type="button">Dernier</button>)
    screen.getByRole('dialog').firstElementChild.focus()

    const evenement = tab({ shiftKey: true })

    expect(evenement.defaultPrevented).toBe(true)
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Dernier' }))
  })
})
