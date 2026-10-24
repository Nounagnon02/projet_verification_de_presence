import { useEffect, useRef, useState, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { FiX, FiCheck, FiCopy, FiArrowRight } from 'react-icons/fi';
import { MdApartment, MdEmail, MdPhone, MdLocationOn } from 'react-icons/md';
import { creerEtablissement } from '../../api/resources/etablissements';
import { useToastCtx } from '../../context/ToastContext';
import cn from '../../utils/cn';

// Mêmes éléments focalisables et même piège de focus que components/ui/Modal,
// dupliqués volontairement : ce panneau est visuellement et structurellement
// un composant à part (overlay latéral, pas une boîte centrée), pas une
// variante de Modal — le fusionner aurait forcé à toucher les 12 pages qui
// utilisent déjà Modal pour un besoin qui ne concerne que cet écran.
const FOCALISABLES = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"]):not([disabled])',
].join(',');

function elementsFocalisables(conteneur) {
  return [...conteneur.querySelectorAll(FOCALISABLES)].filter(
    (el) => !el.closest('.hidden, [hidden], [aria-hidden="true"]'),
  );
}

const FORM_VIDE = { code: '', nom: '', email: '', telephone: '', adresse: '' };

// Durée de la sortie : doit rester égale à celle de l'animation Tailwind
// `animate-glisser-vers-droite` (tailwind.config.js) pour que le panneau soit
// démonté juste après avoir fini de glisser, jamais avant ni longtemps après.
const DUREE_SORTIE_MS = 250;

