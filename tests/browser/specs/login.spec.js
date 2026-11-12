import { expect, test } from '@playwright/test';

// Este spec arranca sin sesión: es el que prueba el login, no el que lo usa.
test.use({ storageState: { cookies: [], origins: [] } });

test('rechaza credenciales inválidas y no deja entrar', async ({ page }) => {
    await page.goto('/login');

    await page.locator('input[name="email"]').fill('e2e@ejemplo.com');
    await page.locator('input[name="password"]').fill('clave-que-no-es');
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page.getByText('Credenciales inválidas.')).toBeVisible();
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('button', { name: 'Cerrar sesión' })).toHaveCount(0);
});

test('con credenciales válidas entra a la portada', async ({ page }) => {
    await page.goto('/login');

    await page.locator('input[name="email"]').fill(process.env.E2E_EMAIL ?? 'e2e@ejemplo.com');
    await page.locator('input[name="password"]').fill(process.env.E2E_PASSWORD ?? 'secreto123');
    await page.getByRole('button', { name: 'Entrar' }).click();

    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('button', { name: 'Cerrar sesión' })).toBeVisible();
});

test('una ruta privada sin sesión te manda al login', async ({ page }) => {
    await page.goto('/notas');

    await expect(page).toHaveURL(/\/login$/);
});
