# Diseño: CRUD de Tareas (Ejemplo)

> Requirements: `@.ai/sdd/specs/001-crud-tareas/requirements.md`
> Estado: Approved
> Última actualización: 2026-10-18

## 1. Resumen

Ejemplo de CRUD siguiendo la convención del framework Slim 4, con identificadores técnicos en inglés y dominio/URL en español. Usa el patrón de 8 pasos: migración (`001_create_tasks_table.php`), modelo (`Task`), controlador (`TaskController`), rutas (`routes/web.php`), vistas (`resources/views/tasks/`), tests (`tests/Http/*Task*Test.php`), seed (`TaskSeeder` vía `DatabaseSeeder`), verificación (`composer test`).

## 2. Mapping a Requisitos

| Requisito | Cobertura en Diseño |
|-----------|---------------------|
| FR-001 | Sección 4 (Listar) y 15 (Verificación) |
| FR-002 | Sección 4 (Crear) y 6 (HTMX) |
| FR-003 | Sección 4 (Editar) y 6 (HTMX) |
| FR-004 | Sección 4 (Borrar) y 6 (HTMX) |
| FR-005 | Sección 5 (Modelo) y 7 (Verificación) |
| FR-006 | Sección 4 (Detalle) y 7 (Verificación) |
| FR-007 | Sección 4 (Toggle) y 6 (HTMX) |

## 3. Enfoque Técnico

- **Capas:** Controllers → Middleware → Models (Eloquent standalone) → Views (Twig).
- **Estado:** Base de datos SQLite (mediante `illuminate/database`).
- **HTMX:** Interacciones de swap en `tasks/_panel.twig`, `hx-headers` para CSRF.
- **Seguridad:** Grupo `/tareas` protegido con `RequireAuthMiddleware`; `slim/csrf` en pipeline.

## 4. Estructura de Componentes

```text
Tareas Feature (negocio) / Task (código)
  Controller    -> app/Http/Controllers/TaskController.php
                   (index, store, destroy, edit, update, toggle)
  Model         -> app/Models/Task.php (fillable: title, done, status, completed_at)
  Routes        -> routes/web.php (grupo /tareas + RequireAuthMiddleware)
  Views         -> resources/views/tasks/index.twig, _panel.twig, _list.twig
  Middleware    -> RequireAuthMiddleware (rutas protegidas)
  Tests         -> tests/Http/EditTaskTest.php, DeleteTaskTest.php, PaginationFlowTest.php,
                   CsrfFlowTest.php, RoutingTest.php, PartialRenderTest.php, ErrorHandlingTest.php
  Migrations    -> database/migrations/001_create_tasks_table.php,
                   004_add_status_to_tasks_table.php
  Seed          -> database/seeds/TaskSeeder.php, DemoTasksSeeder.php (vía DatabaseSeeder)
```

## 5. Modelo de Datos / Estado

```sql
CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(120) NOT NULL,
    done BOOLEAN NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    completed_at DATETIME NULL,
    created_at DATETIME,
    updated_at DATETIME
);
```

- **Propiedad:** Los datos se almacenan en `database/*.sqlite` (configurado por `.env`).
- **Modelo:** `Task` con `$fillable = ['title', 'status', 'completed_at']`.

> **Addendum 2026-11-04 (PLAN-U2b):** la columna `done` se eliminó en la migración
> `005_drop_done_from_tasks.php` (con backfill `done=1 → status=completado, completed_at`).
> `status` es la única fuente de verdad. Se deja el registro original sin reescribir.
- **Estado de la petición HTMX:** Cada swap mantiene `data-theme` sin recargar.

## 6. Contrato API / Integración

- **No aplica** para API externa. Solo renderizado server-side.
- **Rutas (todas bajo `RequireAuthMiddleware`):**

| Método | Ruta | Acción | Respuesta |
|--------|------|--------|-----------|
| GET | `/tareas` | `index` | Página completa (`tasks/index.twig`) o región |
| POST | `/tareas` | `store` | HTML swap de `tasks/_panel.twig` |
| GET | `/tareas/{id}/edit` | `edit` | HTML swap de `tasks/_panel.twig` |
| PUT | `/tareas/{id}` | `update` | HTML swap de `tasks/_panel.twig` |
| POST | `/tareas/{id}/toggle` | `toggle` | HTML swap de `tasks/_panel.twig` |
| DELETE | `/tareas/{id}` | `destroy` | HTML swap de `tasks/_panel.twig` |

- **HTMX:** cada mutación incluye `hx-headers` con el token CSRF.

## 7. Seguridad / Permisos

- **CSRF:** Token en cada form, verificado por middleware Slim.
- **Auth:** Grupo `/tareas` con `RequireAuthMiddleware`; sin sesión redirige a login.
- **Datos sensibles:** No hay secretos en `tasks`; `$hidden` se usaría si se extendiera a usuarios.
- **Errores:** Nunca 404 mudo ni 500 silencioso; siempre devolver un mensaje de error.

## 8. Flujos de Usuario

