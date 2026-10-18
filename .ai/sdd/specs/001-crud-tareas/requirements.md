# Requisitos: CRUD de Tareas (Ejemplo)

> Spec: `.ai/sdd/specs/001-crud-tareas/`
> Estado: Approved
> Última actualización: 2026-10-18

## Overview

Ejemplo de CRUD completo en el framework Slim 4 para Termux. Sirve como referencia para desarrollar nuevas entidades siguiendo el patrón documentado en el README (migración → modelo → controlador → rutas → vistas → tests → seed → verificar).

> Nota de nomenclatura: el dominio de negocio se llama **Tareas** y la URL pública es `/tareas`. Los identificadores técnicos (`Task`, tabla `tasks`, columnas `title`/`done`/`status`) están en inglés por convención del framework. Ver `design.md`.

## Contexto de Negocio

- **Usuario objetivo:** Desarrolladores Termux que quieren un baseline reproducible.
- **Propósito:** Demostrar el ciclo CRUD completo y documentar el patrón.
- **Valor:** Evita la recreación manual de cada CRUD.

## Historias de Usuario

### US-001: Listar Tareas

**Como** un desarrollador que prueba el framework  
**Quiero** ver una lista paginada de tareas  
**Para que** pueda navegar y seleccionar tareas

**Criterios de Aceptación:**
- [ ] La vista lista tareas paginadas (no más de N por página)
- [ ] Cada tarjeta muestra título, estado y botones de editar/borrar/completar
- [ ] El listado requiere sesión (`RequireAuthMiddleware`); sin sesión redirige a login

### US-002: Crear una Tarea

**Como** un desarrollador que prueba el framework  
**Quiero** crear una nueva tarea desde un form HTMX  
**Para que** pueda agregar datos a la base de datos

**Criterios de Aceptación:**
- [ ] El form HTMX envía una petición POST a `/tareas`
- [ ] El form HTMX resetea al enviar
- [ ] La creación se refleja inmediatamente en la lista (swap HTMX)
- [ ] Si hay datos inválidos, se muestran los errores en el form

### US-003: Editar una Tarea

**Como** un desarrollador que prueba el framework  
**Quiero** editar los datos de una tarea existente  
**Para que** pueda corregir errores

**Criterios de Aceptación:**
- [ ] El botón editar abre un form HTMX con los datos actuales
- [ ] El form envía una petición PUT a `/tareas/{id}`
- [ ] Los cambios se reflejan inmediatamente en la lista

### US-004: Borrar una Tarea

**Como** un desarrollador que prueba el framework  
**Quiero** eliminar una tarea existente  
**Para que** pueda limpiar datos de prueba

**Criterios de Aceptación:**
- [ ] El botón borrar envía una petición DELETE con `hx-headers`
- [ ] La tarea se elimina y se actualiza la lista
- [ ] Si la tarea no existe, se avisa (nunca 404 mudo ni 500 silencioso)

### US-005: Marcar una Tarea como completada

**Como** un desarrollador que prueba el framework  
**Quiero** alternar el estado de completada de una tarea  
**Para que** pueda ver el ciclo de vida de una tarea

**Criterios de Aceptación:**
- [ ] La acción envía una petición POST a `/tareas/{id}/toggle` con `hx-headers`
- [ ] El estado (`done`/`status`) se alterna y se refleja en el swap
- [ ] Si la tarea no existe, se avisa (nunca 404 mudo ni 500 silencioso)

## Requisitos Funcionales

### FR-001: Listado de tareas — Must Have

WHEN un usuario autenticado accede a `/tareas`  
IF la sesión es válida  
THEN el sistema SHALL devolver una lista paginada de tareas  
SO THAT el usuario puede navegar por sus tareas

### FR-002: Crear tarea — Must Have

WHEN el usuario envía el form HTMX  
IF todos los campos son válidos  
THEN el sistema SHALL crear la tarea y devolver el HTML del swap  
SO THAT la nueva tarea aparece en la lista

