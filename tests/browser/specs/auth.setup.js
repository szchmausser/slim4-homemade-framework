import { expect, test as setup } from '@playwright/test';

/**
 * Login único: el resto de los specs reusan la cookie de sesión (storageState).
 *
 * La sesión de la app es la nativa de PHP (`mi_app_session`), así que lo que se
 * guarda es esa cookie. Si el login falla, la falla aparece acá, una sola vez,
 * en vez de repetirse en cada spec como "redirigió a /login".
 */
const EMAIL = process.env.E2E_EMAIL ?? 'e2e@ejemplo.com';
const PASSWORD = process.env.E2E_PASSWORD ?? 'secreto123';

setup('inicia sesión y guarda el estado', async ({ page }) => {
    await page.goto('/login');

    await page.locator('input[name="email"]').fill(EMAIL);
    await page.locator('input[name="password"]').fill(PASSWORD);
    await page.getByRole('button', { name: 'Entrar' }).click();

    // La portada con la barra lateral solo existe autenticado; el botón de
    // salir es la señal de que la sesión quedó abierta.
    await expect(page.getByRole('button', { name: 'Cerrar sesión' })).toBeVisible();

    await page.context().storageState({ path: '.auth/user.json' });
});
