# Tasks: Fix CSRF failure in task toggle (001)

## Tarea 001 — Fix toggle CSRF headers

| Field | Value |
|-------|-------|
| ID | 001 |
| Status | Ready |
| Priority | Alta |
| Due | Hoy |
| Design | `.ai/sdd/design.md` |
| Requirements | `.ai/sdd/requirements.md` |

### Archivos
- `resources/views/tasks/_list.twig` (línea ~83)

### Acciones
1. Abrir `_list.twig`
2. Buscar el botón `hx-post="/tareas/{{ task.id }}/toggle"`
3. Añadir `hx-headers="{{ {'csrf_name': csrf_name, 'csrf_value': csrf_value}|json_encode|e('html_attr') }}"` al botón
4. Guardar

### Verificación
- `composer test` debe pasar (suite 65 tests)
- Manual: completar una tarea pendiente → estado cambia a "completado" sin toast error
- Manual: desmarcar una completada → estado vuelve a "pendiente" sin toast error

### Rollback
- Quitar los `hx-headers` añadidos para volver al estado anterior

## Done Criteria
- [x] Toggle funciona sin error CSRF
- [x] Tests verdes
- [x] Smoke test manual pasa