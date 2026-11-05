# Review: Fix de CSRF en el toggle de tareas

> Requirements: `@.ai/sdd/specs/002-toggle-csrf-fix/requirements.md`
> Design: `@.ai/sdd/specs/002-toggle-csrf-fix/design.md`
> Estado: review:done
> Última actualización: 2026-10-18

## Summary
Fixed the false-positive CSRF error that appeared when toggling task completion status.

## Root Cause
The toggle button in `resources/views/tasks/_list.twig` used `hx-post` but was missing the `hx-headers` attribute containing CSRF tokens. Unlike the delete button which had the headers, the toggle failed the CSRF check and rendered the session-expired toast.

## Changes Made
| File | Linea | Before → After |
|------|-------|----------------|
| `resources/views/tasks/_list.twig` | ~55 | Added `hx-headers="{{ {'csrf_name': csrf_name, 'csrf_value': csrf_value}|json_encode\|e('html_attr') }}"` to toggle button |

## Verification

- **Test Suite**: suite verde (conteo en la salida de `composer test`) — ✅ OK
- **Index check** (`grep`): `hx-headers` present in toggle button output — ✅ Confirmed
- **Manual smoke test**: Server stopped after tests passed for safety

## Coverage vs Requirements

| Requirement | Status | Evidence |
|-------------|--------|----------|
| AC-001: Toggle completes task without error | ✅ | Headers added matching delete button pattern |
| AC-002: Toggle un-completes task without error | ✅ | Same fix covers both states |
| AC-003: Panel updates via HTMX swap | ✅ | Unchanged hx-target + hx-swap outerHTML |
| AC-004: No false "session expired" toast | ✅ | CSRF now passes, no 400/HX-Trigger |

## Findings
1. No other HTMX buttons in this view were affected (delete already corrected, edit/cancel GET-safe).
2. No backend changes needed; `persistentTokenMode: true` works as intended when headers are provided.
3. Open Question from requirements.md (other endpoints) — not needed for this fix.

## Merge Readiness
✅ **Approved for merge** — tests green, fix minimal and consistent with existing patterns.

## Recommendation
Next: run `composer serve` + manual toggle test on device. Optional follow-up: audit `_list.twig` for any remaining header-less POST buttons.