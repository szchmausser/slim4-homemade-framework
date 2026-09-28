# Slim 4 Homemade Framework

Esqueleto PHP en capas para apps server-rendered, pensado para correr en Termux (ARM 32-bit) sin toolchain de compilación.

**Stack:** Slim 4 · PHP-DI · Eloquent (standalone) · Phinx · Twig · HTMX · Alpine.js · daisyUI 5 (vendorizado) · SQLite · respect/validation · Monolog.

**Guía completa paso a paso:** [`docs/guia-stack-php-termux.md`](docs/guia-stack-php-termux.md) — instalación en Termux, cada archivo con código copiable, testing y troubleshooting.

## Arranque rápido

```bash
cp .env.example .env
composer install:termux     # en 64-bit: composer install
composer dump-autoload -o
composer migrate
ADMIN_EMAIL=vos@ejemplo.com ADMIN_PASSWORD=una-clave-larga vendor/bin/phinx seed:run -s DatabaseSeeder
composer serve              # http://localhost:8080
composer test               # suite verde: 65 tests
```

## Comandos

| Comando | Para qué |
|---|---|
| `composer serve` | Servidor dev en `:8080` |
| `composer migrate` | Migraciones pendientes |
| `vendor/bin/phinx rollback -t 0` | Baja todo (el "fresh") |
| `vendor/bin/phinx seed:run -s DatabaseSeeder` | Admin + demo en orden |
| `vendor/bin/phinx seed:run -s NombreSeed` | Un seed puntual |
| `composer db:backup` | Backup timestamped a `storage/backups/` |
| `composer test` | Suite completa |
| `composer check:termux` | 32 o 64 bits |

## Receta: nuevo CRUD en N pasos

Para parir `Recetas` (o lo que sea) desde `Tareas`, replicá el patrón:

1. **Migración.** `vendor/bin/phinx create CreateRecetasTable`, completala y **renombrala a secuencial** (`004_...`: los timestamps saturan int32, ver guía 11.1). `composer migrate`.
2. **Modelo** en `app/Models/` con `$fillable` (+ `$hidden` si hay secrets).
3. **Controlador** que extienda `Controller`: lo repetido a `viewDefaults()`, lo puntual por `$extra` en cada `render()`. Si lista, pagina con `Pagination::paginate()` (nunca `get()` pelado) y pasá `pagination` a la vista.
4. **Rutas** en `routes/web.php` (+ `->add(RequireAuthMiddleware::class)` si es protegida, `'active'` para la nav si tiene página completa).
5. **Vistas.** La página extiende `layouts/app.twig`. Si hay HTMX parcial: **una sola región** que devuelven tanto el full como el swap (como `_panel`), flash adentro del swap, tokens CSRF en cada form (el de edición lleva los suyos: vive dentro del swap), `hx-headers` (no `hx-include`) para DELETE, `hx-on::after-request="this.reset()"` si el form queda fuera del swap. Paginación con `partials/_pagination.twig` (params, sin hardcodear).
6. **Tests.** Cloná el estilo: request con body crudo + URI absoluta, `tearDown` que limpia, login inyectado si la ruta es protegida, y al menos: crea-OK, validación que rechaza, id inexistente que avisa (nunca 404 mudo ni 500 silencioso).
7. **Seed** si hace falta dato inicial: seeder idempotente + línea en `DatabaseSeeder::SEEDS` (nunca en el `seed:run` pelado por accidente: todo seeder debe tolerar re-ejecución).
8. **Verificá:** `composer test` en verde + smoke con `curl` (200 donde corresponde, 303/`HX-Redirect` donde la puerta cierra).

## Convenciones que no se negocian

- Respuestas HTMX devuelven HTML de la región, nunca redirects (303 solo para navegación normal; `HX-Redirect` para HTMX).
- `data-theme` gobierna claro/oscuro: cero utilities `dark:`, cero JS de UI de terceros.
- Assets en `public/assets/` vendorizados con SRI (nada de CDN en runtime).
- Clases daisyUI as-is; componentes compartidos a `partials/` con params.
- Cada test paga su mantenimiento: si no te da miedo que falle, no lo escribas.

## Estructura

```
app/Http/Controllers/  Controller base + controladores (Auth, Task, ...)
app/Http/Middleware/   Sesión, CSRF/headers/error (pipeline), RememberMe, RequireAuth
app/Models/ app/Support/   Modelos + helpers (Auth, Flash, Validator, Pagination, ...)
bootstrap/ config/ routes/ resources/views/  App, DI, rutas, plantillas
database/migrations/ database/seeds/  Schema versionado + datos
public/assets/  Frontend vendorizado con SRI
tests/  Suite (unit + HTTP)
scripts/  Herramientas CLI (backup)
```
