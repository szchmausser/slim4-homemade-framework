# IDEA: Fix CSRF failure in task toggle (001)

## Feature
Bug fix: Toggle button fails CSRF check, showing false "session expired" toast.

## Status
Investigated: root cause found (missing `hx-headers` in toggle button).

## Path
`.ai/sdd/IDEA.md`
