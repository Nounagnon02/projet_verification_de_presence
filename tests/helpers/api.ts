import { APIRequestContext } from '@playwright/test';

/**
 * Connexion admin : récupère un token Sanctum via POST /login
 */
export async function adminLogin(
  request: APIRequestContext,
  baseURL: string,
  email: string,
  password: string,
): Promise<{ token: string; user: any }> {
  const res = await request.post(`${baseURL}/login`, {
    data: { email, password, group: 'admin' },
  });

  const body = await res.json();
  if (!res.ok() || !body.success) {
    throw new Error(
      `Admin login failed (${res.status()}): ${body.message || JSON.stringify(body)}`,
    );
  }

  return {
    token: body.data.token,
    user: body.data.user,
  };
}

/**
 * Connexion étudiant : POST /auth/student/login
 */
export async function studentLogin(
  request: APIRequestContext,
  baseURL: string,
  email: string,
  identifiantUnique: string,
): Promise<{ token: string; user: any }> {
  const res = await request.post(`${baseURL}/auth/student/login`, {
    data: { email, identifiant_unique: identifiantUnique },
  });

  const body = await res.json();
  if (!res.ok() || !body.success) {
    throw new Error(
      `Student login failed (${res.status()}): ${body.message || JSON.stringify(body)}`,
    );
  }

  return {
    token: body.data.token,
    user: body.data.user,
  };
}

/**
 * Effectue une requête API authentifiée
 */
export async function apiGet(
  request: APIRequestContext,
  url: string,
  token: string,
) {
  return request.get(url, {
    headers: { Authorization: `Bearer ${token}` },
  });
}

export async function apiPost(
  request: APIRequestContext,
  url: string,
  token: string,
  data?: any,
) {
  return request.post(url, {
    headers: { Authorization: `Bearer ${token}` },
    data,
  });
}

export async function apiPut(
  request: APIRequestContext,
  url: string,
  token: string,
  data?: any,
) {
  return request.put(url, {
    headers: { Authorization: `Bearer ${token}` },
    data,
  });
}

export async function apiDelete(
  request: APIRequestContext,
  url: string,
  token: string,
) {
  return request.delete(url, {
    headers: { Authorization: `Bearer ${token}` },
  });
}

/**
 * Parse la réponse JSON
 */
export async function parseJson(res: any) {
  try {
    return await res.json();
  } catch {
    return { raw: await res.text() };
  }
}

/**
 * Vérifie une réponse API réussie
 */
export function expectSuccess(body: any) {
  if (!body.success) {
    throw new Error(`API returned error: ${body.message || JSON.stringify(body)}`);
  }
}
