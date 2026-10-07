# Conventions

## HTMX Reglas

- **Región única por acción**: Cada sección interactiva (formulario, panel, carrusel) vive en una región Twig que devuelve HTML completo.
- **Sin redirecciones normales**: Las respuestas HTMX son siempre HTML (región) o `HX-Redirect` para acciones de carga completa.
- **Actions**: `hx-on::{event}="handler()"` (ej: `hx-on::save="this.save()"`).
- **Cancelación automática**: `hx-on::cancel="this.cancel()"` para limpiar estado tras error.
- **Formularios**: Cada formulario incluye `hx-headers` (no `hx-include`) para DELETE, y `flash` tokens para mensajes.

## Data Theme

- El tema oscuro/claro se controla mediante la clase `data-theme` en el `<head>`.
- **Prohibido**: Utilizar utilidades `dark:` o `light:` en CSS (el tema se aplica globalmente).
- **Prohibido**: JavaScript de UI de terceros (no jQuery, no React/Vue en runtime).
- **Vendored**: Todos los assets (daisyUI, Alpine, Twig) están embebidos con SRI.

## Paginación

- Usar `App\Models\Pagination` con `Pagination::paginate($query, $perPage)`.
- Pasar el objeto paginado a la vista como `pagination`.
- Renderizar la barra de paginación con `partials/_pagination.twig`.
- Nunca usar `get()` directamente en rutas; siempre paginar.

## Test Standards

- **Request body crudo**: Simular POST/PUT con JSON o formulario real.
- **TearDown**: Limpiar datos, resetear estado, cerrar conexiones.
- **Login**: Inyectar usuario autenticado en rutas protegidas.
- **Éxito**: Verificar código 200 (o 303 para navegación normal).
- **Validación**: Probar casos de error (campos obligatorios faltantes, tipos incorrectos).
- **Id inexistente**: Devolver 404 (nunca 500 ni 503 silenciosos).

## Nomenclatura

- **Modelos**: `App\Models\{Entity}` (ej: `Task`, `User`)
- **Controladores**: `App\Http\Controllers\{ControllerName}` (ej: `TaskController`)
- **Microservicios/Componentes**: `App\Support\{HelperName}` (ej: `RememberMe`, `Flash`)
- **Rutas**: `Routes\web.php` con nombres descriptivos (ej: `taskIndex`, `taskCreate`)
- **Migrations**: `database/migrations/{number}_{description}.php`
- **Seeds**: `database/seeds/{entity}_seed.php`

## Versionado de Migraciones

- Secuenciales: `001_create_users_table`, `002_add_task_comments`, etc.
- Renombrar secuencialmente para evitar conflictos en reinicio.
- Usar `composer migrate` después de cada cambio.

## Documentación

- Todas las decisiones de diseño irán en `lp.md` (dominio) y `INDEX.md` (SDD).
- Los artefactos generados aquí son referenciales y no se sobrescriben sin aprobación.
