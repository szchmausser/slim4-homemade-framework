# Idea: Fix de CSRF en el toggle de tareas

> Estado: idea:captured
> Creado: 2026-10-18
> Path: `.ai/sdd/ideas/002-toggle-csrf-fix.md`
> Resolución: spec `.ai/sdd/specs/002-toggle-csrf-fix/` (`review:done`).

## Feature
Bug fix: Toggle button fails CSRF check, showing false "session expired" toast.

## Status
Investigated: root cause found (missing `hx-headers` in toggle button).
