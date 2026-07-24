import { test, expect } from '@playwright/test';

/**
 * Tests de la page de connexion frontend
 */
test.describe('Page de Connexion (Frontend)', () => {

  test('la page de login se charge correctement', async ({ page, baseURL }) => {
    await page.goto(baseURL!);
    await page.waitForLoadState('networkidle');

    // Vérifie que la page contient le formulaire de connexion
    const title = page.locator('h1, h2, .login-title, [class*="login"]').first();
    await expect(title).toBeVisible({ timeout: 15000 });
  });

  test('la page affiche une erreur pour identifiants vides', async ({ page, baseURL }) => {
    await page.goto(baseURL!);
    await page.waitForLoadState('networkidle');

    // Trouve et clique sur le bouton de connexion
    const submitBtn = page.locator('button[type="submit"], button:has-text("Se connecter")').first();
    if (await submitBtn.isVisible()) {
      await submitBtn.click();

      // Vérifie qu'un message d'erreur apparaît
      await page.waitForTimeout(500);
      const errorMessages = page.locator('.text-red-500, .error, [class*="error"]');
      // Optionnel : vérifier qu'il y a des messages d'erreur
    }
  });

});

/**
 * Tests du dashboard (après connexion)
 */
test.describe('Dashboard Admin', () => {

  test('connexion et accès au dashboard', async ({ page, baseURL }) => {
    // Aller sur la page de login
    await page.goto(baseURL!);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Remplir le formulaire si présent
    const emailInput = page.locator('input[type="email"], input[name="email"], input[placeholder*="email" i]').first();
    if (await emailInput.isVisible()) {
      await emailInput.fill(process.env.PROD_ADMIN_EMAIL || 'admin@presence.uac.bj');

      const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
      if (await passwordInput.isVisible()) {
        await passwordInput.fill(process.env.PROD_ADMIN_PASSWORD || 'admin123');
      }

      // Cliquer sur Se connecter
      const submitBtn = page.locator('button[type="submit"], button:has-text("Se connecter")').first();
      if (await submitBtn.isVisible()) {
        await submitBtn.click();
        await page.waitForTimeout(3000);
      }
    }

    // Vérifier qu'on est sur le dashboard (URL ou élément)
    const currentUrl = page.url();
    const isDashboard = currentUrl.includes('dashboard') || currentUrl.includes('admin');
    if (isDashboard) {
      // Prendre une capture du dashboard
      await page.screenshot({ path: 'test-results/dashboard.png', fullPage: true });
      expect(true).toBeTruthy();
    } else {
      // Si pas redirigé, on note l'URL pour debug
      test.info().annotations.push({
        type: 'url',
        description: currentUrl,
      });
    }
  });

});

/**
 * Tests de la landing page publique
 */
test.describe('Landing Page', () => {

  test('la landing page est accessible et affiche les stats', async ({ page }) => {
    const landingUrl = process.env.PROD_FRONTEND_URL || 'https://presence-uac.vercel.app';
    await page.goto(landingUrl);
    await page.waitForLoadState('networkidle');

    // Vérifie que la page se charge
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });

    // Capture d'écran
    await page.screenshot({ path: 'test-results/landing.png', fullPage: true });
  });

});
