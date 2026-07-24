import { defineConfig } from '@playwright/test';
import * as dotenv from 'dotenv';
import * as path from 'path';

// Charger le .env.test
dotenv.config({ path: path.resolve(__dirname, '.env.test') });

export default defineConfig({
  testDir: '.',
  timeout: 30000,
  expect: {
    timeout: 10000,
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1, // Un seul worker pour éviter les conflits DB
  globalSetup: './global-setup.ts',
  reporter: [
    ['list'],           // Affichage dans le terminal
    ['html', { open: 'never' }], // Rapport HTML
  ],
  use: {
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'api-production',
      testMatch: 'api/*.spec.ts',
      use: {
        baseURL: process.env.PROD_API_URL || 'https://presence-uac-api.onrender.com/api',
        extraHTTPHeaders: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        // Pas de browser pour les tests API
        browserName: undefined,
      },
    },
    {
      name: 'api-local',
      testMatch: 'api/*.spec.ts',
      use: {
        baseURL: process.env.LOCAL_API_URL || 'http://localhost:8000/api',
        extraHTTPHeaders: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      },
    },
    {
      name: 'frontend-production',
      testMatch: 'frontend/*.spec.ts',
      use: {
        baseURL: process.env.PROD_FRONTEND_URL || 'https://presence-uac.vercel.app',
        headless: true,
        viewport: { width: 1920, height: 1080 },
        browserName: 'chromium',
        channel: 'chrome',
      },
    },
    {
      name: 'frontend-local',
      testMatch: 'frontend/*.spec.ts',
      use: {
        baseURL: process.env.LOCAL_FRONTEND_URL || 'http://localhost:5173',
        headless: true,
        viewport: { width: 1920, height: 1080 },
        browserName: 'chromium',
      },
    },
  ],
});
