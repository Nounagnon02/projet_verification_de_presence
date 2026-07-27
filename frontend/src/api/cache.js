/**
 * Cache mémoire partagé des réponses GET.
 *
 * Isolé dans son propre module pour que `axios.js` (qui l'invalide après
 * chaque écriture) et `useApi.js` (qui le lit) puissent tous deux l'importer
 * sans dépendance circulaire.
 */
export const cache = new Map(); // clé -> { data, pagination, at }
export const inFlight = new Map(); // clé -> Promise, déduplication des appels simultanés

/** Durées de fraîcheur par préfixe d'URL. Une URL absente n'est pas cachée. */
const TTLS = [
  ['/admin/annees-academiques', 5 * 60 * 1000],
  ['/admin/filieres', 5 * 60 * 1000],
  ['/admin/salles', 5 * 60 * 1000],
  ['/admin/ues', 2 * 60 * 1000],
  ['/admin/ecs', 2 * 60 * 1000],
];

export function ttlFor(url) {
  const match = TTLS.find(([prefix]) => url === prefix || url.startsWith(prefix + '?'));
  return match ? match[1] : 0;
}

/**
 * Vide le cache.
 *
 * @param {string} [prefix] Ne vide que les entrées commençant par ce préfixe.
 */
export function invalidateApiCache(prefix) {
  if (!prefix) {
    cache.clear();
    return;
  }
  for (const key of cache.keys()) {
    if (key.startsWith(prefix)) cache.delete(key);
  }
}
