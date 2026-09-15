export function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
  });
}

export function formatDateTime(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

export function formatTime(date) {
  if (!date) return '';
  return new Date(date).toLocaleTimeString('fr-FR', {
    hour: '2-digit', minute: '2-digit',
  });
}

export function formatPercentage(value) {
  if (value == null) return '—';
  return `${Math.round(value)}%`;
}

export function formatNumber(value) {
  if (value == null) return '—';
  return value.toLocaleString('fr-FR');
}

/**
 * Date du jour en heure LOCALE, au format AAAA-MM-JJ attendu par
 * <input type="date">.
 *
 * Pas `new Date().toISOString().slice(0, 10)` : cela donne la date UTC, soit
 * la veille entre minuit et 1 h à Cotonou — le sélecteur aurait alors laissé
 * choisir hier.
 */
export function aujourdhuiIso() {
  const d = new Date();
  const deux = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${deux(d.getMonth() + 1)}-${deux(d.getDate())}`;
}
