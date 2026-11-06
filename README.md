# Slim 4 Homemade Framework

Esqueleto PHP en capas para apps server-rendered, pensado para correr en Termux (ARM 32-bit) sin toolchain de compilación.

**Stack:** Slim 4 · PHP-DI · Eloquent (standalone) · Phinx · Twig · HTMX · Alpine.js · daisyUI 5 (vendorizado) · SQLite · respect/validation · Monolog.

**Requisitos:** PHP >= 8.4.1 (`pkg install php` trae una versión vigente) y Composer 2. El piso no es capricho: el lock lo exige (`symfony/clock`, `symfony/translation` y PHPUnit piden `>=8.4.1`), y con un PHP menor el clon falla en el platform check antes de arrancar.

**Guía completa paso a paso:** [`docs/guia-stack-php-termux.md`](docs/guia-stack-php-termux.md) — instalación en Termux, cada archivo con código copiable, testing y troubleshooting.

**Push a GitHub desde Termux:** [`docs/git-push-termux.md`](docs/git-push-termux.md) — por qué `pi`/scripts no comparten el `ssh-agent` y cómo resolverlo (`composer push`).

## Arranque rápido

```bash
cp .env.example .env
composer install:termux     # en 64-bit: composer install
composer dump-autoload -o
composer demo:install       # opcional: copia, migra y siembra el demo de tareas
ADMIN_EMAIL=vos@ejemplo.com ADMIN_PASSWORD=una-clave-larga vendor/bin/phinx seed:run -s DatabaseSeeder
php -S 0.0.0.0:8080 -t public   # http://localhost:8080 (no `composer serve`, ver abajo)
composer test               # suite completa (el conteo lo imprime el comando)
```

## Comandos

| Comando | Para qué |
|---|---|
| `php -S 0.0.0.0:8080 -t public` | Servidor dev en `:8080` (`composer serve` retirado, ver abajo) |
| `composer migrate` | Migraciones pendientes |
| `vendor/bin/phinx rollback -t 0` | Baja todo (el "fresh") |
| `vendor/bin/phinx seed:run -s DatabaseSeeder` | Admin (+ demo si está instalado) en orden |
| `vendor/bin/phinx seed:run -s NombreSeed` | Un seed puntual |
| `composer db:backup` | Backup consistente (`.sqlite`) en `storage/backups/`, retención `KEEP` |
| `composer test` | Suite completa |
| `composer verify` | Lint + docs-check + PHPStan + suite + migración fresh sobre DB temporal |
| `composer lint` | `php -l` sobre `app/`, `framework/` y `tests/` |
| `composer docs:check` | Falla si reaparecen cifras hardcodeadas o claims de CDN/64-bit, o si una copia de archivo de la guía no coincide con el repo |
| `composer docs:sync` | Reescribe las copias de archivos de la guía desde el repo (`docs-check` es el que verifica) |
| `composer phpstan` | Análisis estático nivel 5 (phar dev-only; se cachea en `storage/cache/`) |
| `composer migrate:fresh` | Todas las migraciones sobre una SQLite temporal |
| `composer demo:install` | Copia `framework/scaffolds/tasks/` a `app/Tasks`, rutas, vistas, migraciones, seeds y tests; migra y siembra |
| `composer demo:remove` | Desinstala el demo y limpia la tabla `tasks` + sus filas de `phinxlog` |
| `composer push` | Push a GitHub desde Termux (agente SSH fijo; ver `docs/git-push-termux.md`) |
| `composer check:termux` | 32 o 64 bits |

`composer verify` incluye PHPStan y docs-check. El phar dev-only (~30 MB) se descarga una vez en `storage/cache/`; para trabajar offline: `PHPSTAN_PHAR=/ruta/al/phpstan.phar composer phpstan`.

Los flags del demo se pasan después de `--`: `composer demo:install -- --force` reinstala, y `composer demo:remove -- --no-db` borra solo archivos (deja la DB).

La guía copia archivos enteros del repo. Esas copias **no se editan a mano**: `composer docs:check` compara cada bloque que declara un archivo contra el archivo real y falla si difieren; `composer docs:sync` las reescribe. Un fragmento se marca `excerpt` en el fence y queda afuera del control. Si cambiás una vista o un test que la guía copia, corré `composer docs:sync` antes de cerrar el cambio.

### El servidor va directo, no por Composer

**No uses `composer serve`.** Composer no es un runner de procesos de larga vida: ejecuta cada script como subproceso con un `process-timeout` de 300 s (valor por defecto) y, al vencer, mata el servidor con:

```
The process "php -S 0.0.0.0:8080 -t public" exceeded the timeout of 300 seconds
```

