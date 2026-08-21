import { useState } from 'react';
import { FiMonitor, FiSmartphone, FiGlobe, FiLogOut, FiAlertTriangle, FiClock, FiRefreshCw } from 'react-icons/fi';
import Badge from '../ui/Badge';
import Button from '../ui/Button';
import Modal from '../ui/Modal';
import EmptyState from '../ui/EmptyState';
import LoadingSkeleton from '../ui/LoadingSkeleton';
import useApi from '../../hooks/useApi';
import api from '../../api/axios';
import { useToastCtx } from '../../context/ToastContext';
import { formatDateTime } from '../../utils/formatters';

/**
 * Traduction du nom du jeton Sanctum en appareil lisible.
 *
 * Le backend n'expose que le nom du jeton (`api-token` pour la console web,
 * `mobile-app` pour l'application étudiante) : c'est la seule information
 * d'identification disponible. Un nom inconnu est affiché tel quel plutôt que
 * masqué derrière un libellé générique.
 */
function decrireAppareil(nom) {
  if (nom === 'api-token') return { libelle: 'Navigateur web', Icone: FiMonitor };
  if (nom === 'mobile-app') return { libelle: 'Application mobile', Icone: FiSmartphone };
  return { libelle: nom || 'Appareil inconnu', Icone: FiGlobe };
}

/**
 * Mise en forme de la date de connexion.
 *
 * Le backend renvoie `created_at` au format « Y-m-d H:i ». L'espace séparateur
 * n'est pas accepté partout par `Date` : on le remplace par « T » avant de
 * déléguer à `formatDateTime`, et on retombe sur la valeur brute si la date
 * reste illisible.
 */
function formatConnexion(valeur) {
  if (!valeur) return '—';
  const normalise = String(valeur).replace(' ', 'T');
  if (Number.isNaN(new Date(normalise).getTime())) return String(valeur);
  return formatDateTime(normalise);
}

/**
 * Ligne décrivant une session.
 *
 * Définie au niveau module pour ne pas être recréée à chaque rendu du panneau.
 */
const SessionRow = ({ session }) => {
  const { libelle, Icone } = decrireAppareil(session.name);

  return (
    <li
      className={`flex items-start gap-4 p-4 rounded-xl border transition-colors ${
        session.is_current
          ? 'border-primary/30 bg-primary/[0.03]'
          : 'border-outline-variant/10 bg-surface-container-lowest'
      }`}
    >
      <div className={`p-2.5 rounded-xl shrink-0 ${session.is_current ? 'bg-primary/10' : 'bg-surface-container-high'}`}>
        <Icone size={18} className={session.is_current ? 'text-primary' : 'text-on-surface-variant'} aria-hidden="true" />
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <p className="text-sm font-semibold text-on-surface truncate">{libelle}</p>
          {session.is_current && <Badge variant="success">Session courante</Badge>}
        </div>
        <p className="mt-1 flex items-center gap-1.5 text-xs text-on-surface-variant">
          <FiClock size={12} aria-hidden="true" />
          Dernière activité : {session.last_active || '—'}
        </p>
        <p className="text-xs text-on-surface-variant">
          Connectée le {formatConnexion(session.created_at)}
        </p>
      </div>
    </li>
  );
};

/**
 * Panneau des sessions actives (mémoire §2.1.2).
 *
 * Une « session » correspond à un jeton Sanctum, donc à un appareil connecté au
 * compte. Le panneau les liste et permet de révoquer tous les autres jetons en
 * une action, ce qui déconnecte réellement les appareils concernés.
 */
export default function ActiveSessionsPanel() {
  const { data: sessions, loading, error, refetch } = useApi('/admin/sessions', {}, { defaultData: [] });
  const [showConfirm, setShowConfirm] = useState(false);
  const [revoking, setRevoking] = useState(false);
  const { addToast } = useToastCtx();

  const liste = sessions || [];
  const nbAutres = liste.filter((s) => !s.is_current).length;

  const handleRevoke = async () => {
    setRevoking(true);
    try {
      const { data } = await api.delete('/admin/sessions/others');
      setShowConfirm(false);
      addToast?.(data?.message || 'Les autres appareils ont été déconnectés.', 'success');
      await refetch();
    } catch (err) {
      addToast?.(
        err.response?.data?.message || 'Erreur lors de la déconnexion des autres appareils',
        'error'
      );
    } finally {
      setRevoking(false);
    }
  };

  return (
    <div className="bg-surface-container-lowest rounded-xxl p-6 shadow-sm border border-outline-variant/5">
      {/* En-tête */}
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <div className="p-2.5 bg-primary/5 rounded-xl">
          <FiMonitor className="text-primary" size={20} aria-hidden="true" />
        </div>
        <div className="flex-1 min-w-0">
          <h2 className="text-base font-bold font-headline text-primary">Sessions actives</h2>
          <p className="text-xs text-on-surface-variant mt-0.5">
            Les appareils actuellement connectés à votre compte.
          </p>
        </div>
        <Button
          variant="destructive"
          size="sm"
          onClick={() => setShowConfirm(true)}
          disabled={loading || nbAutres === 0}
          className="rounded-xl font-semibold"
        >
          <FiLogOut size={16} aria-hidden="true" />
          Déconnecter les autres appareils
        </Button>
      </div>

      {/* Contenu : chargement / erreur / vide / liste */}
      {loading ? (
        <div role="status" aria-live="polite" aria-label="Chargement des sessions actives">
          <LoadingSkeleton rows={3} cols={3} />
        </div>
      ) : error ? (
        <div className="p-4 bg-error/10 rounded-xl">
          <p className="flex items-center gap-2 text-sm text-error">
            <FiAlertTriangle size={16} aria-hidden="true" />
            {error}
          </p>
          <Button variant="outline" size="sm" onClick={() => refetch()} className="mt-3 rounded-xl font-semibold">
            <FiRefreshCw size={14} aria-hidden="true" />
            Réessayer
          </Button>
        </div>
      ) : liste.length === 0 ? (
        <EmptyState icon={FiMonitor} message="Aucune session active à afficher." />
      ) : (
        <ul className="space-y-3">
          {liste.map((session) => (
            <SessionRow key={session.id} session={session} />
          ))}
        </ul>
      )}

      {/* Confirmation explicite avant révocation */}
      <Modal
        isOpen={showConfirm}
        onClose={() => setShowConfirm(false)}
        title="Déconnecter les autres appareils"
        size="sm"
        aria-describedby="revoke-sessions-description"
      >
        <div className="space-y-5">
          <div className="flex items-start gap-3 p-3 bg-error/10 rounded-xl">
            <FiAlertTriangle className="text-error shrink-0 mt-0.5" size={18} aria-hidden="true" />
            <p id="revoke-sessions-description" className="text-sm text-error">
              {nbAutres === 1
                ? '1 autre appareil sera déconnecté immédiatement et devra se reconnecter.'
                : `${nbAutres} autres appareils seront déconnectés immédiatement et devront se reconnecter.`}
            </p>
          </div>
          <p className="text-sm text-on-surface-variant">
            Votre session actuelle reste ouverte. Cette action est irréversible.
          </p>
          <div className="flex gap-3">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setShowConfirm(false)}
              className="flex-1 rounded-xl font-semibold"
            >
              Annuler
            </Button>
            <Button
              variant="destructive"
              size="sm"
              loading={revoking}
              onClick={handleRevoke}
              className="flex-1 rounded-xl font-semibold"
            >
              {revoking ? 'Déconnexion...' : 'Confirmer'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}
