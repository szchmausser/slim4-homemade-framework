# Feature: guia-resync

## Objective
Re-sincronizar `docs/guia-stack-php-termux.md` con el codebase actual para que sea replicable paso a paso en Termux con el mismo resultado.

## Problem
La guía quedó desactualizada: CDN vs assets vendorizados, layout artesanal vs shell daisyUI, TaskController plano vs base+helpers, sin `.env.example`, tests 37/89 vs 38/94, valores del Nivel A viejos.

## Why
Usuario lo pidió explícito: sin ajuste, el sistema no levanta igual allá.

## Scope
- Reescritura de la guía: todos los archivos con código copiable, comandos Termux, valores medidos frescos.
- Incluye binarios (assets) vía comandos de descarga + SRI, no pegando bytes.
- Fuera de alcance: cambiar código; solo documentación.

## Constraints
- Sin commits hasta que el usuario lo pida. Todo en working tree.
- Fidelidad: el código de la guía debe ser byte-compatible con el repo (verificado por lectura directa, no de memoria).

## Authorized scope
Reescribir la guía. Push/commits solo cuando él lo pida.

## Acceptance criteria
- [ ] Cada archivo trackeado (menos binarios) aparece con su código completo.
- [ ] Capítulos viejos (CDN, layout, controller, env, tests, Nivel A) actualizados.
- [ ] Valores medidos frescos (bytes, headers, suite).

## Applicable checks
- `git ls-files` vs menciones en la guía (cruce).
- Suite + smoke para valores.

## TDD resolution
- N/A (documentación). Runner de verificación: `vendor/bin/phpunit` + `curl`.

## Delivery strategy
- `ask-on-risk`. Un archivo doc. Sin slicing.

## Tasks
- [x] T1 — relevar fuentes exactas + medir valores frescos
- [x] T2 — reescribir la guía por partes (3073 líneas, 23 capítulos)
- [x] T3 — cruce final ls-files vs guía (limpio tras agregar nombre de migración)

## Progress
- 2026-09-28: T1-T3 done. Guía reescrita en working tree, SIN commit por preferencia del usuario.
