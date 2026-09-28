# Feature: pagination-backup-readme

## Objective
Paginación Laravel-style reutilizable (10 por defecto, controles, contadores) + seeder de 100 + backup + README con receta CRUD. Diferidos: roles, throttling.

## Problem
`get()` sin límite en todas las acciones (la propia guía lo advierte); sin receta CRUD el framework no se replica; sin backup un teléfono perdido es data perdida.

## Why
Usuario lo pidió explícito (2, 4, 5 + seeder 100 + paginación reutilizable daisyUI).

## Scope
- `illuminate/pagination` si falta (verificar subtree 64bit-free).
- `App\Support\Pagination`: from-paginator (total/per_page/page/last/from/to/base_url), window con ellipsis, clamp de per_page.
- `partials/_pagination.twig`: join prev/números/next + selector tamaño + contador "Mostrando X al Y de Z"; params: pagination, target, swap?, show_size?.
- TaskController: pageData() por request (tasks+pagination); viewDefaults vuelve a [] (se elimina el override).
- `DemoTasksSeeder` (100, firstOrCreate por título, NO en DatabaseSeeder).
- `scripts/db-backup.php` + `db:backup` (copia timestamped sqlite+wal+shm a storage/backups/).
- `README.md`: proyecto + receta CRUD en N pasos + comandos.
- Tests: `PaginationTest` (unit: window/clamp/vacío) + HTTP (página 2, per_page, inválido). Suite vieja debe pasar igual.
- Guía: 11.2 (DemoTasks), 16.x (paginación), 17.x (backup), 20.x (PaginationTest), 23 (README, backup script), índice.
- Fuera: roles, throttling, JSON API, i18n.

## Constraints
- Contratos HTMX intactos (panel swap, HX-Request branch, csrf, editing).
- Sin commits hasta que lo pida. Subagentes no disponibles: inline.
- Partial portable: sin ids ni rutas hardcodeadas (todo por params).

## Authorized scope
Implementar + verificar. Commit/push solo si lo pide.

## Acceptance criteria
- [ ] 100 tareas → 10 páginas; pág 2 trae las viejas; per_page 25/50 respeta; inválido cae a default; contadores exactos.
- [ ] Partial reusable sin cambios para otra ruta (params).
- [ ] Backup timestamped con los 3 archivos cuando existen.
- [ ] README con receta usable.
- [ ] Suite verde.

## Applicable checks
- `vendor/bin/phpunit`, smoke curl (page=2, per_page), `db:backup`, lock-grep 64bit si se agrega dep.

## TDD resolution
- Mode: off/unknown. Runner: `vendor/bin/phpunit`.

## Delivery strategy
- `ask-on-risk`. Mediano (~300 líneas + tests + README + guía). Sin slicing.

## Tasks
- [ ] T1 — backend paginación (dep?, support, controller, partial, DemoTasksSeeder)
- [ ] T2 — tests (PaginationTest + HTTP)
- [ ] T3 — backup + README
- [ ] T4 — guía + verificación final

## Progress
- 2026-09-28: T1-T4 done. Paginación (sin dep nueva: illuminate/pagination pedía php^8.3), DemoTasksSeeder, backup, README, guía (16.1, 17.6, 20.10-11, 22.1, 23, fences). Suite 65/200 + smoke 100 filas + cruce limpio. Todo SIN commit por preferencia del usuario.
