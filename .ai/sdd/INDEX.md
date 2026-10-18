# Índice SDD

> Estado: Activo
> Idioma: Español
> Última actualización: 2026-10-18

## Steering

| Documento | Ruta | Estado | Notas |
|-----------|------|--------|-------|
| Producto | `.ai/steering/product.md` | ready | Visión, personas, límites, métricas, glosario |
| Stack | `.ai/steering/tech-stack.md` | ready | Runtime, frameworks, verificación, restricciones |
| Convenciones | `.ai/steering/conventions.md` | ready | Estilo, arquitectura, testing, workflow |
| Principios | `.ai/steering/principles.md` | ready | P-001..P-006 (MUST/SHOULD/MAY) |
| Arquitectura de capas | `.ai/steering/lp.md` | ready | Layered PHP / Termux: capas, patrones, consideraciones 32-bit |

## Handoffs Upstream

| Artefacto | Ruta | Estado | Notas |
|-----------|------|--------|-------|
| Strategy Brief | `.ai/strategy/handoff/strategy-brief.md` | ausente | Entrada opcional para IDEA, PLAN o PRD |

## Plan

| Artefacto | Ruta | Estado | Notas |
|-----------|------|--------|-------|
| Plan | `.ai/sdd/PLAN.md` | plan:approved | MVP CRUD Tareas + fases F02 (auth) y F03 (API JSON) |

## Ideas

| ID | Nombre | Estado | Ruta | Recomendación |
|----|--------|--------|------|---------------|
| 001 | Framework base CRUD example | idea:captured | `.ai/sdd/ideas/001-crud-example.md` | Materializada en `PLAN.md` + spec 001 |
| 002 | Fix de CSRF en el toggle de tareas | idea:captured | `.ai/sdd/ideas/002-toggle-csrf-fix.md` | Materializada en spec 002 |

## Feature Workspace

> Fuente de numeración: directorios reales bajo `.ai/sdd/specs/`, no este índice.

| Campo | Valor | Notas |
|-------|-------|-------|
| Próximo Feature ID | 003 | Recalcular desde el filesystem antes de crear una spec |
| Problemas de numeración | none | Sin prefijos duplicados, status inválidos ni directorios no canónicos |

## Specs

| ID | Feature | Estado | Requirements | Design | Tasks | Review | Próxima acción |
|----|---------|--------|--------------|--------|-------|--------|----------------|
| 001 | CRUD de Tareas (ejemplo) | review:done | requirements.md | design.md | tasks.md | review.md | Cerrar; nueva entidad vía `/skill:sdd-prd` |
| 002 | Fix de CSRF en el toggle de tareas | review:done | requirements.md | design.md | tasks.md | review.md | Cerrar; fix mergeado |

## Handoff Output

| Artefacto | Ruta | Estado / Readiness | Notas |
|-----------|------|--------------------|-------|
| SDD Brief (spec 001) | `.ai/sdd/handoff/sdd-brief.md` | ready for release | Generado tras `review:done` de la spec 001 |

## En Progreso

| Feature | Tarea | Estado | Owner/Agent | Blocker |
|---------|-------|--------|-------------|---------|
| — | — | — | — | — |

## Bloqueado / Atención

- Las dos spec están en `review:done`; no hay gates pendientes.
- `requirements.md`, `design.md` y `tasks.md` de la spec 001 fueron reconciliados con el código real (`Task` / `tasks` / `title` / `done` / `status` / `completed_at`) el 2026-10-18.
- La spec 002 fue reubicada desde una estructura plana previa (`.ai/sdd/<artefacto>.md`) a `specs/002-toggle-csrf-fix/` para unificar el layout canónico.

## Próximas Acciones

- [ ] Nueva entidad: `/skill:sdd-idea` → `/skill:sdd-plan` (opcional) → `/skill:sdd-prd`
- [ ] Verificar tras cualquier cambio: `composer test` en verde + `vendor/bin/phinx rollback -t 0 && composer migrate`
- [ ] Refrescar `.ai/sdd/handoff/sdd-brief.md` tras el próximo `review:done`
