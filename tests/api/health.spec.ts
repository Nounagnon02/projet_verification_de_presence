import { test, expect } from '@playwright/test';

/**
 * Tests de santé de l'API (endpoints publics)
 */
test.describe('API Health & Public Endpoints', () => {

  test('GET /health — le service est opérationnel', async ({ request, baseURL }) => {
    const res = await request.get(`${baseURL}/health`);
    expect(res.ok()).toBeTruthy();

    const body = await res.json();
    expect(body.success).toBe(true);
    expect(body.status).toBe('healthy');
  });

  test('GET /health — ne révèle ni composants, ni version, ni heure', async ({ request, baseURL }) => {
    const res = await request.get(`${baseURL}/health`);
    const body = await res.json();

    expect(Object.keys(body).sort()).toEqual(['status', 'success']);
  });

  test('GET /landing/stats — les stats publiques sont accessibles', async ({ request, baseURL }) => {
    const res = await request.get(`${baseURL}/landing/stats`);
    expect(res.ok()).toBeTruthy();

    const body = await res.json();
    expect(body.success).toBe(true);
    // Vérifie que les clés de stats existent
    expect(body.data).toBeDefined();
  });

  // L'interface Swagger UI (/docs) n'est servie que hors production ; la
  // spécification, elle, l'est partout.
  test('GET /docs/json — la spécification OpenAPI est accessible', async ({ request, baseURL }) => {
    const res = await request.get(`${baseURL}/docs/json`);
    expect(res.ok()).toBeTruthy();

    const spec = await res.json();
    expect(spec.openapi).toBeDefined();
    expect(spec.paths['/health']).toBeDefined();
  });

});
