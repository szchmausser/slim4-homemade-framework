import { defineConfig, devices } from '@playwright/test';

/**
 * Pruebas de navegador de la app y del demo `notas`.
 *
 * Corren contra `php -S` sobre la base real de desarrollo, así que el server lo
 * levanta Playwright (`webServer`) y la base la prepara `fixture.php`. Son
 * dev-only: no entran en `composer verify` ni en el runtime.
 *
 * `workers: 1` a propósito: una sola SQLite y un solo proceso PHP detrás. Con
 * paralelismo, dos specs que crean notas compiten por el mismo archivo y la
 * falla aparece como timeout, no como el problema real.
 */
const PORT = Number(process.env.E2E_PORT ?? 8090);
const baseURL = `http://127.0.0.1:${PORT}`;

export default defineConfig({
    testDir: './specs',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    timeout: 15_000,
    expect: { timeout: 5_000 },

    // `line` imprime el fallo con archivo, línea, esperado y recibido: es lo que
    // se lee desde Termux con `gh run view --log-failed`. El HTML y el trace
    // quedan como artefactos para cuando hay una computadora a mano.
    reporter: process.env.CI
        ? [['line'], ['html', { open: 'never' }]]
        : [['line']],

    use: {
        baseURL,
        locale: 'es-AR',
        timezoneId: 'America/Argentina/Buenos_Aires',
        viewport: { width: 1280, height: 800 },
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    webServer: {
        command: `php -S 127.0.0.1:${PORT} -t public`,
        cwd: '../..',
        url: `${baseURL}/login`,
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },

    projects: [
        // Inicia sesión una vez y guarda la cookie de sesión nativa de PHP.
        { name: 'setup', testMatch: /auth\.setup\.js/ },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], storageState: '.auth/user.json' },
            dependencies: ['setup'],
            testIgnore: /auth\.setup\.js/,
        },
    ],
});
