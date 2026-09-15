import { couleurTaux } from '../../utils/taux';

/**
 * Barre horizontale d'un taux sur 0–100 %, colorée selon son niveau.
 * Décorative : le taux est toujours écrit à côté.
 */
export default function BarreTaux({ taux, className = '' }) {
  const largeur = Math.max(0, Math.min(100, taux ?? 0));

  return (
    <div className={`h-2 rounded-full bg-surface-container-high overflow-hidden ${className}`} aria-hidden="true">
      <div
        className="h-full rounded-full"
        style={{ width: `${largeur}%`, minWidth: taux > 0 ? 3 : 0, backgroundColor: couleurTaux(taux) }}
      />
    </div>
  );
}
