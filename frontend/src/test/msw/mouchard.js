import { server } from './server';

/**
 * Mouchard de requetes : enregistre chaque requete sortante avec son corps
 * deja lu, pour pouvoir asserter dessus apres coup.
 *
 * Le corps d'une Request n'est lisible qu'une fois ; on le clone donc a la
 * volee, sinon MSW ne pourrait plus le transmettre au gestionnaire.
 */
export function installerMouchard() {
  const requetes = [];

  const surRequete = async ({ request }) => {
    let corps = null;
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      try {
        corps = await request.clone().json();
      } catch {
        corps = null;
      }
    }
    const url = new URL(request.url);
    requetes.push({
      methode: request.method,
      chemin: url.pathname,
      parametres: Object.fromEntries(url.searchParams),
      corps,
      autorisation: request.headers.get('Authorization'),
    });
  };

  server.events.on('request:start', surRequete);

  return {
    requetes,
    /** Requetes correspondant a une methode et un fragment de chemin. */
    filtrer: (methode, fragment) =>
      requetes.filter((r) => r.methode === methode && r.chemin.includes(fragment)),
    vider: () => { requetes.length = 0; },
    desinstaller: () => server.events.removeListener('request:start', surRequete),
  };
}
