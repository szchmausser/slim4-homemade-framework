# Feature: termux-hardening-fixes

## Objective
Corregir los 3 puntos donde muerde en Termux 32 bits: OOM de Composer, `DB_DATABASE` absoluto frágil, CDNs que parecen "Tailwind roto" sin red.

## Problem
- `composer update` en armv7l muere por OOM killer (exit 137) sin mensaje PHP.
- `DB_DATABASE` absoluto + `PRAGMA` antes del error middleware = `QueryException` cruda, sin página bonita. Repo clonado no trae `storage/`, `database.sqlite` ni `.env`.
- Frontend 100% CDN: sin internet parece que Alpine/Tailwind no andan, sin error visible.

## Why
Los tres son el hueco entre "anda en mi máquina" y "replicable en Termux siguiendo la guía". La guía los documenta (caps 17/20) pero el codebase no los mitiga.

## Scope
- `composer.json` scripts low-mem + doc mínima de flujo 32-bit.
- `config/container.php` + `bootstrap/app.php`: default relativo a `__DIR__`, override por env, autocreación de dirs/archivo, error accionable antes del PRAGMA.
- `public/assets/` vendorizados (tailwind browser 4.3.3, htmx 2.0.11, alpine 3.17.4) + `resources/views/layouts/app.twig` sirviendo local con SRI conservado. Sin fallback CDN->local automático.
- Fuera de alcance: CSP, compilar Tailwind a CSS estático, túnel/HTTPS, glibc CLI.

## Constraints
- Mantener pins: `respect/validation:^2.0`, SRI hashes existentes.
- Tests verdes: `vendor/bin/phpunit` (37 tests aprox). Sin vendor instalado en este clon, instalar para verificar.
- No romper contratos testeados: `persistentTokenMode:true`, orden middlewares, `rewind()`, `hx-headers` en DELETE, `HX-Request` branch, `editing_id`, `$event.detail.value`.
- Heurística ~400 líneas authored por task (advisory, no cap). Forecast total <100 líneas authored + 3 assets vendorizados (excluidos como generados).

## Authorized scope
Usuario dijo "corrijamos los 3 puntos" — autoriza mutación local en esta feature. Push/PR/merge = decisión del usuario bajo política ordinaria del repo. Sin ejecución remota.

## Acceptance criteria
- [ ] `composer install --prefer-dist --no-dev --no-scripts` documentado y scripteado con `COMPOSER_MEMORY_LIMIT=1024M` (no `-1`).
- [ ] Clon fresco sin `.env`/`storage`/sqlite levanta con mensaje accionable o autocrea, nunca `QueryException` cruda por path inexistente.
- [ ] `GET /tareas` funciona offline (assets locales), SRI intacto, listener `@csrf.window` intacto.
- [ ] Suite phpunit verde, `curl -sI` 404 con headers + 507 bytes aprox, `set-cookie` con HttpOnly+SameSite=Lax.

## Applicable checks
- `vendor/bin/phpunit` (requiere `composer install` previo)
- `php -S 127.0.0.1:8080 -t public` + `curl -sI http://127.0.0.1:8080/no-existe` + `curl -s .../tareas | wc -c`
- Structural readback (diff review) por task. Sin RDD (switch usuario, no activar por default).

## TDD resolution
- Mode: off/unknown (source: sin config explícita; presencia de phpunit NO habilita TDD por regla ODD). Runner: `vendor/bin/phpunit` (pendiente `composer install`).
- Enfoque: checks funcionales ordinarios por task, no RED/GREEN ceremonial.

## Delivery strategy
- `ask-on-risk` (default). Forecast <400 authored, running count desde work-unit commits. Sin slicing previsto. Slices/PR boundaries se registran acá si el conteo supera.

## Tasks
- [x] T1 — composer-lowmem: scripts + doc flujo 32-bit (route: inline, 1 file) — commit 2bc63bd
- [x] T2 — db-path-resiliente: default relativo + autocreación + error accionable en container/bootstrap/phinx (route: inline, 1 file por commit) — commits 0140afa, de79d59, 0483797, 6980c3f
- [x] T3 — assets-locales: descargar 3 JS a public/assets + layout a local con SRI + .gitattributes binary (route: inline) — commits f95c905, 7e7cef0
- [x] T4 — verificación final: install + phpunit + smoke curl, cerrar doc (route: inline checks)

## Progress
- 2026-09-28: T1-T4 done en `feat/termux-hardening-fixes`. Pendiente: decisión push/PR del usuario.

## Verification evidence
- `vendor/bin/phpunit`: OK (37 tests, 89 assertions) — commits 0140afa..6980c3f, runner PHP 8.5.5
- `curl -sI /no-existe`: 404 + nosniff/DENY/strict-origin-when-cross-origin + Set-Cookie HttpOnly SameSite=Lax
- `curl -s /no-existe | wc -c`: 507 (igual guía)
- `curl -s /tareas`: 200, 2066 bytes (guía decía 2273 con CDN; baja por srcs locales más cortos + sin crossorigin — esperado, no 0), `csrf.window` count 1, 3x `/assets/*` presentes, `/assets/htmx` 200 52182
- `GET /`: 200
- SRI local verificado: sha384 de los 3 assets coincide byte a byte con la guía
- Bug propio atrapado por smoke: container calculaba `$db` pero usaba `$_ENV` directo (fix 0483797); phinx pedía `.env` (fix 6980c3f)
- Running authored count: ~60 líneas + 3 assets generados (excluidos). Sin slicing (ask-on-risk, bajo 400).

## Delivery
- Estrategia: ask-on-risk, sin split. Branch `feat/termux-hardening-fixes` con 7 work-unit commits. Push/PR/merge = usuario.

## Verification evidence
- (se anexa por task: comando + resultado observado + commit id)

## Next step
- Crear branch `feat/termux-hardening-fixes`, luego T1.

## Rationale (cambios aceptados)
- Rutina: se registran junto a su task, no journal exhaustivo.
