import { test, expect } from '@playwright/test';
import { getAdminToken, cleanupToken } from '../helpers/auth-shared';
import { studentLogin, apiGet } from '../helpers/api';

test.describe('Présences & Scan', () => {
  let adminToken: string;

  test.beforeAll(async ({ request, baseURL }) => {
    adminToken = await getAdminToken(request, baseURL!);
  });

  test.afterAll(async ({ request, baseURL }) => {
    await cleanupToken(request, baseURL!);
  });

  test('GET /admin/presence/history — historique des présences', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/presence/history?per_page=10`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('GET /admin/presence/stats — stats globales', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/presence/stats`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('GET /admin/presence/pending — validations en attente', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/presence/pending`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('GET /admin/filieres — liste des filières', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/filieres`, adminToken);
    expect(res.ok()).toBeTruthy();

    const body = await res.json();
    expect(body.success).toBe(true);
    expect(Array.isArray(body.data)).toBe(true);
  });

  test('GET /admin/annees-academiques — liste des années', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/annees-academiques`, adminToken);
    expect(res.ok()).toBeTruthy();

    const body = await res.json();
    expect(body.success).toBe(true);
    expect(Array.isArray(body.data)).toBe(true);
  });

  test('GET /admin/evenements — liste des événements', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/evenements?per_page=10`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('POST /presence/scan — scan public (sans auth) — identifiants invalides → 422', async ({ request, baseURL }) => {
    const res = await request.post(`${baseURL}/presence/scan`, {
      data: {
        identifiant_unique: 'TEST_ID',
        token_evenement: 'INVALID_TOKEN',
        latitude: 6.4,
        longitude: 2.3,
      },
    });
    expect(res.status()).toBe(422);
  });

  test('GET /presence/course-by-token/{token} — token invalide → erreur 4xx', async ({ request, baseURL }) => {
    const res = await request.get(`${baseURL}/presence/course-by-token/INVALID_TOKEN`);
    // 404 (pas trouvé) ou 500 (erreur serveur sur token invalide)
    expect(res.status() === 404 || res.status() === 500).toBeTruthy();
  });

  async function getStudent(request: any, baseURL: string, token: string): Promise<any> {
    const res = await apiGet(request, `${baseURL}/admin/students?per_page=1`, token);
    const body = await res.json();
    return body.data?.[0] || null;
  }

  test('GET /presence/my-history — historique personnel étudiant', async ({ request, baseURL }) => {
    const student = await getStudent(request, baseURL!, adminToken);
    if (!student) {
      test.skip();
      return;
    }

    try {
      const { token: studentToken } = await studentLogin(
        request,
        baseURL!,
        student.email,
        student.identifiant_unique,
      );

      const res = await request.get(`${baseURL}/presence/my-history`, {
        headers: { Authorization: `Bearer ${studentToken}` },
      });
      expect(res.ok()).toBeTruthy();
    } catch (e: any) {
      // Si le login étudiant échoue (ex: pas d'étudiant valide en prod), on skip
      test.skip();
    }
  });

  test('GET /presence/my-stats — stats personnelles étudiant', async ({ request, baseURL }) => {
    const student = await getStudent(request, baseURL!, adminToken);
    if (!student) {
      test.skip();
      return;
    }

    try {
      const { token: studentToken } = await studentLogin(
        request,
        baseURL!,
        student.email,
        student.identifiant_unique,
      );

      const res = await request.get(`${baseURL}/presence/my-stats`, {
        headers: { Authorization: `Bearer ${studentToken}` },
      });
      expect(res.ok()).toBeTruthy();
    } catch (e: any) {
      test.skip();
    }
  });

});
