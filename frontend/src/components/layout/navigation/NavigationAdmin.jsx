import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { NavLink, useLocation } from 'react-router-dom';
import { MdHelp, MdAccountCircle, MdLogout, MdMenu, MdClose } from 'react-icons/md';
import { useAuth } from '../../../context/AuthContext';
import Logo from '../../ui/Logo';

const classeLien = ({ isActive }) =>
  `flex items-center gap-3 rounded-xl px-4 py-2.5 text-sm transition-all ${
    isActive
      ? 'translate-x-1 bg-surface-container-lowest font-semibold text-ink shadow-sm'
      : 'text-on-surface-variant hover:translate-x-1 hover:bg-nav-hover hover:text-ink'
  }`;

/**
 * Contenu commun aux barres latérales (bureau) et au tiroir (mobile) : un seul
 * endroit pour les liens, sinon les deux listes finissent par diverger — c'est
 * ce qui avait laissé Cours, Séances, Rapports, Profil et Déconnexion hors de
 * la navigation mobile.
 */
export function ContenuNavigation({ liens, prefixe = '', sousTitre }) {
  const { logout } = useAuth();

  return (
    <>
      <div className="mb-8 px-2">
        <Logo className="mb-1.5 h-7 w-auto" />
        <p className="text-[10px] font-medium uppercase tracking-widest text-on-surface-variant">{sousTitre}</p>
      </div>

      <nav aria-label="Navigation principale" className="flex-1 space-y-0.5 overflow-y-auto">
        {liens.map((lien) => (
          <NavLink key={lien.to} to={lien.to} end={lien.end} className={classeLien}>
            <span className="text-lg" aria-hidden="true">
              {lien.icon}
            </span>
            <span>{lien.label}</span>
          </NavLink>
        ))}
      </nav>

      <div className="space-y-0.5 border-t border-outline-variant/20 pt-4">
        <NavLink to={`${prefixe}/help`} className={classeLien}>
          <MdHelp className="text-lg" aria-hidden="true" />
          <span>Aide</span>
        </NavLink>
        <NavLink to={`${prefixe}/profile`} end className={classeLien}>
          <MdAccountCircle className="text-lg" aria-hidden="true" />
          <span>Profil</span>
        </NavLink>
        <button
          type="button"
          onClick={() => logout()}
          className="flex w-full items-center gap-3 rounded-xl px-4 py-2.5 text-left text-sm text-on-surface-variant transition-all hover:bg-error/10 hover:text-error"
        >
          <MdLogout className="text-lg" aria-hidden="true" />
          <span>Déconnexion</span>
        </button>
      </div>
    </>
  );
}

export function BarreLaterale(props) {
  return (
    <aside className="fixed left-0 top-0 z-50 hidden h-screen w-64 flex-col bg-nav p-5 md:flex">
      <ContenuNavigation {...props} />
    </aside>
  );
}

/**
 * Barre supérieure et tiroir de navigation sous 768 px. La barre latérale y
 * est masquée : sans ce composant, l'espace super-admin n'avait AUCUNE
 * navigation sur téléphone, et l'espace faculté seulement 5 entrées sur 11.
 */
export function NavigationMobile(props) {
  const [ouvert, setOuvert] = useState(false);
  const { pathname } = useLocation();
  const boutonRef = useRef(null);
  const tiroirRef = useRef(null);

  // Fermer à chaque changement de page : ajusté pendant le rendu plutôt que
  // dans un effet (motif du projet), pour ne pas rendre deux fois.
  const [cheminVu, setCheminVu] = useState(pathname);
  if (cheminVu !== pathname) {
    setCheminVu(pathname);
    setOuvert(false);
  }

  useEffect(() => {
    if (!ouvert) return undefined;
    const precedent = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    tiroirRef.current?.querySelector('a, button')?.focus();
    const bouton = boutonRef.current;
    const surTouche = (e) => {
      if (e.key === 'Escape') setOuvert(false);
    };
    document.addEventListener('keydown', surTouche);
    return () => {
      document.body.style.overflow = precedent;
      document.removeEventListener('keydown', surTouche);
      bouton?.focus();
    };
  }, [ouvert]);

  return (
    <>
      <header className="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-outline-variant/20 bg-nav/95 px-4 backdrop-blur md:hidden">
        <Logo className="h-6 w-auto" />
        <button
          ref={boutonRef}
          type="button"
          onClick={() => setOuvert(true)}
          aria-label="Ouvrir le menu"
          aria-expanded={ouvert}
          aria-controls="tiroir-navigation"
          className="flex h-10 w-10 items-center justify-center rounded-xl text-ink transition-colors hover:bg-nav-hover"
        >
          <MdMenu size={24} aria-hidden="true" />
        </button>
      </header>

      {ouvert &&
        createPortal(
          <div className="fixed inset-0 z-[60] md:hidden">
            <div
              className="absolute inset-0 animate-apparaitre bg-ink/30 backdrop-blur-sm"
              onClick={() => setOuvert(false)}
              aria-hidden="true"
            />
            <div
              ref={tiroirRef}
              id="tiroir-navigation"
              role="dialog"
              aria-modal="true"
              aria-label="Menu de navigation"
              className="absolute inset-y-0 left-0 flex w-72 max-w-[85vw] animate-glisser-depuis-gauche flex-col bg-nav p-5 shadow-xl"
            >
              <button
                type="button"
                onClick={() => setOuvert(false)}
                aria-label="Fermer le menu"
                className="absolute right-3 top-3 flex h-10 w-10 items-center justify-center rounded-xl text-on-surface-variant transition-colors hover:bg-nav-hover"
              >
                <MdClose size={22} aria-hidden="true" />
              </button>
              <ContenuNavigation {...props} />
            </div>
          </div>,
          document.body,
        )}
    </>
  );
}
