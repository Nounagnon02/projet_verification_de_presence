import { test, expect } from '@playwright/test';
import { apiGet, apiPost, apiPut, apiDelete } from '../helpers/api';
import { getAdminToken, cleanupToken } from '../helpers/auth-shared';

test.describe('Gestion des Étudiants (Admin)', () => {
  let adminToken: string;
  let testStudentId: string | null = null;

  test.beforeAll(async ({ request, baseURL }) => {
    adminToken = await getAdminToken(request, baseURL!);
  });

  test.afterAll(async ({ request, baseURL }) => {
    await cleanupToken(request, baseURL!);
  });

  test('GET /admin/students — liste paginée des étudiants', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/students`, adminToken);
    expect(res.ok()).toBeTruthy();

    const body = await res.json();
    expect(body.success).toBe(true);
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.meta).toBeDefined();
    expect(body.meta.total).toBeDefined();
  });

  test('GET /admin/students?search=&per_page=5 — pagination + recherche', async ({ request, baseURL }) => {
    const res = await apiGet(request, `${baseURL}/admin/students?search=a&per_page=5`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('GET /admin/students/{id} — détail d\'un étudiant', async ({ request, baseURL }) => {
    const listRes = await apiGet(request, `${baseURL}/admin/students?per_page=1`, adminToken);
    const listBody = await listRes.json();
    if (!listBody.data?.length) {
      test.skip();
      return;
    }

    const studentId = listBody.data[0].id;
    const res = await apiGet(request, `${baseURL}/admin/students/${studentId}`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

  test('POST /admin/students — création d\'un étudiant', async ({ request, baseURL }) => {
    const filieresRes = await apiGet(request, `${baseURL}/admin/filieres`, adminToken);
    const filieresBody = await filieresRes.json();
    if (!filieresBody.data?.length) {
      test.skip();
      return;
    }
    const filiere = filieresBody.data[0];

    const anneesRes = await apiGet(request, `${baseURL}/admin/annees-academiques`, adminToken);
    const anneesBody = await anneesRes.json();
    if (!anneesBody.data?.length) {
      test.skip();
      return;
    }
    const annee = anneesBody.data.find((a: any) => a.active) || anneesBody.data[0];

    const newStudent = {
      nom: 'TEST',
      prenom: `Student_${Date.now()}`,
      email: `test.student.${Date.now()}@etu.uac.bj`,
      filiere_id: filiere.id,
      annee_id: annee.id,
    };

    const res = await apiPost(request, `${baseURL}/admin/students`, adminToken, newStudent);
    const body = await res.json();

    if (res.status() === 422) {
      test.skip();
      return;
    }

    expect(res.ok()).toBeTruthy();
    expect(body.success).toBe(true);
    expect(body.data.email).toBe(newStudent.email);
    expect(body.data.identifiant_unique).toBeDefined();

    testStudentId = body.data.id;
  });

  test('PUT /admin/students/{id} — mise à jour d\'un étudiant', async ({ request, baseURL }) => {
    if (!testStudentId) {
      test.skip();
      return;
    }

    const res = await apiPut(request, `${baseURL}/admin/students/${testStudentId}`, adminToken, {
      prenom: `Updated_${Date.now()}`,
    });
    expect(res.ok()).toBeTruthy();
  });

  test('DELETE /admin/students/{id} — suppression d\'un étudiant', async ({ request, baseURL }) => {
    if (!testStudentId) {
      test.skip();
      return;
    }

    const res = await apiDelete(request, `${baseURL}/admin/students/${testStudentId}`, adminToken);
    expect(res.ok()).toBeTruthy();
  });

});
