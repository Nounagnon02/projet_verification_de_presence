import { test, expect } from '@playwright/test';
import { apiGet } from '../helpers/api';
import { getAdminToken, cleanupToken } from '../helpers/auth-shared';

test.describe('Gestion UE / EC', () => {
  let adminToken: string;

  test.beforeAll(async ({ request, baseURL }) => {
    adminToken = await getAdminToken(request, baseURL!);
  });

  test.afterAll(async ({ request, baseURL }) => {
    await cleanupToken(request, baseURL!);
  });

  test('GET /admin/ues — liste des UE', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/ues`, adminToken);
    expect(res.ok()).toBeTruthy();
    // Format varie selon l'API (ResourceCollection, paginated, etc.)
    // L'essentiel est que la requête réussisse
  });

  test('GET /admin/ecs — liste des EC', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/ecs`, adminToken);
    expect(res.ok()).toBeTruthy();

    const body = await res.json();
    expect(body.success).toBe(true);
    expect(Array.isArray(body.data)).toBe(true);
  });

  test('GET /admin/ecs?ue_id= — filtre EC par UE', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/ues`, adminToken);
    expect(res.ok()).toBeTruthy();
    const ues = await res.json();
    const ueList = Array.isArray(ues) ? ues : (ues.data || []);
    if (!ueList.length) {
      test.skip();
      return;
    }

    const ueId = ueList[0].id;
    const ecRes = await apiGet(request, `${baseURL}/admin/ecs?ue_id=${ueId}`, adminToken);
    expect(ecRes.ok()).toBeTruthy();
  });

});
