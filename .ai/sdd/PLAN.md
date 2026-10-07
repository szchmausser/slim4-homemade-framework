# Plan: Fix CSRF failure en toggle de tarea (001)

## Atajos Rápidos

| Día | Actividad | Logrado |
|-----|----------|---------|
| 1 | Investigar el bug (HSRQ missing) | ✅ Sí 
| 2 | Editar `resources/views/tasks/_list.twig` | 
| 3 | Testear el fix | 

## Dependencias
- Ninguna (standalone)

## Riesgos / Notas
- `persistentTokenMode: true` podría ser redundante si el token va en cada request.
- Cambiar este flag podría afectar otros endpoints.
- El toggle está implementado en este proyecto desde un principio, puede que sea el diseño intencional.

## Tareas

### Tarea 001: Añadir headers CSRF al botón toggle
- ID: 001
- Prioridad: Alta (bloquea la funcionalidad principal)
- Dueño: Dev
- Comienzo: Hoy
- Fin: Hoy
- Verificación: El usuario puede completar/descompletar tareas sin error CSRF

#### Paso a paso
1. Abrir `resources/views/tasks/_list.twig` línea 83
2. Añadir `hx-headers="{{ {'csrf_name': csrf_name, 'csrf_value': csrf_value}|json_encode|e('html_attr') }}"` al botón `hx-post="/tareas/{{ task.id }}/toggle"`
3. Guardar archivo
4. Probar: completar una tarea desde la vista (esta vez sin el error "Sesión expirada")

### Tarea 002 (Opcional): Asegurar que la variable CSP está disponible
- Revisar si `csrf_name` y `csrf_value` son pasados al template `tasks/_panel.twig`
- Si no, agregarlas en `Controller::render`
- No necesario para este fix (el panel usa los mismos _csrf fields que delete)