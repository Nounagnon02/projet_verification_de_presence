import { test, expect } from '@playwright/test';
import { apiGet } from '../helpers/api';
import { getAdminToken, cleanupToken } from '../helpers/auth-shared';

test.describe('Dashboard & Stats', () => {
  let adminToken: string;

  test.beforeAll(async ({ request, baseURL }) => {
    adminToken = await getAdminToken(request, baseURL!);
  });

  test.afterAll(async ({ request, baseURL }) => {
    await cleanupToken(request, baseURL!);
  });

  test('GET /admin/dashboard — les stats du dashboard', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/dashboard`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('GET /admin/dashboard/attendance-trend — tendance des présences', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/dashboard/attendance-trend`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('GET /admin/dashboard/top-absences — top absences', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/dashboard/top-absences`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('GET /admin/dashboard/today-events — événements du jour', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/dashboard/today-events`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

});
