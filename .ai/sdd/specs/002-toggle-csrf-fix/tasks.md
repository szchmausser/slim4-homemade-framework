# Tareas: Fix de CSRF en el toggle de tareas

> Requirements: `@.ai/sdd/specs/002-toggle-csrf-fix/requirements.md`
> Design: `@.ai/sdd/specs/002-toggle-csrf-fix/design.md`
> Status: Approved (completado)
> Última actualización: 2026-10-18

## Tarea 001 — Fix toggle CSRF headers

| Field | Value |
|-------|-------|
| ID | 001 |
| Status | Ready |
| Priority | Alta |
| Due | Hoy |
| Design | `.ai/sdd/specs/002-toggle-csrf-fix/design.md` |
| Requirements | `.ai/sdd/specs/002-toggle-csrf-fix/requirements.md` |

### Archivos
- `resources/views/tasks/_list.twig` (línea ~83)

### Acciones
1. Abrir `_list.twig`
2. Buscar el botón `hx-post="/tareas/{{ task.id }}/toggle"`
3. Añadir `hx-headers="{{ {'csrf_name': csrf_name, 'csrf_value': csrf_value}|json_encode|e('html_attr') }}"` al botón
4. Guardar

### Verificación
- `composer test` debe pasar (suite completa)
- Manual: completar una tarea pendiente → estado cambia a "completado" sin toast error
- Manual: desmarcar una completada → estado vuelve a "pendiente" sin toast error

### Rollback
- Quitar los `hx-headers` añadidos para volver al estado anterior

## Done Criteria
- [x] Toggle funciona sin error CSRF
- [x] Tests verdes
- [x] Smoke test manual pasa