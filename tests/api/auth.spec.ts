import { test, expect } from '@playwright/test';
import { studentLogin } from '../helpers/api';
import { getAdminToken } from '../helpers/auth-shared';

/**
 * Tests d'authentification — ADMIN
 */
test.describe('Authentification Admin', () => {

  test('POST /login — connexion admin avec identifiants valides', async ({ request, baseURL }) => {
    const res = await request.post(`${baseURL}/login`, {
      data: {
        email: process.env.PROD_ADMIN_EMAIL || 'admin@presence.uac.bj',
        password: process.env.PROD_ADMIN_PASSWORD || 'admin123',
        group: 'admin',
      },
    });

    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.success).toBe(true);
    expect(body.data.token).toBeDefined();
    expect(body.data.user.email).toBe(process.env.PROD_ADMIN_EMAIL || 'admin@presence.uac.bj');
  });

  test('POST /login — rejette les mauvais identifiants', async ({ request, baseURL }) => {
    const res = await request.post(`${baseURL}/login`, {
      data: {
        email: 'admin@presence.uac.bj',
        password: 'wrongpassword',
        group: 'admin',
      },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.success).toBe(false);
  });

  test('POST /login — rejette les emails inconnus', async ({ request, baseURL }) => {
    const res = await request.post(`${baseURL}/login`, {
      data: {
        email: 'inconnu@test.com',
        password: 'admin123',
        group: 'admin',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('GET /user — récupère l\'utilisateur connecté', async ({ request, baseURL }) => {
    const token = await getAdminToken(request, baseURL!);
    const res = await request.get(`${baseURL}/user`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(res.ok()).toBeTruthy();
  });

  test('POST /logout — déconnexion révocable', async ({ request, baseURL }) => {
    // Session dédiée pour le test de logout
    const res = await request.post(`${baseURL}/login`, {
      data: {
        email: process.env.PROD_ADMIN_EMAIL || 'admin@presence.uac.bj',
        password: process.env.PROD_ADMIN_PASSWORD || 'admin123',
        group: 'admin',
      },
    });
    const auth = await res.json();

    const logoutRes = await request.post(`${baseURL}/logout`, {
      headers: { Authorization: `Bearer ${auth.data.token}` },
    });
    expect(logoutRes.ok()).toBeTruthy();

    const userRes = await request.get(`${baseURL}/user`, {
      headers: { Authorization: `Bearer ${auth.data.token}` },
    });
    expect(userRes.status()).toBe(401);
  });

});

/**
 * Tests d'authentification — ÉTUDIANT (mobile)
 */
test.describe('Authentification Étudiant (Mobile)', () => {
  let student: any;
  let adminToken: string;

  test.beforeAll(async ({ request, baseURL }) => {
    adminToken = await getAdminToken(request, baseURL!);
    const studentsRes = await request.get(`${baseURL}/admin/students?per_page=1`, {
      headers: { Authorization: `Bearer ${adminToken}` },
    });
    const studentsBody = await studentsRes.json();
    if (studentsBody.data?.length) {
      student = studentsBody.data[0];
    }
  });

  test('POST /auth/student/login — connexion étudiant valide', async ({ request, baseURL }) => {
    if (!student) {
      test.skip();
      return;
    }

    const loginRes = await request.post(`${baseURL}/auth/student/login`, {
      data: {
        email: student.email,
        identifiant_unique: student.identifiant_unique,
      },
    });

    const body = await loginRes.json();
    expect(loginRes.ok()).toBeTruthy();
    expect(body.success).toBe(true);
    expect(body.data.token).toBeDefined();
    expect(body.data.user.email).toBe(student.email);
    expect(body.data.user.role).toBe('etudiant');
  });

  test('POST /auth/student/login — rejette les mauvais identifiants', async ({ request, baseURL }) => {
    const res = await request.post(`${baseURL}/auth/student/login`, {
      data: {
        email: 'etudiant@test.com',
        identifiant_unique: 'WRONG_ID',
      },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.success).toBe(false);
  });

  test('GET /auth/student/me — récupère le profil étudiant connecté', async ({ request, baseURL }) => {
    if (!student) {
      test.skip();
      return;
    }

    const { token } = await studentLogin(request, baseURL!, student.email, student.identifiant_unique);

    const meRes = await request.get(`${baseURL}/auth/student/me`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(meRes.ok()).toBeTruthy();

    const meBody = await meRes.json();
    expect(meBody.success).toBe(true);
    expect(meBody.data.email).toBe(student.email);
  });

  test('POST /auth/student/logout — déconnexion étudiant', async ({ request, baseURL }) => {
    if (!student) {
      test.skip();
      return;
    }

    const { token } = await studentLogin(request, baseURL!, student.email, student.identifiant_unique);

    const logoutRes = await request.post(`${baseURL}/auth/student/logout`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    expect(logoutRes.ok()).toBeTruthy();
  });

});
