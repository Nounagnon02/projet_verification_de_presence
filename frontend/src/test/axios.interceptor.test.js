import { describe, it, expect, beforeEach, vi } from 'vitest';

/**
 * Intercepteur de réponse d'axios.js.
 *
 * Régression visée : un super admin sans 2FA reçoit désormais un 403
 * { code: 'two_factor_setup_required' } (RequireTwoFactorForSuperAdmin) sur
 * les routes /super-admin/*. Il reste authentifié — l'intercepteur ne doit
 * PAS le déconnecter comme un 401, il doit le renvoyer vers l'écran où
 * activer la 2FA.
 */
describe('intercepteur axios — réponse', () => {
  beforeEach(() => {
    vi.resetModules();
    localStorage.clear();
    delete window.location;
    window.location = { href: '' };
  });

  it("un 403 two_factor_setup_required redirige vers /profile, sans purger le jeton", async () => {
    localStorage.setItem('auth_token', 'jeton-super-admin');

    const { default: api } = await import('../api/axios');
    const gestionnaireErreur = api.interceptors.response.handlers[0].rejected;

    await gestionnaireErreur({
      response: { status: 403, data: { code: 'two_factor_setup_required' } },
    }).catch(() => {});

    expect(window.location.href).toBe('/profile?securite=requise');
    expect(localStorage.getItem('auth_token')).toBe('jeton-super-admin');
  });

  it('un 401 purge le jeton et redirige vers /login', async () => {
    localStorage.setItem('auth_token', 'jeton-expire');

    const { default: api } = await import('../api/axios');
    const gestionnaireErreur = api.interceptors.response.handlers[0].rejected;

    await gestionnaireErreur({ response: { status: 401 } }).catch(() => {});

    expect(window.location.href).toBe('/login');
    expect(localStorage.getItem('auth_token')).toBeNull();
  });

  it('un 403 générique (autre code) ne redirige nulle part', async () => {
    localStorage.setItem('auth_token', 'jeton-valide');

    const { default: api } = await import('../api/axios');
    const gestionnaireErreur = api.interceptors.response.handlers[0].rejected;

    await gestionnaireErreur({ response: { status: 403, data: { message: 'Interdit.' } } }).catch(() => {});

    expect(window.location.href).toBe('');
    expect(localStorage.getItem('auth_token')).toBe('jeton-valide');
  });
});
