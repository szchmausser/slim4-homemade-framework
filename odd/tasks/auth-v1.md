# Feature: auth-v1

## Objective
Login + logout + remember-me (split-token con rotación) + usuario inicial por seed/CLI. Sin registro público, sin reset por email.

## Problem
App sin auth: cualquiera con la URL opera. En este stack (HTMX + server-render, sin build) el fit natural es sesión+cookie, no JWT ni OAuth.

## Why
Usuario confirmó el alcance. Remember-me exigido con diseño seguro (no checkbox ingenuo).

## Scope
- Migraciones: `users`, `remember_tokens` (selector PK, hashed_validator bcrypt, expires_at unix, FK cascade).
- Modelos `User`, `RememberToken`; `Auth` (sesión), `RememberMe` (tokens+cookies), `CsrfTokens` (helper compartido closure/controladores).
- Middlewares: `RememberMeMiddleware` (global, tras sesión), `RequireAuthMiddleware` (solo grupo /tareas; HX→200+HX-Redirect, normal→303).
- `AuthController`: show/store/logout; login form normal (no HX); logout POST (CSRF); regeneración de sesión al entrar/salir; rehash si cambia el algoritmo.
- Rutas: GET/POST /login (públicas), POST /logout, grupo /tareas protegido. `/` pública + muestra usuario/salir en layout.
- Vistas: `auth/login.twig` + bloque usuario/salir en sidebar (requiere csrf+user en layout: base render + cierre `/`).
- Seed: `scripts/seed-admin.php` + `composer db:seed` (ADMIN_EMAIL/PASSWORD, valida min:8, nunca imprime el secret).
- Tests `AuthTest`: login ok/ko, sesión, remember set/resume/rotate/tamper/expire/logout, guard normal+HX, seed indirecto vía modelo.
- Fuera de alcance: registro público, reset email, throttling (riesgo aceptado: sin registro + app personal; documentar), roles, guía (pendiente próximo paso).

## Constraints
- Reglas de seguridad: hash bcrypt a tokens y passwords; `hash_equals` innecesario con password_verify (timing-safe); split-token; rotación; quema total ante mismatch; HttpOnly+Lax; secure=false como sesión (sin TLS); logout quema todo; re-login regenera.
- Sin commits/push hasta que lo pida. Subagentes no disponibles: inline.
- Contratos existentes intactos (panel, CSRF, HX-Request branch).

## Authorized scope
Diseño + implementación + verificación. Commit/push solo si lo pide.

## Acceptance criteria
- [ ] Flujo completo por curl con cookie-jar: login malo→200+flash; login bueno→303+tareas 200; sin sesión→303 (HX→HX-Redirect); logout→303 y pierde acceso; remember resume/rota/rechaza-tamper/expirado.
- [ ] Suite verde (nuevos tests incluidos).
- [ ] Seed crea/actualiza admin sin exponer secret.

## Applicable checks
- `vendor/bin/phpunit`, smoke curl con `-c/-b`, `phinx migrate` con las 3 migraciones.

## TDD resolution
- Mode: off/unknown. Runner: `vendor/bin/phpunit`.

## Delivery strategy
- `ask-on-risk`. Estimado ~400 líneas authored + 2 migraciones + tests. Sin slicing previsto.

## Tasks
- [x] T1 — schema+modelos+servicios (migraciones, User, RememberToken, Auth, RememberMe, CsrfTokens)
- [x] T2 — middlewares+container+rutas+AuthController+vistas+layout+seed (bugs propios: Guard eager en `/`, corregido a lazy)
- [x] T3 — tests AuthTest (11) + migración de 4 archivos viejos a entrar logueados
- [x] T4 — verificación: suite 49/154 verde + seed real + smoke cookie-jar completo

## Progress
- 2026-09-28: T1-T4 done + guía con auth (3210+ líneas). Ajustes: nota PHP≥8.2 (fallback rancio eliminado), 11.1 restaurada (había quedado duplicado el rollback sin contenido), rechazo documentado de credenciales hardcodeadas. Todo en `feat/auth-v1`, SIN commit por preferencia del usuario.

## Verification evidence
- Suite: OK (49 tests, 154 assertions).
- Seeds: `seed:run -s DatabaseSeeder` → admin + 3 demo (idempotente en re-runs); script suelto eliminado a favor de seeders Phinx.
- Smoke HTTP: login malo→200+flash genérico; login bueno→303+session+remember(HttpOnly/Lax/Max-Age); /tareas con jar→200; logout→303+quema+Max-Age=0; post-logout→303 /login; HX sin sesión→200+HX-Redirect.
- Phinx 0.13: rollback/migrate/status OK con las 3 migraciones.

## Rationale
- Sesión+cookie (no JWT): calza con HTMX server-render; JWT pelearía con el stack.
- Sin registro público: achica superficie (enumeración, spam) en app personal.
- Servicio RememberMe concentrado: el código security-critical vive en un archivo revisable.
- `active` y demás contratos no se tocan.

## 2026-09-28 (pm)
- DatabaseSeeder + TaskSeeder + guía 11.2/17.5; suite 49/154 verde; sin commit.
