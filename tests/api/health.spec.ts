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
    expect(body.services).toBeDefined();
    expect(body.services.database).toBe('connected');
    expect(body.services.app).toBe('running');
  });

  test('GET /landing/stats — les stats publiques sont accessibles', async ({ request, baseURL }) => {
    const res = await request.get(`${baseURL}/landing/stats`);
    expect(res.ok()).toBeTruthy();

    const body = await res.json();
    expect(body.success).toBe(true);
    // Vérifie que les clés de stats existent
    expect(body.data).toBeDefined();
  });

  test('GET /docs — la documentation API est accessible', async ({ request, baseURL }) => {
    const res = await request.get(`${baseURL}/docs`);
    expect(res.ok()).toBeTruthy();

    const text = await res.text();
    // La doc peut être du HTML (Swagger UI) ou du JSON selon le moteur
    expect(
      text.includes('swagger') ||
      text.includes('openapi') ||
      text.includes('<!DOCTYPE html>') ||
      text.includes('success')
    ).toBeTruthy();
  });

});
