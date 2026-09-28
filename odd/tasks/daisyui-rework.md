# Feature: daisyui-rework

## Objective
Rework visual de `/` y `/tareas` sobre daisyUI 5 as-is (temas light/dark por defecto, sin custom): shell con header, sidebar plegable (desktop + móvil), footer, responsive.

## Problem
Layout actual artesanal con slate hardcodeado: sin sistema, sin oscuro, sidebar inexistente. Escalar así duele (decisión del usuario + riesgo de caos visual).

## Why
Usuario eligió daisyUI 5 as-is tras ponderar HyperUI/daisyUI/artesanal. Veredicto propio: tokens + `data-theme` centralizado > snippets para consistencia y oscuro.

## Scope
- Vendorizar `daisyui@X.Y.Z` CSS pineado + SRI en `public/assets/` (offline-first, como htmx/alpine).
- `layouts/app.twig`: shell drawer (sidebar plegable), navbar con toggle tema (Alpine + localStorage), toast CSRF intacto, footer.
- `home.twig`: bienvenida con hero/cards daisyUI (conservar `Slim 4` y `href="/tareas"` por RoutingTest).
- `tasks/index/_panel/_list`: clases daisyUI; conservar ids (`alta-tarea`, `tareas-panel`), attrs hx, parcial `_csrf`, `editing_id/editing_title`, `hx-on` reset, `maxlength`.
- `routes/web.php` + `TaskController::index`: var `active` para nav (home/tareas).
- NINGÚN `dark:` utility: el tema lo gobierna `data-theme`. NINGÚN JS de daisyUI/terceros: comportamiento solo Alpine.
- Fuera de alcance: custom theme, MCP/skill/plugin, guía (no se toca en esta feature).

## Constraints
- Contratos de tests intactos salvo 1: flash pasa a `alert alert-error` → se actualiza su assertion (`bg-red-100` → `alert-error`).
- Sin commits hasta que el usuario lo pida (preferencia registrada). Todo queda en working tree.
- Subagentes no disponibles en este runtime (free tier): ruta inline, un archivo por vez.

## Authorized scope
Usuario autorizó el rework (daisyUI as-is + sidebar plegable desktop/móvil). Push/PR/merge/commits = solo cuando él lo pida.

## Acceptance criteria
- [ ] `/` 200 con shell (header/sidebar/footer) y contenido bienvenida.
- [ ] `/tareas` 200 full + parcial `_panel` sin shell ante `HX-Request`.
- [ ] Sidebar pliega/despliega en desktop y móvil; tema persiste tras recarga.
- [ ] Suite phpunit verde (38 tests incl. el nuevo de `editing_title`).

## Applicable checks
- `vendor/bin/phpunit`
- Smoke `curl`: `/`, `/tareas`, `/assets/daisyui-*.css` 200, `csrf.window` presente.

## TDD resolution
- Mode: off/unknown. Runner: `vendor/bin/phpunit`.

## Delivery strategy
- `ask-on-risk`. Forecast <150 líneas authored + 1 asset generado. Sin slicing.

## Tasks
- [x] T1 — vendor daisyUI: versión exacta + SRI + link en layout (route: inline) — `public/assets/daisyui-5.7.46.css` + SRI sha384 verificado
- [x] T2 — shell: drawer/sidebar + navbar + tema + footer en `app.twig` (route: inline)
- [x] T3 — vistas: home + tasks a daisyUI conservando contratos (route: inline)
- [x] T4 — nav active + ajuste test flash (route: inline) — 1 assertion (`bg-red-100` → `alert-error`)
- [x] T5 — verificación: phpunit + smoke, cerrar doc (route: inline checks)

## Progress
- 2026-09-28: T1-T5 done + ajustes post-rework, commiteado a `main`.
- Ajustes post-rework (también sin commit): container a `max-w-6xl`, hero compacto, bloques inferiores a `h-12` para línea continua, logo genérico (`partials/_logo.twig`) solo en sidebar, iconos compartidos (`partials/_icon.twig`) y header como breadcrumb con texto del menú, pre-pintado del sidebar plegado (script + CSS en `<head>`, Alpine lee el attr del DOM).

## Verification evidence
- `vendor/bin/phpunit`: OK (38 tests, 94 assertions).
- Smoke: `/` 200, `/tareas` 200, `/assets/daisyui-5.7.46.css` 200; shell markers presentes; parcial HX sin shell.
- `is-drawer-*`: 0 ocurrencias en CSS vendorizado → se descartó el drawer plegable de la doc (clases muertas sin pipeline); collapse con Alpine + utilities estándar.
- Running authored count: ~200 líneas + 1 asset generado. Sin slicing.

## Verification evidence
- (se anexa por task)

## Next step
- Resolver versión exacta daisyUI 5 y vendorizar con SRI.

## Rationale
- Drawer daisyUI (checkbox hack) + Alpine solo para persistir estado en localStorage: sin JS de componentes, HTMX-safe.
- `data-theme` en `<html>`; nada de `dark:` utilities.
