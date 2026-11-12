import { expect, test } from '@playwright/test';

/**
 * El alta: modal nativo, validación del navegador y aviso por toast.
 *
 * Es el spec que más cubre con menos código: prueba que Alpine cargó (el toast
 * lo renderiza Alpine), que el `<dialog>` abre, que el formulario tiene CSRF
 * válido y que la fila nueva aparece en el panel.
 */
test('abre el modal, guarda una nota y la muestra en la lista', async ({ page }) => {
    const titulo = `Nota e2e ${Date.now()}`;

    await page.goto('/notas');

    await page.getByRole('button', { name: 'Agregar nota' }).click();

    const modal = page.locator('#nota-create-modal');

    await expect(modal).toBeVisible();

    await modal.getByLabel('Título').fill(titulo);
    await modal.getByRole('button', { name: 'Crear nota' }).click();

    await expect(page.getByRole('link', { name: titulo, exact: true })).toBeVisible();
    await expect(page.getByText('Nota creada.')).toBeVisible();
});

test('no envía el formulario sin título', async ({ page }) => {
    await page.goto('/notas');

    await page.getByRole('button', { name: 'Agregar nota' }).click();
    await page.locator('#nota-create-modal').getByRole('button', { name: 'Crear nota' }).click();

    // El campo es `required`: el navegador frena el submit y el modal sigue abierto.
    await expect(page.locator('#nota-create-modal')).toBeVisible();
    await expect(page).toHaveURL(/\/notas$/);
});