export default function NouvelleFaculteModal({ isOpen, onClose, onCreated }) {
  const [form, setForm] = useState(FORM_VIDE);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [resultat, setResultat] = useState(null);
  // Ni dérivé du rendu ni posé dans un effet : seul un clic (gestionnaire
  // d'événement) le fait passer à true, ce qui laisse le panneau monté le
  // temps de l'animation de sortie avant que le parent ne mette isOpen à
  // false et ne démonte tout.
  const [closing, setClosing] = useState(false);
  const { addToast } = useToastCtx();

  const overlayRef = useRef(null);
  const panelRef = useRef(null);
  const previousActiveElement = useRef(null);
  const onCloseRef = useRef(onClose);
  useEffect(() => {
    onCloseRef.current = onClose;
  }, [onClose]);

  const reinitialiser = useCallback(() => {
    setForm(FORM_VIDE);
    setError('');
    setResultat(null);
    setLoading(false);
  }, []);

  const fermer = useCallback(() => {
    setClosing(true);
    setTimeout(() => {
      onCloseRef.current();
      setClosing(false);
      reinitialiser();
    }, DUREE_SORTIE_MS);
  }, [reinitialiser]);

  const handleKeyDown = useCallback(
    (e) => {
      if (e.key === 'Escape') {
        fermer();
        return;
      }
      if (e.key === 'Tab' && panelRef.current) {
        const focalisables = elementsFocalisables(panelRef.current);
        if (focalisables.length === 0) {
          e.preventDefault();
          panelRef.current.focus();
          return;
        }
        const premier = focalisables[0];
        const dernier = focalisables[focalisables.length - 1];
        const actif = document.activeElement;
        if (e.shiftKey && (actif === premier || actif === panelRef.current)) {
          e.preventDefault();
          dernier.focus();
        } else if (!e.shiftKey && actif === dernier) {
          e.preventDefault();
          premier.focus();
        }
      }
    },
    [fermer],
  );

  useEffect(() => {
    const application = document.getElementById('root');
    if (isOpen) {
      previousActiveElement.current = document.activeElement;
      document.body.style.overflow = 'hidden';
      application?.setAttribute('aria-hidden', 'true');
      setTimeout(() => panelRef.current?.focus(), 0);
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

  // Reste monté pendant l'animation de sortie : fermer() ne prévient le
  // parent (isOpen → false) qu'une fois celle-ci terminée.
  if (!isOpen && !closing) return null;

  const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

  // Pure décoration (barre de progression) : une adresse ou un téléphone
  // oublié ne doit jamais empêcher l'envoi, seuls les 3 champs réellement
  // obligatoires comptent.
  const champsRequis = ['code', 'nom', 'email'];
  const progression = Math.round(
    (champsRequis.filter((c) => form[c].trim().length > 0).length / champsRequis.length) * 100,
  );

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (!form.code.trim() || !form.nom.trim() || !form.email.trim()) {
      setError("Le code, le nom et l'email sont obligatoires.");
      return;
    }

    setLoading(true);
    try {
      const data = await creerEtablissement(form);
      if (data.success) {
        setResultat({
          etablissement: data.data?.etablissement || data.data,
          credentials: data.data?.credentials,
        });
        onCreated?.();
      }
    } catch (err) {
      const data = err.response?.data;
      const msg =
        (data?.errors ? Object.values(data.errors).flat().join(', ') : null) ||
        data?.message ||
        'Erreur lors de la création.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  const copier = async (valeur, libelle) => {
    try {
      await navigator.clipboard.writeText(valeur);
      addToast(`${libelle} copié.`, 'success', 2000);
    } catch {
      addToast('Copie impossible : sélectionnez le texte manuellement.', 'warning');
    }
  };

  return createPortal(
    <div
      ref={overlayRef}
      className={cn(
        'fixed inset-0 z-50 flex justify-end bg-ink/30 backdrop-blur-sm',
        closing ? 'animate-disparaitre' : 'animate-apparaitre',
      )}
      onClick={(e) => {
        if (e.target === overlayRef.current) fermer();
      }}
    >
      <div
        ref={panelRef}
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-labelledby="nouvelle-faculte-titre"
        className={cn(
          'relative flex h-full w-full max-w-md flex-col bg-surface-container-lowest shadow-[-16px_0_48px_rgba(1,21,73,0.18)]',
          closing ? 'animate-glisser-vers-droite' : 'animate-glisser-depuis-droite',
        )}
      >
        {/* Barre de progression des champs requis — accrochée au bord supérieur */}
        <div className="h-1 w-full bg-primary/10">
          <div
            className="h-full bg-gradient-to-r from-secondary to-primary transition-all duration-300 ease-out"
            style={{ width: `${resultat ? 100 : progression}%` }}
          />
        </div>

        {/* En-tête de marque */}
        <div className="relative overflow-hidden bg-gradient-to-br from-[#011549] via-[#011549] to-[#0a1a3a] px-7 pb-7 pt-8 text-white shrink-0">
          <div className="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/5 blur-2xl" />
          <div className="absolute -bottom-16 -left-10 h-40 w-40 rounded-full bg-secondary/10 blur-2xl" />
          <div className="relative flex items-start justify-between">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10">
              <MdApartment size={24} />
            </div>
            <button
              onClick={fermer}
              className="rounded-xl p-2 text-white/70 transition-colors hover:bg-white/10 hover:text-white"
              aria-label="Fermer"
            >
              <FiX size={18} />
            </button>
          </div>
          <h2 id="nouvelle-faculte-titre" className="relative mt-5 font-headline text-2xl font-bold">
            Nouvelle faculté
          </h2>
          <p className="relative mt-1 text-sm text-white/60">
            Elle apparaîtra aussitôt dans la liste des établissements de l'UAC.
          </p>
        </div>

        <div className="flex-1 overflow-y-auto px-7 py-6">
          {resultat ? (
            <div className="space-y-6">
              <div className="flex flex-col items-center gap-3 py-4 text-center">
                <div className="flex h-14 w-14 items-center justify-center rounded-full bg-statut-succes-fond text-statut-succes">
                  <FiCheck size={28} />
                </div>
                <div>
                  <p className="font-headline text-lg font-bold text-primary">Faculté créée</p>
                  <p className="mt-1 text-sm text-on-surface-variant">
                    « {resultat.etablissement?.nom} » est prête, avec son compte administrateur.
                  </p>
                </div>
              </div>

              {resultat.credentials && (
                <div className="space-y-2 rounded-xxl border border-outline-variant/20 bg-surface-container-high p-4">
                  <p className="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                    Identifiants administrateur
                  </p>
                  {[
                    { libelle: 'Email', valeur: resultat.credentials.email },
                    { libelle: 'Mot de passe', valeur: resultat.credentials.password },
                  ].map(({ libelle, valeur }) => (
                    <div
                      key={libelle}
                      className="flex items-center justify-between gap-3 rounded-xl bg-surface-container-lowest px-3 py-2.5"
                    >
                      <div className="min-w-0">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">
                          {libelle}
                        </p>
                        <p className="truncate font-mono text-sm text-primary">{valeur}</p>
                      </div>
                      <button
                        type="button"
                        onClick={() => copier(valeur, libelle)}
                        className="shrink-0 rounded-lg p-2 text-on-surface-variant transition-colors hover:bg-primary/5 hover:text-primary"
                        aria-label={`Copier ${libelle.toLowerCase()}`}
                      >
                        <FiCopy size={15} />
                      </button>
                    </div>
                  ))}
                  <p className="pt-1 text-xs text-on-surface-variant">
                    Transmettez-les à l'administrateur de cette faculté : ils ne seront plus affichés ensuite.
                  </p>
                </div>
              )}
            </div>
          ) : (
            <form id="form-nouvelle-faculte" onSubmit={handleSubmit} className="space-y-5">
              {error && (
                <div className="rounded-xl border border-error/10 bg-statut-erreur-fond px-4 py-3 text-sm text-statut-erreur">
                  {error}
                </div>
              )}

              <div className="space-y-1.5">
                <label
                  className="ml-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                  htmlFor="code"
                >
                  Code <span className="font-normal normal-case tracking-normal">(ex: FAST, EPAC, FDS) *</span>
                </label>
                <div className="relative">
                  <MdApartment
                    className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant"
                    size={16}
                  />
                  <input
                    id="code"
                    name="code"
                    value={form.code}
                    onChange={handleChange}
                    className="w-full rounded-xl border-b-2 border-transparent bg-surface-container-high py-3 pl-11 pr-4 text-sm text-on-surface transition-colors focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                    placeholder="FAST"
                    disabled={loading}
                    autoFocus
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label
                  className="ml-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                  htmlFor="nom"
                >
                  Nom complet *
                </label>
                <div className="relative">
                  <MdApartment
                    className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant"
                    size={16}
                  />
                  <input
                    id="nom"
                    name="nom"
                    value={form.nom}
                    onChange={handleChange}
                    className="w-full rounded-xl border-b-2 border-transparent bg-surface-container-high py-3 pl-11 pr-4 text-sm text-on-surface transition-colors focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                    placeholder="Faculté des Sciences et Techniques"
                    disabled={loading}
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label
                  className="ml-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                  htmlFor="email"
                >
                  Email de contact *
                </label>
                <div className="relative">
                  <MdEmail
                    className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant"
                    size={16}
                  />
                  <input
                    id="email"
                    name="email"
                    type="email"
                    value={form.email}
                    onChange={handleChange}
                    className="w-full rounded-xl border-b-2 border-transparent bg-surface-container-high py-3 pl-11 pr-4 text-sm text-on-surface transition-colors focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                    placeholder="contact@fast.uac.bj"
                    disabled={loading}
                  />
                </div>
                <p className="ml-1 text-xs text-on-surface-variant">
                  Un compte administrateur sera créé avec cet email.
                </p>
              </div>

              <div className="space-y-1.5">
                <label
                  className="ml-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                  htmlFor="telephone"
                >
                  Téléphone
                </label>
                <div className="relative">
                  <MdPhone
                    className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant"
                    size={16}
                  />
                  <input
                    id="telephone"
                    name="telephone"
                    value={form.telephone}
                    onChange={handleChange}
                    className="w-full rounded-xl border-b-2 border-transparent bg-surface-container-high py-3 pl-11 pr-4 text-sm text-on-surface transition-colors focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                    placeholder="+229 01 23 45 67"
                    disabled={loading}
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label
                  className="ml-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant"
                  htmlFor="adresse"
                >
                  Adresse
                </label>
                <div className="relative">
                  <MdLocationOn
                    className="pointer-events-none absolute left-4 top-3 text-on-surface-variant"
                    size={16}
                  />
                  <textarea
                    id="adresse"
                    name="adresse"
                    value={form.adresse}
                    onChange={handleChange}
                    rows={2}
                    className="w-full resize-none rounded-xl border-b-2 border-transparent bg-surface-container-high py-3 pl-11 pr-4 text-sm text-on-surface transition-colors focus:border-primary focus:bg-surface-container-lowest focus:outline-none"
                    placeholder="Abomey-Calavi, Bénin"
                    disabled={loading}
                  />
                </div>
              </div>
            </form>
          )}
        </div>

        {/* Pied d'écran collé en bas */}
        <div className="shrink-0 border-t border-outline-variant/10 px-7 py-5">
          {resultat ? (
            <button
              type="button"
              onClick={fermer}
              className="flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-marque to-marque-fonce py-3.5 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-transform hover:scale-[0.99] active:scale-[0.98]"
            >
              Terminé
            </button>
          ) : (
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={fermer}
                disabled={loading}
                className="rounded-xl px-5 py-3.5 text-sm font-semibold text-on-surface-variant transition-colors hover:bg-surface-container-high disabled:opacity-50"
              >
                Annuler
              </button>
              <button
                type="submit"
                form="form-nouvelle-faculte"
                disabled={loading}
                className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-marque to-marque-fonce py-3.5 text-sm font-bold text-white shadow-lg shadow-primary/20 transition-transform hover:scale-[0.99] active:scale-[0.98] disabled:opacity-60"
              >
                {loading ? (
                  <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                ) : (
                  <FiArrowRight size={16} />
                )}
                Créer la faculté
              </button>
            </div>
          )}
        </div>
      </div>
    </div>,
    document.body,
  );
}
