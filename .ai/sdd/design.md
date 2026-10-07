# Design: Fix CSRF failure in task toggle (001)

## Architecture Decision
No architecture change required. This is a UI/UX fix to correctly implement HTMX request headers.

## Data Flow

```
[User Clicks Toggle Button] 
        ↓
[HTMX hx-post to /tareas/{id}/toggle WITH CSRF Headers] 
        ↓ 
[CSRF Guard Validates Token → Calls Next Handler]
        ↓
[TaskController::toggle() Updates DB Status]
        ↓
[Returns Rendered #tareas-panel via Twig] 
        ↓
[HTMX Swaps outerHTML of #tareas-panel]
        ↓
[UI Updated, No Error Toast]
```

## Component Changes

### File: `resources/views/tasks/_list.twig`
**Location:** Botón toggle (línea ~83)

**Before:**
```html
<button
    hx-post="/tareas/{{ task.id }}/toggle"
    hx-target="#tareas-panel"
    hx-swap="outerHTML"
    class="btn btn-ghost btn-sm {{ task.status == 'completado' ? 'text-success' : '' }}">
    {{ task.status == 'completado' ? 'Desmarcar' : 'Completar' }}
</button>
```

**After:**
```html
<button
    hx-post="/tareas/{{ task.id }}/toggle"
    hx-target="#tareas-panel"
    hx-swap="outerHTML"
    hx-headers="{{ {'csrf_name': csrf_name, 'csrf_value': csrf_value}|json_encode|e('html_attr') }}"
    class="btn btn-ghost btn-sm {{ task.status == 'completado' ? 'text-success' : '' }}">
    {{ task.status == 'completado' ? 'Desmarcar' : 'Completar' }}
</button>
```

## Error Handling
- **No change needed**: Existing CSRF failure handler remains correct
- Real CSRF failures (token missing/expired) will still show the toast
- This fix only prevents false positives from missing headers

## Security Considerations
- **No security degradation**: Headers are identical to delete button usage
- Token remains session-bound (persistentTokenMode: true)
- HTMX request still validates origin/same-site implicitly via Slim CSRF

## Open Questions
- Consider applying same fix to other HTMX endpoints if missing headers
- Review if any other buttons (edit form submit, etc) lack headers