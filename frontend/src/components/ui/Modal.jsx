import { useEffect, useRef, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { FiX } from 'react-icons/fi';

/**
 * Éléments où le focus peut réellement se poser. Le piège de focus prenait
 * auparavant tous les <button>, <input>… sans regarder s'ils étaient
 * désactivés ou masqués : à l'étape du QR de la double authentification, le
 * dernier « élément » était « Confirmer », désactivé tant que le code n'a pas
 * six chiffres — Tab depuis le champ de code sortait alors de la modale. Même
 * défaut avec l'<input type="file"> masqué des imports.
 */
const FOCALISABLES = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"]):not([disabled])',
].join(',');

function elementsFocalisables(conteneur) {
  // « .hidden » : display:none de Tailwind. Le test ne passe pas par la mise en
  // page (offsetParent, getClientRects), que jsdom ne calcule pas.
  return [...conteneur.querySelectorAll(FOCALISABLES)].filter(
    (el) => !el.closest('.hidden, [hidden], [aria-hidden="true"]'),
  );
}

export default function Modal({ isOpen, onClose, title, children, size = 'md', 'aria-describedby': ariaDescribedBy }) {
  const overlayRef = useRef(null);
  const contentRef = useRef(null);
  const previousActiveElement = useRef(null);
  const onCloseRef = useRef(onClose);

  // Garder la référence à jour sans déclencher de re-render
  useEffect(() => { onCloseRef.current = onClose; }, [onClose]);

  const handleKeyDown = useCallback((e) => {
    if (e.key === 'Escape') {
      onCloseRef.current();
      return;
    }

    // Trap focus inside modal
    if (e.key === 'Tab' && contentRef.current) {
      const focalisables = elementsFocalisables(contentRef.current);

      // Rien de focalisable : le focus reste sur la boîte elle-même.
      if (focalisables.length === 0) {
        e.preventDefault();
        contentRef.current.focus();
        return;
      }

      const premier = focalisables[0];
      const dernier = focalisables[focalisables.length - 1];
      const actif = document.activeElement;

      // La boîte elle-même reçoit le focus à l'ouverture : Maj+Tab depuis elle
      // sortait de la modale, comme Maj+Tab depuis le premier élément.
      if (e.shiftKey && (actif === premier || actif === contentRef.current)) {
        e.preventDefault();
        dernier.focus();
      } else if (!e.shiftKey && actif === dernier) {
        e.preventDefault();
        premier.focus();
      }
    }
  }, []); // Dépendances vides — onCloseRef est stable

  // L'application est masquée aux lecteurs d'écran pendant l'ouverture, pas
  // <body> : la modale y est rendue, et masquer <body> la masquait elle aussi.
  useEffect(() => {
    const application = document.getElementById('root');
    if (isOpen) {
      previousActiveElement.current = document.activeElement;
      document.body.style.overflow = 'hidden';
      application?.setAttribute('aria-hidden', 'true');

      // Focus the modal content
      setTimeout(() => {
        contentRef.current?.focus();
      }, 0);

      document.addEventListener('keydown', handleKeyDown);
    } else {
      document.body.style.overflow = '';
      application?.removeAttribute('aria-hidden');
      previousActiveElement.current?.focus();
    }
    return () => {
      document.body.style.overflow = '';
      application?.removeAttribute('aria-hidden');
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, handleKeyDown]);

  if (!isOpen) return null;

  const sizes = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };

  // Rendue dans <body> : placée dans la page, la modale héritait de la marge de
  // son conteneur (space-y) et son voile laissait une bande découverte en haut.
  return createPortal(
    <div
      ref={overlayRef}
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
      onClick={(e) => { if (e.target === overlayRef.current) onClose(); }}
      role="dialog"
      aria-modal="true"
      aria-labelledby={title ? 'modal-title' : undefined}
      aria-describedby={ariaDescribedBy}
    >
      <div
        ref={contentRef}
        tabIndex={-1}
        className={`bg-surface w-full ${sizes[size]} rounded-xxl shadow-[0_12px_32px_rgba(25,28,31,0.06)] animate-in fade-in zoom-in-95 duration-200`}
      >
        <div className="flex items-center justify-between px-6 py-4 border-b border-outline-variant/10">
          {title && <h2 id="modal-title" className="text-lg font-bold text-primary font-headline">{title}</h2>}
          <button
            onClick={onClose}
            className="p-1.5 hover:bg-surface-container-high rounded-xl transition-colors"
            aria-label="Fermer la fenêtre modale"
          >
            <FiX className="text-on-surface-variant" size={18} aria-hidden="true" />
          </button>
        </div>
        <div className="p-6 max-h-[70vh] overflow-y-auto">{children}</div>
      </div>
    </div>,
    document.body,
  );
}
