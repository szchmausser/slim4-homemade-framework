# Pruebas de navegador

Pruebas de comportamiento real en Chromium, con Playwright. Son **dev-only**: no
forman parte del runtime de la app, no entran en `composer verify` y no agregan
Node a la aplicación. Existen porque hay regresiones que los tests PHP no pueden
ver —un `<dialog>` que abre un clic y no otro, un `hx-target` que apunta a un id
que ya no existe, un toast que Alpine nunca renderiza—.

## Qué cubren y qué no

| Cubren | No cubren |
|---|---|
| login real con cookie de sesión de PHP | que Tailwind se vea bien |
| modal nativo: abrir, validar, guardar | layout, espaciado, foco visual |
| fila nueva en la lista y toast de Alpine | detalles estéticos |
| `#notas-panel` como región de swap de HTMX | |

Lo visual se revisa a ojo en el navegador del teléfono. Esto no reemplaza eso.

## Requisitos

- PHP con las mismas extensiones que la app.
- Node 20 o superior.
- El demo `notas` instalado y la base preparada:

```bash
composer demo:notas:install
php tests/browser/fixture.php
```

`fixture.php` es idempotente: crea el usuario `e2e@ejemplo.com` / `secreto123`
(override con `E2E_EMAIL` y `E2E_PASSWORD`) y la nota fija del spec del listado.

## Correr

```bash
cd tests/browser
npm ci
npx playwright install chromium     # una vez por máquina, ~120 MB
npx playwright test
```

Playwright levanta `php -S 127.0.0.1:8090 -t public` por su cuenta y lo baja al
terminar. Para apuntar a un servidor ya levantado: `E2E_PORT=8080` y
`reuseExistingServer` entra en juego fuera de CI.

## Por qué no corre en Termux 32 bits

No existe Chromium para `linux-arm`: ni Playwright ni Chrome for Testing publican
binarios para armv7l. Termux sí tiene `nodejs`, pero el que falta es el navegador.
Por eso la suite corre en CI (ubuntu x64) y en cualquier máquina con Node, y no
en el teléfono.

Flujo desde Termux: `composer verify` (backend, 26 s) → push → `gh run watch`.

## Artefactos

- `test-results/` — screenshot y `error-context.md` de cada fallo.
- `playwright-report/` — reporte HTML con el trace.
- En CI se suben como `playwright-failure` cuando el job falla.
