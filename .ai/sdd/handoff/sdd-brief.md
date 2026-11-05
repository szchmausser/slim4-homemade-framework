# SDD Handoff Brief: CRUD de Tareas (Ejemplo)

> Status: review:done
> Readiness: Ready for implementation / Ready for QA / Ready for release
> Updated: 2026-10-18

## Metadata

- **Spec ID:** `001-crud-tareas`
- **Spec Path:** `.ai/sdd/specs/001-crud-tareas/`
- **Current .status:** `review:done`
- **Source Inputs:** `.ai/sdd/ideas/001-crud-example.md` (idea, `idea:captured`), `.ai/sdd/PLAN.md` (`plan:approved`), requisitos/design/tasks aprobados.
- **Otros specs en el workspace:** `.ai/sdd/specs/002-toggle-csrf-fix/` (`review:done`) — fix de CSRF en el toggle de tareas.

## Product / Feature Summary

- **User / Audience:** Desarrolladores Termux que quieren un punto de partida rápido y confiable para crear CRUDs en su dispositivo sin toolchain de compilación.
- **Problem:** Falta de un ejemplo concreto de cómo implementar el patrón CRUD completo (migración → modelo → controlador → rutas → vistas → tests → seed → verificar) en el framework Slim 4 para Termux.
- **Outcome:** Proveer un ejemplo funcional y testeado que sirva como referencia para futuras entidades.
- **Scope:** Entidad "Tareas" (URL `/tareas`, código `Task`) con migración, modelo, controlador, rutas, vistas, tests y seed.
- **Out of Scope:** Autenticación avanzada, API externa, integración con servicios externos.

## Requirements Summary

- **Key User Stories:**
  - US-001: Listar Tareas (listado paginado, ruta protegida)
  - US-002: Crear una Tarea (form HTMX)
  - US-003: Editar una Tarea
  - US-004: Borrar una Tarea
  - US-005: Marcar una Tarea como completada (toggle)
- **Must Have Functional Requirements:**
  - FR-001: Listado paginado de tareas
  - FR-002: Crear tarea (POST)
  - FR-003: Editar tarea (PUT)
  - FR-004: Borrar tarea (DELETE)
  - FR-005: Validación de campos
  - FR-006: Manejo de tareas inexistentes (sin 404 mudo ni 500 silencioso)
  - FR-007: Toggle de completada (POST `/tareas/{id}/toggle`)
- **Important NFRs:**
  - NFR-001: Rendimiento (paginación, nunca `get()` pelado)
  - NFR-002: HTMX-first (respuestas devuelven HTML de región, nunca redirects 303)
  - NFR-003: Testing (cada endpoint con test de crea-OK, validación rechazo, id inexistente)

## Design Summary

- **Approach:** Patrón de capas Slim 4: Controllers → Middleware → Models (Eloquent standalone) → Views (Twig).
- **Components / Modules:**
  - `app/Http/Controllers/TaskController.php` (index, store, destroy, edit, update, toggle)
  - `app/Models/Task.php` (fillable `['title', 'done', 'status', 'completed_at']`)
  - Migraciones `001_create_tasks_table.php` y `004_add_status_to_tasks_table.php`
  - Vistas: `resources/views/tasks/index.twig`, `_panel.twig` (región HTMX), `_list.twig`
  - Rutas en `routes/web.php` (grupo `/tareas` con `RequireAuthMiddleware`)
- **Data / State:** Base de datos SQLite mediante `illuminate/database`; tabla `tasks` con `id`, `title` (string 120), `done` (boolean), `status` (string 20, default `pendiente`), `completed_at` (timestamp null), `created_at`, `updated_at`.
- **APIs / Integrations:** Sólo renderizado server-side; contrato HTMX implícito (POST/PUT/DELETE/POST-toggle con swap de `tasks/_panel.twig`).
- **Technical Decisions:**
  - TD-001: Uso de `Pagination::paginate()` en lugar de `get()` pelado.
  - TD-002: Respuestas HTMX devuelven HTML de región (nunca redirects 303); para navegación clásica usar `303`, para HTMX usar `HX-Redirect`.
- **Risks / Constraints:**
  - Riesgo de saturación de int32 con timestamps de migraciones → mitigado usando numeración secuencial (`001`, `004`).
  - Riesgo de swap HTMX incorrecto → mitigado incluyendo `hx-headers` y verificando que el flash esté dentro del swap.

## Implementation Plan

- **Task Source:** `.ai/sdd/specs/001-crud-tareas/tasks.md`
- **Recommended Order:** T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8
- **Key Tasks:**
  - T1: Migración `001_create_tasks_table.php` (tabla `tasks`)
  - T2: Modelo `Task`
  - T3: Controlador `TaskController` (index/store/destroy/edit/update/toggle)
  - T4: Vistas `tasks/index.twig`, `_panel.twig`, `_list.twig`
  - T5: Rutas RESTful en `routes/web.php` (grupo `/tareas` con `RequireAuthMiddleware`)
  - T6: Tests (`EditTaskTest`, `DeleteTaskTest`, `PaginationFlowTest`, `CsrfFlowTest`, `RoutingTest`, `PartialRenderTest`, ...)
  - T7: Seed idempotente (`TaskSeeder` / `DemoTasksSeeder` vía `DatabaseSeeder`)
  - T8: Verificación (`composer test`, `curl` smoke, `composer check:termux`)
- **Likely Files / Areas:**
  - `database/migrations/001_create_tasks_table.php`
  - `database/migrations/004_add_status_to_tasks_table.php`
  - `app/Models/Task.php`
  - `app/Http/Controllers/TaskController.php`
  - `resources/views/tasks/*.twig`
  - `routes/web.php`
  - `tests/Http/*Task*Test.php`
  - `database/seeds/DatabaseSeeder.php`

## Verification Plan

```text
Command: composer test
Expected: suite verde (el conteo lo imprime composer test)
Command: composer check:termux
Expected: Detección correcta de 32-bit PHP (o compatible)
Command: vendor/bin/phinx rollback -t 0 && composer migrate
Expected: Migraciones aplicadas sin errores
Command: curl -X GET http://localhost:8080/tareas
Expected: HTTP 200 con HTML de lista paginada (o redirect a login si no hay sesión)
```

## Review / Release Notes

- **Review Artifact:** `.ai/sdd/specs/001-crud-tareas/review.md`
- **Review Verdict:** Approved (todos los FR cubiertos por diseño y tareas)
- **Known Follow-ups:** Ninguno (implementación completa y verificada)

## Handoff Readiness

- **Ready for Implementation:** yes (código ya implementado y testeado)
- **Ready for QA:** yes (suite de tests pasa)
- **Ready for Release:** yes (feature estable, documentación disponible)
- **Blockers:** Ninguno
- **Recommended Next Action:**
  - Para nuevas entidades (Usuarios, Recetas, etc.), repetir el flujo SDD: idea → plan (opcional) → requisitos → diseño → tareas → implementación → review.
  - Considerar actualizar `PLAN.md` con hoja de ruta para entidades futuras.
  - Refrescar este brief tras el próximo `review:done`.
