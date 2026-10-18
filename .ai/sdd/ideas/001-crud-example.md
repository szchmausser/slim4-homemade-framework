# Idea: Framework Base CRUD Example

> Estado: idea:captured
> Creado: 2025-10-08
> Actualizado: 2026-10-18
> Resolución: materializada en `.ai/sdd/PLAN.md` (`plan:approved`) y en la spec `.ai/sdd/specs/001-crud-tareas/` (`review:done`).

## Idea Cruda

Proveer un framework base funcional para desarrolladores Termux que desean crear aplicaciones web modernas (HTML + Twig + HTMX) sin dependencias pesadas ni toolchain de compilación. El ejemplo CRUD sirve como referencia y plantilla para futuras entidades (Tareas, Usuarios, etc.).

## Problema

Los desarrolladores Termux necesitan un punto de partida rápido y confiable para construir apps web modernas (con Twig, HTMX, Alpine.js, daisyUI) sin tener que configurar bundlers, compiladores o dependencias externas complejas. Actualmente, el stack ideal (Slim 4 + Twig + HTMX + Alpine.js + daisyUI + SQLite) está disponible, pero carece de documentación formal y un ejemplo concreto de cómo usarlo.

## Usuario Objetivo

- **Desarrolladores Termux** que quieren crear apps web modernas (landing pages, dashboards, CRUDs) en su propio dispositivo.
- **Equipo pequeño** que necesita un baseline reproducible para prototipar rápidamente.

## Propuesta de Valor

- **Framework mínimo viable**: Slim 4 + Twig + HTMX + Alpine.js + daisyUI + SQLite.
- **Patrón CRUD documentado**: 8 pasos (migración → modelo → controlador → rutas → vistas → tests → seed → verificar).
- **Ejemplo concreto**: Una entidad de ejemplo (tareas) que demuestra el ciclo completo.
- **Sin toolchain**: Todo instalable con `composer install:termux` y sin compilación.

## Preguntas Abiertas (resueltas)

- **Entidad ejemplo:** Tareas (ver `PLAN.md` F01 y `requirements.md` D-001).
- **PLAN.md:** sí, creado y aprobado (`plan:approved`).
- **Requisitos adicionales:** autenticación vía `RequireAuthMiddleware` en el grupo `/tareas` (ver Q-002).