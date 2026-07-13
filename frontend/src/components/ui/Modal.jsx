import { useEffect, useRef, useCallback } from 'react';
import { FiX } from 'react-icons/fi';

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
      const focusableElements = contentRef.current.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (e.shiftKey && document.activeElement === firstElement) {
        e.preventDefault();
        lastElement.focus();
      } else if (!e.shiftKey && document.activeElement === lastElement) {
        e.preventDefault();
        firstElement.focus();
      }
    }
  }, []); // Dépendances vides — onCloseRef est stable

  useEffect(() => {
    if (isOpen) {
      previousActiveElement.current = document.activeElement;
      document.body.style.overflow = 'hidden';
      document.body.setAttribute('aria-hidden', 'true');

      // Focus the modal content
      setTimeout(() => {
        contentRef.current?.focus();
      }, 0);

      document.addEventListener('keydown', handleKeyDown);
    } else {
      document.body.style.overflow = '';
      document.body.removeAttribute('aria-hidden');
      previousActiveElement.current?.focus();
    }
    return () => {
      document.body.style.overflow = '';
      document.body.removeAttribute('aria-hidden');
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isOpen, handleKeyDown]);

  if (!isOpen) return null;

  const sizes = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };

  return (
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
    </div>
  );
}
