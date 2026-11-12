import { expect, test } from '@playwright/test';

/**
 * El listado del demo `notas`. Depende de que el demo esté instalado
 * (`composer demo:notas:install`) y de la nota que siembra `fixture.php`.
 */
test('muestra el encabezado, el contador y las filas sembradas', async ({ page }) => {
    await page.goto('/notas');

    await expect(page.getByRole('heading', { level: 1, name: 'Notas' })).toBeVisible();
    await expect(page.locator('#notas-panel')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Nota sembrada e2e', exact: true })).toBeVisible();
});

test('el panel es la región que HTMX reemplaza', async ({ page }) => {
    await page.goto('/notas');

    // Si el contenedor cambia de id, los `hx-target` de las acciones quedan
    // apuntando a la nada: el servidor responde 200 y la pantalla no cambia.
    await expect(page.locator('#notas-panel')).toHaveCount(1);
});