```mermaid
flowchart TD
    A[Usuario autenticado abre /tareas] --> B[Lista paginada (Pagination)]
    B --> C[Usuario crea una tarea]
    C --> D[Form HTMX envía POST /tareas]
    D --> E[Swap HTML en tasks/_panel.twig]
    E --> B
    B --> F[Usuario edita una tarea]
    F --> G[Form HTMX envía PUT /tareas/:id]
    G --> E
    B --> H[Usuario alterna completada]
    H --> I[POST /tareas/:id/toggle]
    I --> E
    B --> J[Usuario borra una tarea]
    J --> K[DELETE /tareas/:id con hx-headers]
    K --> E
```

## 9. Casos de Borde

| Caso | Comportamiento Esperado |
|------|-------------------------|
| Tarea no existe (p. ej. GET/PUT/DELETE `/tareas/999`) | Devolver mensaje de error (no 404 mudo ni 500 silencioso) |
| Datos de formulario inválidos | Devolver form con errores visibles |
| Base de datos de test sucia | `tearDown` limpia en todos los tests |
| Sin sesión en `/tareas` | Redirigir a login (middleware) |

## 10. Notas de Accesibilidad / UX

- **Theme:** `data-theme` en `<html>`; no usar utilities `dark:`.
- **HTMX:** `hx-headers` para CSRF; `hx-on::after-request="this.reset()"` si el form está fuera del swap.
- **Flash:** El mensaje de éxito o error debe ir dentro del swap HTMX.

## 11. Observabilidad / Operaciones

- **Logs:** `Monolog` en `storage/logs/` (ya configurado por el framework).
- **Verificación:** `composer test`, `composer check:termux`.

## 12. Migraciones / Rollout

- **Migraciones:** `001_create_tasks_table.php` y `004_add_status_to_tasks_table.php` (numeración secuencial; los timestamps saturan int32).
- **Fresh:** `vendor/bin/phinx rollback -t 0` → luego `composer migrate`.
- **Seed:** `vendor/bin/phinx seed:run -s DatabaseSeeder` (orquesta `AdminSeeder` y `TaskSeeder`; cada seeder es idempotente).

## 13. Decisiones Técnicas

### TD-001: Paginación con Pagination

- **Decisión:** Usar `Pagination::paginate()` en lugar de `get()` pelado.
- **Por qué:** El README establece que nunca debe usarse `get()` pelado; es una regla de la arquitectura.
- **Alternativas:** `DB::table('tasks')->get()` (rechazado por arquitectura).

### TD-002: HTMX sobre redirect 303

- **Decisión:** Respuestas HTMX devuelven HTML, nunca 303. Para navegación clásica usar `303`, para HTMX usar `HX-Redirect`.
- **Por qué:** El README dice: "Respuestas HTMX devuelven HTML de la región, nunca redirects".
- **Alternativas:** Redirección tradicional (rechazada para HTMX).

### TD-003: Nomenclatura dominio español / código inglés

- **Decisión:** La URL y el lenguaje de negocio usan "Tareas" (`/tareas`), mientras que clases, tablas y columnas usan inglés (`Task`, `tasks`, `title`, `done`, `status`, `completed_at`).
- **Por qué:** Convención del framework (PSR/StudlyCase en inglés) sin perder el vocabulario del usuario en la URL.
- **Alternativas:** Renombrar todo a español (rechazado: rompe convenciones del proyecto).

## 14. Riesgos

| Riesgo | Impacto | Mitigación |
|--------|---------|------------|
| Timestamps int32 | High | Usar numeración secuencial para migraciones |
| HTMX swap incorrecto | Medium | Verificar que `hx-headers` esté incluido y el flash dentro del swap |
| Drift entre specs y código | Medium | Reconciliar artefactos tras cada implementación (este documento, 2026-10-18) |

## 15. Estrategia de Verificación

- Unit/Support: `tests/Support/PaginationTest.php`, `ValidatorTest.php`, `FlashTest.php`
- HTTP/Feature: `tests/Http/EditTaskTest.php`, `DeleteTaskTest.php`, `PaginationFlowTest.php`, `CsrfFlowTest.php`, `RoutingTest.php`, `PartialRenderTest.php`, `ErrorHandlingTest.php`
- Integration smoke: `curl -X GET http://localhost:8080/tareas`
- Build/lint: `composer test`
- Manual: `php -S 0.0.0.0:8080 -t public` + navegar `/tareas`, crear, editar, completar y borrar una tarea

## 16. FAQ de Implementación

**Q:** ¿Dónde va el form HTMX (dentro del swap o fuera)?  
**A:** Puede ir fuera del swap si se requiere, pero siempre con `hx-headers` y `hx-on::after-request="this.reset()"`. Si está dentro del swap, no hace falta el reset.

**Q:** ¿Qué pasa si la base de datos SQLite no existe al iniciar?  
**A:** `vendor/bin/phinx migrate` crea la base y las tablas.

**Q:** ¿Dónde se guarda el seed?  
**A:** En `database/seeds/`; `DatabaseSeeder` orquesta `AdminSeeder` y `TaskSeeder`, y cada seeder debe ser idempotente.
