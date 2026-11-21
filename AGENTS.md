# AGENTS.md — guía operativa para agentes

Este repositorio es un **esqueleto PHP server-rendered** para apps pequeñas/medianas:
Slim 4 + PHP-DI + Eloquent standalone + Phinx + Twig + SQLite. El objetivo es que el
clon funcione en Termux (ARM 32-bit) **sin toolchain de compilación**, sin Node, sin
bundler, sin CDNs y sin paso de build. `app/` es el dominio del usuario; `framework/`
es infraestructura reusable; `framework/scaffolds/*` son demos copiables.

## Invariantes (no romper)

- **Sin build para quien usa el esqueleto.** No agregar pasos que exijan Node, npm,
  bundler ni compilación en el flujo de instalación/migración/ejecución.
- **Sin CDNs ni recursos remotos en runtime.** Assets locales en `public/assets/`
  (o en el scaffold correspondiente), servidos con `integrity="sha384-..."`.
  Si cambiás un asset, recalculá el SRI y actualizá el `<link>`/`<script>`.
- **Sin JS nuevo fuera de los bloques permitidos.** El shell usa bloques inline
  concretos (pre-pintado de tema/sidebar, `shell()`/`toastStack()` de Alpine) y
  `public/assets/app.js` con SRI para la lógica no crítica (toasts, foco, diálogos,
  doble submit). Para los demos, el JS vive en su scaffold y se instala con el demo.
  No agregar `<script>` sueltos ni `onclick` con datos no confiables.
- **URLs siempre por helper.** En Twig `{{ url('/ruta') }}`; en PHP
  `Framework\Http\Url::path('/ruta')`. Nunca literales raíz-absolutos: rompen
  `APP_BASE_PATH`.
- **Las migraciones son la única fuente del esquema.** No crear tablas a mano ni
  duplicar el esquema en tests. Los seeds son datos, no schema.
- **`app/` es del usuario; `framework/` es reusable.** No metas reglas de negocio
  de una app en `framework/`. `framework/` no debe depender de `App\`.
- **Los demos no se editan a mano en dos lugares.** La fuente es
  `framework/scaffolds/<demo>/`; `composer demo:<demo>:install` copia a `app/`,
  `routes/`, `resources/views/`, `database/` y `tests/`. Si editás una demo ya
  instalada, sincronizá el scaffold o volvé a instalarla.
- **La documentación copiada se sincroniza.** `composer docs:check` falla si una
  copia diverge; después de tocar una vista/test copiado, corré `composer docs:sync`.

## Mapa rápido

| Ruta | Qué es |
|---|---|
| `app/` | Código de la app: las entidades nuevas van planas en `app/Models` y `app/Http/Controllers`, que es lo que enseña el tutorial. Los demos agrupan por entidad en `app/<Demo>/`. |
| `framework/Auth` | Sesión, remember-me, throttle, provider de usuarios. |
| `framework/Http` | Controller base, CSRF, Flash, Pagination, Url, middlewares. |
| `framework/Validation` | Validator propio (respect/validation). |
| `framework/scaffolds/*` | Demos instalables (tasks, contacts, notas). |
| `routes/*.php` | Rutas por convención; `web.php` primero, el resto alfabético. |
| `resources/views/` | Twig: layouts, componentes, partials. |
| `database/migrations` | Esquema (Phinx). `database/seeds` datos iniciales. |
| `tests/` | Tests de la app y de tus entidades: contratos del framework, comportamiento HTTP y scripts. Una carpeta por entidad (`tests/<Entidad>/Http/`, `namespace Tests\<Entidad>\Http`); los tests de cada demo viajan en su scaffold. |
| `scripts/` | `lint`, `docs-check/sync`, `phpstan`, `migrate-fresh`, `demo`, `serve`, backup. |

## Comandos

```bash
composer install:termux     # Termux/32-bit; en 64-bit: composer install
composer migrate            # aplicar migraciones
composer make:migration Nombre  # crea database/migrations/NNN_nombre.php con el siguiente número libre
composer test               # PHPUnit de la app base
composer lint               # lint propio
composer phpstan            # PHPStan nivel 5 (PHAR, sin dependencias)
composer docs:check         # copias de docs sincronizadas
composer docs:sync          # regenerar copias
composer verify             # gate: lint + docs + phpstan + test + migrate:fresh
composer check:32bit        # gate manual: exit 0 solo en PHP de 32 bits
php -S 127.0.0.1:8080 -t public   # servidor de desarrollo (no usar 0.0.0.0 en LAN)
```

Demos:

```bash
composer demo:tasks:install
composer demo:tasks:remove
composer demo:contacts:install
composer demo:contacts:remove
composer demo:notas:install
composer demo:notas:remove
```

`composer verify` **no** instala demos: los tests de scaffold solo corren si el demo
está instalado (CI lo hace con la matriz `demo-round-trip`). Si tocás un scaffold,
instalalo y corré `composer test` antes de cerrar.

## Flujo recomendado

1. Leé `README.md`, `DESIGN.md`, `.ai/steering/` y el scaffold que vas a tocar.
2. Si el cambio es de dominio, hacelo en `app/`; si es infra reusable, en `framework/`.
3. Si agregás una entidad, creá su archivo en `routes/`, su migración y sus vistas;
   no edites `bootstrap/app.php` para registrarla. La migración se crea con
   `composer make:migration Create<Entidad>Table`: el número lo asigna el script.
4. Si agregás una ruta mutante, incluí el token CSRF (`components/form/csrf.twig`) y
   verificá el comportamiento HTMX (región, `HX-Trigger`, `HX-Push-Url`).
5. Corré `composer verify` y, si corresponde, el demo instalado + `composer test`.
6. Si tocaste docs copiadas, `composer docs:sync` y `composer docs:check`.

## Definición de "done"

- `composer verify` verde.
- Tests nuevos para la ruta de fallo (no solo el happy path): CSRF inválido,
  404/405, validación, permisos.
- Sin URLs externas nuevas, sin CDNs, sin pasos de build.
- SRI actualizado si cambió un asset.
- `composer docs:check` verde si el cambio toca documentación copiada.
- Si el cambio afecta el esquema: migración nueva (no editar una ya aplicada) y
  `composer migrate:fresh` verde.

## Seguridad y datos

- No commits de `.env`, `database/*.sqlite`, `storage/`, backups ni secretos.
- CSRF es global (`Slim\Csrf\Guard`): toda ruta POST/PUT/PATCH/DELETE necesita token.
- Las cookies de sesión/remember-me son HttpOnly y SameSite=Lax; `Secure` se activa
  con `SESSION_SECURE=true` detrás de HTTPS.
- No imprimas tokens, contraseñas ni valores de `.env` en logs o respuestas.
- Los demos son demos: no asumen ownership multiusuario. Si convertís uno en app
  real, agregá `user_id`/autorización antes de exponerlo.

## Limitaciones conocidas

- Tailwind se compila en el navegador con `tailwind-browser`; daisyUI se sirve
  completo. Es una decisión deliberada para no agregar build; implica FOUC y
  dependencia de JS para las utilidades.
- No hay CSP estricta: el stack usa JS inline y Alpine/Tailwind runtime. No agregar
  una CSP que rompa la app sin rediseñar esos puntos.
- `expires_at`/`attempted_at` son enteros Unix: en PHP de 32 bits hay límite en 2038.
- `php -S` es de desarrollo (single-thread); para producción usar nginx/Caddy + PHP-FPM.
