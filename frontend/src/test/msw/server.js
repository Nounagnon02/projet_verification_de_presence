import { setupServer } from 'msw/node';

/**
 * Serveur MSW pour les tests d'integration de page.
 *
 * Pourquoi MSW et pas vi.mock('../api/axios') : le double d'axios repond
 * toujours { success: true, data: [] } sans regarder ce qu'on lui envoie. Il
 * prouve qu'une page monte, jamais CE QU'ELLE ENVOIE. C'est cet angle mort qui
 * a laisse passer trois defauts en production :
 *
 *   - la page de validation ne transmettait pas scan_challenge (422 systematique)
 *   - le defi transmis etait calcule cote client et donc toujours refuse (403)
 *   - SallesPage appelait /admin/etablissements, route inexistante (404 avale)
 *
 * MSW intercepte au niveau HTTP : methode, URL, parametres de requete et CORPS
 * sont observables et donc assertables.
 */
export const server = setupServer();
