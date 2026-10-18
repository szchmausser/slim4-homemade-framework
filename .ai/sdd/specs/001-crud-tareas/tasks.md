# Tareas: CRUD de Tareas (Ejemplo)

> Requirements: `@.ai/sdd/specs/001-crud-tareas/requirements.md`
> Design: `@.ai/sdd/specs/001-crud-tareas/design.md`
> Status: Approved (todas las tareas completadas — ver `review.md`)
> Última actualización: 2026-10-18

## Cobertura de Requerimientos

| Requisito | Tareas | Notas |
|-----------|--------|-------|
| FR-001 | T1, T3, T4, T6 | Listado paginado |
| FR-002 | T2, T3, T4, T5, T6 | Crear tarea |
| FR-003 | T3, T4, T6 | Editar tarea |
| FR-004 | T3, T4, T6 | Borrar tarea |
| FR-005 | T2, T4, T6 | Validación |
| FR-006 | T3, T6 | Tareas inexistentes |
| FR-007 | T3, T4, T6 | Toggle de completada |

## Lectura de Verificación de Implementación

| Verificación | Estado | Notas |
|--------------|--------|-------|
| Must Have requirements have tasks | Pass | Todos los FR cubiertos |
| Requirements are covered by design | Pass | Todas las secciones de design |
| Critical Questions are answered | Pass | Q-001 y Q-002 resueltas |
| Tasks have dependencies, acceptance criteria, files, and verification | Pass | Cada tarea con checklist |
| Verification commands are known or marked manual/N/A | Pass | `composer test` |

## Tareas

### T1: Configurar migraciones (FR-001, FR-007)

**Prioridad:** P0  
**Estimación:** 30 min  
**Dependencias:** none  
**Cubre:** FR-001, FR-007

#### Trabajo
- [x] Crear `database/migrations/001_create_tasks_table.php` (tabla `tasks`)
- [x] Columnas base: `id`, `title` (VARCHAR 120), `done` (BOOLEAN), `created_at`, `updated_at`
- [x] Crear `database/migrations/004_add_status_to_tasks_table.php` (`status`, `completed_at`)
- [x] Migraciones con numeración secuencial (001, 004; no timestamp)
- [x] Ejecutar `vendor/bin/phinx migrate` y verificar sin errores

#### Criterios de Aceptación
- [x] `vendor/bin/phinx rollback -t 0` funciona
- [x] `composer migrate` recrea las tablas

#### Archivos
- `database/migrations/001_create_tasks_table.php` — crear
- `database/migrations/004_add_status_to_tasks_table.php` — crear

#### Verificación
- [x] `vendor/bin/phinx status` muestra las migraciones aplicadas

### T2: Crear modelo Task (FR-001, FR-005)

**Prioridad:** P0  
**Estimación:** 30 min  
**Dependencias:** T1  
**Cubre:** FR-001, FR-005

#### Trabajo
- [x] Crear `app/Models/Task.php` extendiendo el modelo base
- [x] `$fillable = ['title', 'done', 'status', 'completed_at']`
- [x] `$hidden` según necesidad (sin secretos en `tasks`)

#### Criterios de Aceptación
- [x] El modelo puede crear una nueva tarea

#### Archivos
- `app/Models/Task.php` — crear

#### Verificación
- [x] `Task::create(['title' => 'x'])` funciona

### T3: Crear controlador (FR-001 a FR-004, FR-006, FR-007)

**Prioridad:** P0  
**Estimación:** 2 h  
**Dependencias:** T1, T2  
**Cubre:** FR-001 a FR-004, FR-006, FR-007

#### Trabajo
- [x] Crear `app/Http/Controllers/TaskController.php` extendiendo `Controller`
- [x] Método `index()` → paginación con `Pagination::paginate()`
- [x] Método `store()` → crear tarea y devolver región HTMX
- [x] Método `edit($id)` → form HTMX con la tarea actual
- [x] Método `update($id)` → actualizar y devolver región HTMX
- [x] Método `toggle($id)` → alternar completada y devolver región HTMX
- [x] Método `destroy($id)` → borrar tarea y devolver región HTMX

#### Criterios de Aceptación
- [x] `index` renderiza `tasks/index.twig`

#### Archivos
- `app/Http/Controllers/TaskController.php` — crear
- `routes/web.php` — modificar

#### Verificación
- [x] `vendor/bin/phpunit --filter EditTaskTest` pasa

### T4: Crear vistas (FR-001 a FR-007)

**Prioridad:** P0  
**Estimación:** 2 h  
**Dependencias:** T3  
**Cubre:** FR-001 a FR-007

