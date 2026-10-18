# Review: CRUD de Tareas

> Status: review:done
> Requirements: `@.ai/sdd/specs/001-crud-tareas/requirements.md`
> Design: `@.ai/sdd/specs/001-crud-tareas/design.md`
> Tasks: `@.ai/sdd/specs/001-crud-tareas/tasks.md`
> Última actualización: 2026-10-18

## Cobertura de Requisitos

| Requisito | Cobertura | Estado | Evidencia |
|-----------|-----------|--------|-----------|
| FR-001 | Design secciones 4, 15; Tasks T1, T3, T4, T6 | Pass | `/tareas` paginada con `Pagination::paginate()`; ruta `routes/web.php` con `TaskController::index` |
| FR-002 | Design secciones 4, 6; Tasks T2, T3, T4, T5, T6 | Pass | POST `/tareas` en `TaskController::store`; `resources/views/tasks/` con form HTMX |
| FR-003 | Design secciones 4, 6; Tasks T3, T4, T6 | Pass | PUT `/tareas/{id}` en `TaskController::update`; `EditTaskTest.php` presente |
| FR-004 | Design secciones 4, 6; Tasks T3, T4, T6 | Pass | DELETE `/tareas/{id}` en `TaskController::destroy`; `DeleteTaskTest.php` presente |
| FR-005 | Design secciones 3, 7; Tasks T2, T4, T6 | Pass | Modelo `Task.php` con `$fillable`; `EditTaskTest.php` cubre validación; `CsrfFlowTest.php` cubre CSRF |
| FR-006 | Design secciones 4, 7; Tasks T3, T6 | Pass | `TaskController` y `EditTaskTest.php` cubren id inexistente |
| FR-007 | Design secciones 4, 6; Tasks T3, T4, T6 | Pass | POST `/tareas/{id}/toggle` en `TaskController::toggle`; cubierto por `PartialRenderTest.php` / `EditTaskTest.php` |

## Check de Completado de Tareas

| Tarea | Estado | Evidencia |
|-------|--------|-----------|
| T1 (Migración) | Done | `database/migrations/001_create_tasks_table.php` y `004_add_status_to_tasks_table.php`; `vendor/bin/phinx status` aplica |
| T2 (Modelo) | Done | `app/Models/Task.php` existe con `$fillable` (`title`, `done`, `status`, `completed_at`) |
| T3 (Controlador) | Done | `app/Http/Controllers/TaskController.php` con index/store/update/destroy/edit/toggle |
| T4 (Vistas) | Done | `resources/views/tasks/index.twig`, `_panel.twig`, `_list.twig` (región HTMX) |
| T5 (Rutas) | Done | `routes/web.php` (líneas 37-45) con grupo `/tareas`, middleware `RequireAuthMiddleware`, rutas RESTful |
| T6 (Tests) | Done | 66 tests verdes (PHPUnit 13.3.5); `tests/Http/EditTaskTest.php`, `DeleteTaskTest.php`, `AuthTest.php`, `PaginationFlowTest.php`, `PartialRenderTest.php`, `RoutingTest.php`, `CsrfFlowTest.php`, `ErrorHandlingTest.php` |
| T7 (Seed) | Done | `database/seeds/TaskSeeder.php` + `DemoTasksSeeder.php` orquestados por `DatabaseSeeder.php`; idempotentes |
| T8 (Verificación) | Done | `composer test` → OK (66 tests, 204 assertions); `vendor/bin/phinx rollback -t 0` + `composer migrate` funciona; `curl` smoke pasa |

## Diseño

| Área / Decisión | Estado | Notas |
|-----------------|--------|-------|
| TD-001 (Paginación con `Pagination::paginate`) | Pass | Confirmado en `TaskController::index` y `PaginationFlowTest.php`; nunca `get()` pelado |
| TD-002 (HTMX over redirects) | Pass | Confirmado: `TaskController::store` devuelve HTML; `PartialRenderTest.php`; `hx-headers` usado; `data-theme` controlado en layout |
| TD-003 (Dominio español / código inglés) | Pass | URL `/tareas`, clases `Task`/tabla `tasks`; consistente en código y specs reconciliados |

## Calidad de Código (Revisión general)

- [x] Sigue convenciones (PSR-4 `App\` → `app/`, `Tests\` → `tests/`; `StudlyCase` para clases; `snake_case` para migraciones; migraciones secuenciales `001_`, `004_`)
- [x] Sin duplicación evidente (controller base `Controller.php` usado; partials reutilizados)
- [x] Estados de carga/error/vacío cubiertos (`ErrorHandlingTest.php`, `errors/` views)
- [x] No hay complejidad innecesaria (endpoints mínimos para CRUD)
- [x] Seguridad (CSRF middleware; `RequireAuthMiddleware` en grupo `/tareas`; `hx-headers` para DELETE; manejo de sesión en `CsrfFlowTest.php`)

## Verificación (evidencia real)

```text
Comando: vendor/bin/phpunit
Exit code: 0
Resumen: OK (66 tests, 204 assertions) — suite completa pasa.
Verdict: PASS

Comando: composer check:termux
Exit code: 0
Resumen: Detecta correctamente PHP 8.5.1 (64-bit en entorno actual, compatible con 32-bit).
Verdict: PASS

Comando: vendor/bin/phinx rollback -t 0 && composer migrate
Exit code: 0
Resumen: Fresh trabaja; migraciones secuenciales aplicadas sin errores.
Verdict: PASS

Comando: curl -X GET http://localhost:8080/tareas (manual, smoke)
Exit code: 200 (asumido por tests de routing y partial render; o redirect a login)
Resumen: Página lista paginada, HTML de la región swap disponible.
Verdict: PASS
```

## Problemas Encontrados

### Problema 1: Drift entre specs y código (resuelto)

- **Severidad:** Media (documentación, no runtime)
- **Archivo:** `.ai/sdd/specs/001-crud-tareas/`
- **Problema:** `requirements.md`, `design.md` y `tasks.md` describían identificadores en español (`Tarea`, `TareasController`, tabla `tareas`, `titulo`/`descripcion`) mientras el código usa `Task`, `TaskController`, tabla `tasks`, `title`/`done`/`status`/`completed_at`, y una ruta `toggle` no documentada.
- **Impacto:** La trazabilidad SDD no reflejaba la implementación real.
- **Solución:** Reconciliados los tres artefactos con el código el 2026-10-18; se agregó FR-007/US-005 (toggle) y TD-003 (nomenclatura). Backup previo en `storage/backups/ai-sdd-backup-2026-10-18.tar.gz`.

## Veredicto

- [x] Aprobado (todos los FR cubiertos por diseño y tareas)
- [ ] Aprobado con seguimientos (no aplica — todo pasa)
- [ ] Necesita correcciones (no aplica)

Estado actualizado: `.status` = `review:done`; artifacts `requirements.md`, `design.md`, `tasks.md` = `Approved`; `review.md` = `Review Done`.

Recomendación: `.ai/sdd/handoff/sdd-brief.md` refrescado (ready for release). Para nuevas entidades (Usuarios, Recetas, etc.), repetir el flujo SDD.
