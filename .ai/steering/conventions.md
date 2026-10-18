# Convenciones del Proyecto

## Estilo de Código

- **PSR-4:** Autoload `App\\` → `app/` y `Tests\\` → `tests/`.
- **Nombres de archivo:** `StudlyCase` para clases, `snake_case` para migraciones (ej. `004_create_recetas_table.php`).
- **Convención actual (del README):**
  - Migraciones: **renombrar a secuencial** (`004_...`: los timestamps saturan int32).
  - Controladores: extender `Controller` base, usar `viewDefaults()` lo repetido y `$extra` lo puntual.
  - Vistas: la página extiende `layouts/app.twig`. Región única para HTMX partials.
  - HTMX: `hx-headers` (no `hx-include`) para DELETE; `hx-on::after-request="this.reset()"` si form está fuera del swap.
  - Flash: adentro del swap HTMX.
  - Tokens CSRF: en cada form (el de edición lleva los suyos: vive dentro del swap).
  - Paginación: `Pagination::paginate()` (nunca `get()` pelado) + partial `_pagination.twig` (params, sin hardcodear).

## Patrones de Arquitectura

- **Capas:** Controllers → Middleware → Models + Support helpers.
- **Middleware pipeline:** Sesión, CSRF/headers/error (pipeline), RememberMe, RequireAuth.
- **Modelos:** `$fillable` + `$hidden` si hay secrets. Seeder idempotente (tolerante a re-ejecución).
- **Tests:** request con body crudo + URI absoluta, `tearDown` que limpia, login inyectado si la ruta es protegida. Al menos: crea-OK, validación que rechaza, id inexistente que avisa (nunca 404 mudo ni 500 silencioso).

## Reglas de Testing (que no se negocian)

- Cada test paga su mantenimiento: si no te da miedo que falle, no lo escribas.
- Respuestas HTMX devuelven HTML de la región, never redirects (303 solo para navegación normal; `HX-Redirect` para HTMX).
- `data-theme` gobierna claro/oscuro: cero utilities `dark:`, cero JS de UI de terceros.
- Clases daisyUI as-is; componentes compartidos a `partials/` con params.
- Cada test: login inyectado si la ruta es protegida, tearDown que limpia, al menos 3 assertions (crea-OK, validación rechazo, id inexistente aviso).

## Accesibilidad / Seguridad

- **Claro/oscuro:** `data-theme` switch, sin utilities `dark:` en CSS.
- **CSRF:** token en cada form, verificado por middleware Slim.
- **HTMX:** `hx-headers` con token CSRF, `hx-on::after-request="this.reset()"` para resetear forms.
- **Errores:** nunca 404 mudo ni 500 silencioso en tests.

## Reglas de Workflow

- **Branch:** `main` estable, feature branches para nuevo CRUD.
- **Commit:** mensajes claros, referencia a US/FR cuando aplica.
- **Review:** Pull request con revisión de convenciones antes de merge.
- **Verificación:** `composer test` debe quedar en verde antes de merge.
- **Nuevos comandos:** agregar a `composer.json` scripts y documentar en README.