#### Trabajo
- [x] Crear `resources/views/tasks/index.twig`
- [x] Crear `resources/views/tasks/_panel.twig` (región HTMX)
- [x] Crear `resources/views/tasks/_list.twig` (partial de lista)
- [x] Usar `layouts/app.twig` como layout base
- [x] Flash adentro del swap HTMX
- [x] Tokens CSRF en cada form
- [x] Paginación con `partials/_pagination.twig`

#### Criterios de Aceptación
- [x] `/tareas` devuelve lista paginada

#### Archivos
- `resources/views/tasks/index.twig` — crear
- `resources/views/tasks/_panel.twig` — crear
- `resources/views/tasks/_list.twig` — crear

#### Verificación
- [x] `curl -X GET http://localhost:8080/tareas` devuelve 200 con HTML (o redirect a login)

### T5: Crear rutas (FR-001 a FR-007)

**Prioridad:** P0  
**Estimación:** 30 min  
**Dependencias:** T3  
**Cubre:** FR-001 a FR-007

#### Trabajo
- [x] Grupo `/tareas` en `routes/web.php` (RESTful):
  - [x] GET `/tareas` → `index`
  - [x] POST `/tareas` → `store`
  - [x] GET `/tareas/{id}/edit` → `edit`
  - [x] PUT `/tareas/{id}` → `update`
  - [x] POST `/tareas/{id}/toggle` → `toggle`
  - [x] DELETE `/tareas/{id}` → `destroy`
- [x] Middleware `RequireAuthMiddleware::class` en el grupo
- [x] `'active'` para la nav si tiene página completa

#### Criterios de Aceptación
- [x] `composer serve` + `curl http://localhost:8080/tareas` → 200 (autenticado) o redirect a login

#### Archivos
- `routes/web.php` — modificar

#### Verificación
- [x] Rutas cubiertas por `RoutingTest.php`

### T6: Crear tests (FR-001 a FR-007)

**Prioridad:** P0  
**Estimación:** 1 h  
**Dependencias:** T3, T5  
**Cubre:** FR-001 a FR-007

#### Trabajo
- [x] Crear tests HTTP: `tests/Http/EditTaskTest.php`, `DeleteTaskTest.php`
- [x] Cobertura de flujo: `PaginationFlowTest.php`, `PartialRenderTest.php`, `CsrfFlowTest.php`, `RoutingTest.php`, `ErrorHandlingTest.php`
- [x] Login inyectado si la ruta es protegida
- [x] `tearDown` que limpia
- [x] Test: crea-OK
- [x] Test: validación que rechaza
- [x] Test: id inexistente que avisa (nunca 404 mudo ni 500 silencioso)
- [x] Request con body crudo + URI absoluta

#### Criterios de Aceptación
- [x] `composer test` pasa

#### Archivos
- `tests/Http/EditTaskTest.php` — crear
- `tests/Http/DeleteTaskTest.php` — crear

#### Verificación
- [x] `composer test` → 66 tests verdes, 204 assertions

### T7: Crear seed (FR-001)

**Prioridad:** P1  
**Estimación:** 30 min  
**Dependencias:** T1, T2  
**Cubre:** FR-001

#### Trabajo
- [x] Seeder idempotente (tolerante a re-ejecución)
- [x] `database/seeds/TaskSeeder.php` y `DemoTasksSeeder.php`
- [x] Orquestación explícita en `database/seeds/DatabaseSeeder.php` (nunca `seed:run` pelado por accidente)

#### Criterios de Aceptación
- [x] `vendor/bin/phinx seed:run -s DatabaseSeeder` crea tareas de prueba

#### Archivos
- `database/seeds/TaskSeeder.php` — crear
- `database/seeds/DatabaseSeeder.php` — modificar

#### Verificación
- [x] `composer migrate && vendor/bin/phinx seed:run -s DatabaseSeeder` funciona

### T8: Verificar (todos)

**Prioridad:** P0  
**Estimación:** 1 h  
**Dependencias:** T7  
**Cubre:** todos

#### Trabajo
- [x] `composer test` en verde
- [x] Smoke con `curl` (200 donde corresponde, 303/`HX-Redirect` donde la puerta cierra)
- [x] `composer check:termux` detecta correctamente 32-bit
- [x] `vendor/bin/phinx rollback -t 0` + `composer migrate` funciona

#### Criterios de Aceptación
- [x] `composer test` → suite verde (66 tests)
- [x] `curl` smoke tests pasan

#### Verificación
```bash
composer test
composer check:termux
curl -X GET http://localhost:8080/tareas
```

## Estado

- [x] `.status` = `tasks:approved` → `implementation:in-progress` → `implementation:done` → `review:done`
- [x] `.ai/sdd/INDEX.md` actualizado
- [x] `.ai/sdd/handoff/sdd-brief.md` generado