### FR-003: Editar tarea — Must Have

WHEN el usuario envía el form HTMX de edición  
IF todos los campos son válidos  
THEN el sistema SHALL actualizar la tarea y devolver el HTML del swap  
SO THAT los cambios se reflejan inmediatamente

### FR-004: Borrar tarea — Must Have

WHEN el usuario envía la petición DELETE  
IF la tarea existe  
THEN el sistema SHALL eliminar la tarea y devolver el HTML del swap  
SO THAT la lista se actualiza

### FR-005: Validación — Must Have

WHEN el usuario envía datos inválidos  
IF los campos no cumplen las reglas de validación  
THEN el sistema SHALL devolver el form con errores  
SO THAT el usuario puede corregir los datos

### FR-006: Tareas inexistentes — Must Have

WHEN el usuario solicita una tarea inexistente  
IF la tarea no existe en la base de datos  
THEN el sistema SHALL mostrar un mensaje de error apropiado  
SO THAT el usuario sabe que la tarea no existe

### FR-007: Toggle de completada — Must Have

WHEN el usuario activa el toggle de una tarea  
IF la tarea existe  
THEN el sistema SHALL alternar el estado de completada y devolver el HTML del swap  
SO THAT la lista refleja el nuevo estado

## Requisitos No Funcionales

### NFR-001: Rendimiento
- El listado debe ser paginado (nunca `get()` pelado).

### NFR-002: HTMX-first
- Las respuestas HTMX deben devolver HTML de región (nunca redirects 303).
- Los forms deben usar `hx-headers` para el token CSRF.

### NFR-003: Testing
- Cada endpoint debe tener al menos un test (crea-OK, validación rechazo, id inexistente).
- `tearDown` debe limpiar los datos de prueba.

## Fuera de Alcance

- Autenticación avanzada (roles, permisos granulares).
- API externa.
- Notificaciones en tiempo real.

## Decisiones

### D-001: Entidad ejemplo — Tareas

**Decisión:** Usar `Tareas` como entidad de ejemplo porque es una entidad con datos simples que permite probar todas las operaciones CRUD.

**Motivo:** Permite demostrar el patrón completo con la menor complejidad.

**Fuente:** Decisión directa del usuario

**Afecta:** requirements.md, todos los archivos del CRUD

### D-002: Modelo de datos

**Decisión:** La tabla `tasks` tendrá: `id`, `title` (string, máx. 120), `done` (boolean, default `false`), `status` (string, default `pendiente`), `completed_at` (timestamp nullable), `created_at` y `updated_at`.

**Motivo:** Cumple con el patrón estándar de Phinx (`addTimestamps()`) y cubre el ciclo de vida de la tarea (pendiente/completada).

**Fuente:** Convenciones del proyecto + implementación verificada

**Afecta:** design.md, tasks.md

## Preguntas

### Q-001: ¿Debe el CRUD incluir validación de campos?

**Estado:** closed  
**Por qué importa:** Afecta la cantidad y complejidad de los tests.

| Opción | Respuesta | Elegir si... | Impacto |
|--------|-----------|--------------|---------|
| A | Sí, validación de campos obligatorios | Si queremos demostrar validación | Tests de validación |
| B | No, sin validación | Si el CRUD es solo un esqueleto | Tests mínimos |

**Resuelta:** A — se implementó validación (FR-005).

### Q-002: ¿Debe el CRUD tener autenticación?

**Estado:** closed  
**Por qué importa:** Afecta los tests de rutas protegidas.

| Opción | Respuesta | Elegir si... | Impacto |
|--------|-----------|--------------|---------|
| A | Sí, con RequireAuthMiddleware | Si es el objetivo final | Tests de login |
| B | No, sin autenticación | Si es solo un ejemplo público | Tests sin login |

**Resuelta:** A — el grupo `/tareas` usa `RequireAuthMiddleware`.