El `php -S` estaba perfecto; lo bajó Composer. Por eso el servidor de desarrollo se levanta sin intermediario:

```bash
php -S 0.0.0.0:8080 -t public
```

`composer serve` quedó como guarda (`scripts/serve.php`): aborta con el mensaje de arriba en vez de morir a los 5 minutos. Si necesitás el servidor vivo sin cerrar la terminal, `tmux` + `termux-wake-lock` (guía, caps. 17.1 y 17.2).

## Receta: nuevo CRUD en N pasos

El CRUD de Tareas es el demo instalable (`composer demo:install`). Para tu app, replicá el patrón en las capas app-owned (`app/Models`, `app/Http/Controllers`, `routes/web.php`, `resources/views`):

1. **Migración.** `vendor/bin/phinx create CreateRecetasTable`, completala y **renombrala a secuencial** (`007_...`: los timestamps saturan int32 y el scaffold `tasks` reserva `001/004/005`, ver guía 11.1). `composer migrate`.
2. **Modelo** en `app/Models/` con `$fillable` (+ `$hidden` si hay secrets).
3. **Controlador** que extienda `Controller`: lo repetido a `viewDefaults()`, lo puntual por `$extra` en cada `render()`. Si lista, pagina con `Pagination::paginate()` (nunca `get()` pelado) y pasá `pagination` a la vista.
4. **Rutas** en `routes/web.php` (+ `->add(RequireAuthMiddleware::class)` si es protegida, `'active'` para la nav si tiene página completa).
5. **Vistas.** La página extiende `layouts/app.twig`. Si hay HTMX parcial: **una sola región** que devuelven tanto el full como el swap (como `_panel`), flash adentro del swap, tokens CSRF en cada form (el de edición lleva los suyos: vive dentro del swap), `hx-headers` (no `hx-include`) para DELETE, `hx-on::after-request="this.reset()"` si el form queda fuera del swap. Paginación con `partials/_pagination.twig` (params, sin hardcodear).
6. **Tests.** Cloná el estilo: request con body crudo + URI absoluta, `tearDown` que limpia, login inyectado si la ruta es protegida, y al menos: crea-OK, validación que rechaza, id inexistente que avisa (nunca 404 mudo ni 500 silencioso).
7. **Seed** si hace falta dato inicial: seeder idempotente + inclusión en `DatabaseSeeder` (o en el scaffold demo si el seeder es del demo; todo seeder debe tolerar re-ejecución).
8. **Verificá:** `composer test` en verde + smoke con `curl` (200 donde corresponde, 303/`HX-Redirect` donde la puerta cierra).

## Convenciones que no se negocian

- Respuestas HTMX devuelven HTML de la región, nunca redirects (303 solo para navegación normal; `HX-Redirect` para HTMX).
- Toda URL de plantilla va por `url('/login')` (en PHP, `Framework\Http\Url::path()`), nunca un literal raíz-absoluto a mano: es lo único que permite servir la app desde una carpeta con `APP_BASE_PATH=/app` (vacío = raíz, sin cambios). Un literal raíz-absoluto nuevo deja esa URL fuera del prefijo.
- `data-theme` gobierna claro/oscuro: cero utilities `dark:`, cero JS de UI de terceros.
- Assets en `public/assets/` vendorizados con SRI (nada de CDN en runtime).
- Clases daisyUI as-is; componentes compartidos a `partials/` con params.
- Cada test paga su mantenimiento: si no te da miedo que falle, no lo escribas.
- `app/Models/` es para modelos de dominio; la infraestructura del framework vive en `framework/` por componente (`Auth`, `Http`, `Validation`, `Docs`). `app/Support/` queda para helpers propios de la app.
- El demo no se instala por defecto: `composer demo:install` copia `framework/scaffolds/tasks/`; `composer demo:remove` lo quita. La app base no debe referenciar rutas ni modelos del demo.

## Estructura

```
framework/                       Infraestructura (Auth, Http, Validation, Docs)
framework/scaffolds/tasks/       Demo opcional (composer demo:install)
app/Http/Controllers/            Controladores de la app (Auth/AuthController, ...)
app/Http/Middleware/             Middleware propio de la app
app/Models/                      Modelos de dominio (User, ...)
app/Support/                     Helpers propios de la app
bootstrap/ config/ routes/ resources/views/  App, DI, rutas, plantillas
database/migrations/ database/seeds/  Schema versionado + datos base
public/assets/  Frontend vendorizado con SRI
tests/  Suite (unit + HTTP)
scripts/  Herramientas CLI (backup, lint, verificación, demo)
.github/workflows/  CI (PHP 8.4/8.5 + composer verify)
```
