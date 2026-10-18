# Plan: CRUD de Tareas (Ejemplo)

> Status: Approved

## Vision

Proveer un ejemplo completo de CRUD en el framework Slim 4 para Termux, demostrando el ciclo completo de 8 pasos (migración, modelo, controlador, rutas, vistas, tests, seed, verificar) como guía para futuras entidades.

## Steering Context

- Product: @.ai/steering/product.md
- Tech Stack: @.ai/steering/tech-stack.md
- Conventions: @.ai/steering/conventions.md
- Principles: @.ai/steering/principles.md

## Personas

- **Desarrollador Termux:** Quiere un punto de partida rápido y confiable para crear CRUDs en su dispositivo sin toolchain de compilación.

## MVP Boundary

### Must Have

- Entidad de ejemplo (Tareas) con migración, modelo, controlador, rutas, vistas, tests, seed y verificación.
- Documentación clara del ciclo CRUD (como en el README).

### Won't Have Yet

- Autenticación avanzada.
- API externa.
- Integración con servicios externos.

## Feature Map

### Phase 1 — MVP (Must Have)

| ID | Feature | Module | Description |
|----|---------|--------|-------------|
| F01 | CRUD de Tareas | app/ | Ejemplo completo de CRUD con migración, modelo, controlador, rutas, vistas, tests y seed. |

### Phase 2 — Essentials (Should Have)

| ID | Feature | Module | Description |
|----|---------|--------|-------------|
| F02 | Autenticación básica | app/Http/Middleware | Middleware de sesión y CSRF para rutas protegidas. |

### Phase 3 — Nice to Have (Could Have)

| ID | Feature | Module | Description |
|----|---------|--------|-------------|
| F03 | API JSON | app/Http/Controllers | Endpoint JSON para consumos externos. |

## Dependencies

```mermaid
flowchart LR
    F01[CRUD Tareas] --> F02[Autenticación]
    F02 --> F03[API JSON]
```

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Migraciones con timestamps saturan int32 | Medium | Renombrar migraciones a numeración secuencial (004, 005...) |
| Tests flaky por entorno Termux | Medium | Asegurar que los tests usen body raw + URI absoluta |
| Falta de ejemplos en documentación | High | Crear este ejemplo como referencia oficial |

## Open Decisions (resueltas)

- **Entidad de ejemplo:** Tareas (F01) — resuelta en `requirements.md` D-001.
- **Autenticación en MVP:** sí, el grupo `/tareas` usa `RequireAuthMiddleware` — resuelta en `requirements.md` Q-002.

## Next Step

Crear requirements.md para la entidad Tareas y luego design.md, tasks.md, etc.