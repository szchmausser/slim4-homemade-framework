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
  - Paginación: `Pagination::paginate()` (nunca `get()` pelado) + `components/pagination/simple.twig` (params, sin hardcodear).

## Patrones de Arquitectura

- **Capas:** Controllers → Middleware → Models + Framework.
- **Middleware pipeline:** Los middleware base viven en `framework/Http/Middleware`; `app/Http/Middleware` queda para middleware propio de la app.
- **Demo:** `framework/scaffolds/tasks/` es la fuente; `composer demo:tasks:install` lo copia a `app/Tasks`, rutas, vistas, migraciones, seeds y tests, y `composer demo:tasks:remove` lo desinstala; `demo:install` / `demo:remove` quedan como alias. La app base no referencia nada del demo.
- **Modelos:** `$fillable` + `$hidden` si hay secrets. `app/Models/` es solo para modelos de dominio; la infraestructura del framework vive en `framework/` por componente (`Auth`, `Http`, `Validation`, `Docs`) y `app/Support/` queda para helpers propios de la app. Seeder idempotente (tolerante a re-ejecución).
- **Contrato de esquema:** `Framework\Auth` espera las tablas base `users` (002), `remember_tokens` (003) y `login_attempts` (006). Si extraés el framework a otra app, replicá esas migraciones o el contrato.
- **Tests:** request con body crudo + URI absoluta, `tearDown` que limpia, login inyectado si la ruta es protegida. Al menos: crea-OK, validación que rechaza, id inexistente que avisa (nunca 404 mudo ni 500 silencioso).

## Reglas de Testing (que no se negocian)

- Cada test paga su mantenimiento: si no te da miedo que falle, no lo escribas.
- Respuestas HTMX devuelven HTML de la región, never redirects (303 solo para navegación normal; `HX-Redirect` para HTMX).
- `data-theme` gobierna claro/oscuro: cero utilities `dark:`, cero JS de UI de terceros.
- Clases daisyUI as-is; componentes compartidos a `components/` con params.
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
- **Documentación duplicada:** si un Markdown de `docs/` copia un archivo del repo, la copia se declara con la ruta en la línea de arriba y **no se edita a mano**: `composer docs:sync` la reescribe y `composer docs:check` falla si difiere. Un fragmento va con `excerpt` en el info string del fence.