# Idea Intake: tutorial de CRUD paso a paso sobre este framework

- **Slug**: tutorial-crud
- **Created**: 2026-11-11
- **Source**: texto pegado en sesión + puntero al repo (este proyecto)
- **Type**: new-capability

## Idea (as captured)

> Crear tutorial sobre como crear un crud paso a paso usando este framework

Argumento de la invocación, conservado aparte: `slug=tutorial-crud`.

Contexto del repo al que la idea apunta (leído, no evaluado):

- `docs/guia-stack-php-termux.md` es una guía paso a paso del **framework en sí** (Termux, Composer, DI, middlewares, auth, migraciones, validación, CSRF, assets con SRI, shell, testing, troubleshooting). Tiene un solo capítulo de punta a punta: el 16, "Ejemplo funcional de punta a punta (lista de tareas con HTMX + paginación reutilizable)".
- Existen dos demos instalables y removibles: `composer demo:tasks:install` y `composer demo:contacts:install`, cuyos orígenes viven en `framework/scaffolds/tasks/` y `framework/scaffolds/contacts/`.
- La guía verifica las copias literales de archivos con `composer docs:check` / `docs:sync`, y marca los fragmentos con `excerpt`.
- Entidad de ejemplo ya presente en el repo: `tasks` (migración, modelo, controlador, vistas, tests) y `contacts` (con una isla Tabulator).

## Restated

Se propone un tutorial, en pasos, que muestre cómo construir un CRUD completo sobre este framework. El repo es el punto de partida y ya contiene material equivalente en otro formato y alcance.

## Origin & Context

- **Raised by**: dueño del repo, en esta sesión (2026-11-11)
- **Trigger**: [NEEDS CLARIFICATION: el motivo declarado no se registró. Contexto visible, no declarado como motivo: la sesión venía de adoptar Spec Kit y de escribir `docs/tutorial-de-uso-spec-kit.md`.]

## First-Glance Unknowns

- [NEEDS CLARIFICATION: ¿Cuál es el punto de partida del lector? ¿Alguien que clona el repo y arranca de cero, o alguien que ya tiene la app corriendo y quiere agregar su primera entidad?]
- [NEEDS CLARIFICATION: ¿La entidad de ejemplo es una nueva (por ejemplo `notas`) o se enseña sobre una existente (`tasks`, `contacts`)? Usar una existente reutiliza archivos que la guía ya copia; usar una nueva obliga a escribirlos todos.]
- [NEEDS CLARIFICATION: ¿El recorrido parte de cero a mano (migración → modelo → controlador → rutas → vistas → tests) o sobre el demo instalable (`composer demo:tasks:install`) como andamio?]
- [NEEDS CLARIFICATION: ¿Cubre HTMX de verdad (swap de región, flash, paginación, CSRF en los dos transportes) o se limita al CRUD server-rendered clásico con 303?]
- [NEEDS CLARIFICATION: ¿Incluye tests y el gate (`composer test`, `composer verify`) como parte de los pasos, o quedan como capítulo aparte?]
- [NEEDS CLARIFICATION: ¿Dónde vive el artefacto? ¿Capítulo nuevo en `docs/guia-stack-php-termux.md`, documento propio en `docs/`, o README?]
- [NEEDS CLARIFICATION: ¿Los bloques de código son copias literales de archivos del repo (verificadas por `composer docs:check`) o fragmentos explicativos marcados `excerpt`?]
- [NEEDS CLARIFICATION: ¿El tutorial es independiente o se apoya en el flujo SDD documentado en `docs/tutorial-de-uso-spec-kit.md`?]
