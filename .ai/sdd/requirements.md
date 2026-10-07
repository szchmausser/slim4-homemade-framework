# Requirements: Fix CSRF failure in task toggle (001)

## User Stories

**US-001:** Como usuario, quiero poder marcar una tarea como completada (o desmarcarla) para que su estado cambie sin errores ni mensajes confusos.

## Acceptance Criteria

| ID | Criterio | Tipo |
|----|----------|------|
| AC-001 | Al clickear "Completar" en una tarea pendiente, la tarea cambia a "completado" sin mostrar toast de error | Funcional |
| AC-002 | Al clickear "Desmarcar" en una tarea completada, la tarea cambia a "pendiente" sin mostrar toast de error | Funcional |
| AC-003 | El panel de tareas se actualiza vía HTMX (swap outerHTML en #tareas-panel) | Técnico |
| AC-004 | No aparece el mensaje "Tu sesión expiró. Recargá la página." | UX |

## Non-Functional Requirements

| ID | Requisito | Valor |
|----|-----------|-------|
| NFR-001 | Tiempo de respuesta < 300ms | Performance |
| NFR-002 | Compatible con existing HTMX + daisyUI patterns | Maintainability |

## Edge Cases

| ID | Escenario | Comportamiento Esperado |
|----|-----------|------------------------|
| EC-001 | Task ID inexistente | Flash error "La tarea no existe o ya fue eliminada." en el panel |
| EC-002 | Sesión realmente expirada (token inválido) | 400 + HX-Trigger toast (comportamiento actual correcto) |
| EC-003 | Múltiples clicks rápidos | Cada toggle invierte el estado sin duplicar requests |

## Out of Scope

- Cambiar `persistentTokenMode` en container.php
- Modificar el mensaje del toast CSRF
- Agregar headers a otros endpoints (solo toggle)

## Definition of Done

- [ ] Botón toggle funciona sin error CSRF
- [ ] Tests pasan (composer test en verde)
- [ ] Smoke test manual: completar + desmarcar tarea funciona