# Feature: env-form-reset

## Objective
Dejar el clon fresco usable sin `.env` a mano, versionar un `.env.example`, y que el form de alta limpie su input después de cada alta.

## Problem
- No hay `.env` ni `.env.example` en el repo: un clon fresco depende del default relativo + safeLoad (funciona) pero sin referencia documentada de variables.
- El form `#alta-tarea` vive fuera de `#tareas-panel`, así que el swap de HTMX no lo toca: después de agregar, el texto queda en el input.

## Why
El usuario lo pidió explícito tras probar `rollback -t 0` + `migrate` (probado OK: down → up).

## Scope
- `.env` local (gitignored, no viaja) + `.env.example` versionado con las 4 variables y nota de Termux.
- `resources/views/layouts`? No. Solo `resources/views/tasks/index.twig`: `hx-on::after-request` + `maxlength="120"` en el input de alta.
- Fuera de alcance: edit inline (el swap lo reemplaza; en fail conserva modo con valor guardado, comportamiento documentado de la guía), seeds, script `migrate:fresh`.

## Constraints
- `.env` nunca se commitea (ya en `.gitignore`). `.env.example` sí.
- No romper contratos testeados: CSRF hiddens, `hx-post/target/swap`, panel como unidad de intercambio.
- `maxlength=120` alinea browser con `max:120` del server (el form de edición ya lo tiene).

## Authorized scope
Usuario autorizó: probar rollback/migrate, generar `.env`, crear `.env.example`, mejora del form. Push/PR/merge = decisión del usuario.

## Acceptance criteria
- [ ] `vendor/bin/phinx rollback -t 0` → down, `migrate` → up (ya verificado).
- [ ] `.env` existe local, `.env.example` commiteado, `git status` no muestra `.env`.
- [ ] Alta exitosa deja el input vacío sin recargar; el swap del panel sigue trayendo la fila + flash.
- [ ] Suite phpunit verde.

## Applicable checks
- `vendor/bin/phpunit`
- `phinx status` antes/después
- Smoke `curl` de `/tareas` (200) y alta manual o via test CSRF existente

## TDD resolution
- Mode: off/unknown (sin config explícita; phpunit presente no habilita TDD). Runner: `vendor/bin/phpunit`.

## Delivery strategy
- `ask-on-risk` (default). Forecast <20 líneas authored. Sin slicing.

## Tasks
- [x] T1 — env: generar `.env` local + crear `.env.example` versionado (route: inline) — commit 06ad670
- [x] T2 — form-reset: `hx-on::after-request` + `maxlength` en `#alta-tarea` (route: inline, 1 file) — commit 60ec2b6
- [x] T3 — verificación: phpunit + smoke, cerrar doc (route: inline checks)

## Progress
- 2026-09-28: T1-T3 done en `feat/env-form-reset`. Pendiente: decisión push/PR del usuario.

## Verification evidence
- phinx: `rollback -t 0` → down, `migrate` → up, `status` final up (2026-09-28).
- `vendor/bin/phpunit`: OK (37 tests, 89 assertions).
- Smoke `/tareas`: 200, `hx-on::after-request` count 1, input alta con `maxlength="120"`. `/`: 200.
- `git check-ignore .env`: ignorado OK. `.env.example` commiteado.
- Running authored count: ~30 líneas. Sin slicing (ask-on-risk, bajo 400).

## Verification evidence
- phinx: `CreateTasksTable: reverted` → status down → `migrated` → status up (2026-09-28).

## Next step
- Crear branch `feat/env-form-reset`, luego T1.

## Rationale
- Alta usa `this.reset()` incondicional (no solo en éxito) porque `store()` responde 200 también con flash de validación; distinguir exigiría inspeccionar el body. El `required` + nuevo `maxlength` del browser ya previenen casi todos los fails de validación en ese form.
- Edit no se toca: en éxito el swap lo elimina, en fail conserva modo (guía).
