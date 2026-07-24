/**
 * Setup global Playwright — exécuté avant tous les tests
 */
import { clearStoredToken } from './helpers/auth-shared';

export default async function globalSetup() {
  // Nettoie le cache de token admin pour repartir à zéro
  clearStoredToken();
  console.log('🧹 Cache de token admin nettoyé');
}
