/**
 * Gestion centralisée du token admin pour éviter le rate limiting
 * Stocke le token dans un fichier temporaire partagé entre les tests
 */
import * as fs from 'fs';
import * as path from 'path';
import { APIRequestContext } from '@playwright/test';

const TOKEN_FILE = path.resolve(__dirname, '../.admin-token.json');

interface StoredToken {
  token: string;
  baseURL: string;
  expiresAt: number;
}

export function getStoredToken(baseURL: string): string | null {
  try {
    if (!fs.existsSync(TOKEN_FILE)) return null;
    const stored: StoredToken = JSON.parse(fs.readFileSync(TOKEN_FILE, 'utf-8'));
    if (stored.baseURL !== baseURL || Date.now() > stored.expiresAt) {
      fs.unlinkSync(TOKEN_FILE);
      return null;
    }
    return stored.token;
  } catch {
    return null;
  }
}

export function storeToken(token: string, baseURL: string): void {
  const data: StoredToken = {
    token,
    baseURL,
    expiresAt: Date.now() + 55 * 60 * 1000, // 55 minutes (les tokens Sanctum durent 1h)
  };
  fs.writeFileSync(TOKEN_FILE, JSON.stringify(data));
}

export function clearStoredToken(): void {
  try {
    if (fs.existsSync(TOKEN_FILE)) fs.unlinkSync(TOKEN_FILE);
  } catch {}
}

/**
 * Obtient un token admin (depuis le cache si disponible)
 */
export async function getAdminToken(
  request: APIRequestContext,
  baseURL: string,
): Promise<string> {
  const cached = getStoredToken(baseURL);
  if (cached) return cached;

  const email = process.env.PROD_ADMIN_EMAIL || 'admin@presence.uac.bj';
  const password = process.env.PROD_ADMIN_PASSWORD || 'admin123';

  const res = await request.post(`${baseURL}/login`, {
    data: { email, password, group: 'admin' },
    headers: { 'Accept': 'application/json' },
  });

  const body = await res.json();
  if (!res.ok() || !body.success) {
    throw new Error(
      `Admin login failed (${res.status()}): ${body.message || JSON.stringify(body)}`,
    );
  }

  storeToken(body.data.token, baseURL);
  return body.data.token;
}

/**
 * Nettoie le token après les tests
 */
export async function cleanupToken(
  request: APIRequestContext,
  baseURL: string,
): Promise<void> {
  try {
    const token = getStoredToken(baseURL);
    if (token) {
      await request.post(`${baseURL}/logout`, {
        headers: { Authorization: `Bearer ${token}` },
      });
    }
  } catch {} finally {
    clearStoredToken();
  }
}
