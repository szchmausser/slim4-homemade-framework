# Implementation Plan: Recorrido de primera entidad para usuarios nuevos

**Branch**: `001-tutorial-crud` | **Date**: 2026-11-11 | **Spec**: `specs/001-tutorial-crud/spec.md`

**Input**: Feature specification from `specs/001-tutorial-crud/spec.md`

## Summary

Producir un recorrido de lectura lineal, en un único archivo de la raíz (`tutorial-crud.md`), que
lleve a alguien con un clon recién hecho hasta una entidad propia funcionando: arranque de la app
hasta una sesión iniciada, una primera ruta verificable por petición directa, la vista que la
muestra, y un cierre que convierte las comprobaciones manuales en tests. Sin dependencias nuevas,
sin scripts propios y sin tocar la guía ni los demos.

Enfoque técnico: el artefacto es documentación ejecutable a mano. La verificación no la hace un gate
—queda fuera de `docs:check`, `lint` y PHPStan— sino el lector en cada paso, con el resultado
esperado escrito de antemano, y al final la suite de la entidad.

## Technical Context

**Language/Version**: Markdown para el artefacto; PHP 8.4.1 para el código que muestra (el piso del proyecto).

**Primary Dependencies**: ninguna nueva. El recorrido usa lo que el repo ya trae: Slim 4, PHP-DI, Eloquent standalone, Phinx, Twig, HTMX, PHPUnit.

**Storage**: SQLite, la del proyecto. La entidad de ejemplo usa la migración que el lector escribe.

**Testing**: PHPUnit 13.3, suite existente del proyecto, al cierre del recorrido.

**Target Platform**: Termux ARM 32-bit sin toolchain, más navegador y cliente HTTP del lector.

**Project Type**: documentación técnica (artefacto único), no código de producción.

**Performance Goals**: N/A — no hay ruta de ejecución propia.

**Constraints**: un solo archivo en la raíz; fuera de `docs/`, `.ai/` y el README como destino; cero dependencias nuevas; cero JS fuera de los bloques ya existentes; nada de `dark:` ni CDN.

**Scale/Scope**: 1 archivo, ~15 pasos, 1 entidad de ejemplo, 3 tests al cierre, 1 línea agregada al README.

## Constitution Check

| Principio | Cómo lo cumple este plan |
|---|---|
| I. Termux primero | No agrega dependencias ni pasos de build. Todo lo que el lector ejecuta ya corre en 32 bits: Composer, Phinx, `php -S`. |
| II. HTMX devuelve HTML de región | El recorrido enseña el intercambio de región como idioma; no introduce variantes propias ni respuestas 303 a HTMX. |
| III. Tema por `data-theme` | No toca el tema ni introduce utilidades `dark:`; usa el shell existente. |
| IV. Assets con SRI, cero CDN | No agrega assets. Si el recorrido necesita una imagen, se revisa (asumido como riesgo en la spec). |
| V. Toda regla entra con su guarda | **Excepción deliberada**, ver Complexity Tracking: el material queda fuera de todo gate; la guarda se sustituye por comprobación manual declarada más la suite al cierre. |

**Gate del proyecto**: `composer verify` (lint + docs-check + PHPStan + suite + migración fresh). El
plan no puede romperlo: el artefacto no está en los alcances escaneados y el código que muestra no
se ejecuta desde el repo.

**Gate de clarificaciones**: sin pendientes. Las cuatro se cerraron en `spec.md` § Clarifications.

## Project Structure

### Documentation (this feature)

```text
specs/001-tutorial-crud/
├── spec.md                  # ya existe: requisitos y clarificaciones
├── plan.md                  # este archivo
├── research.md              # decisiones del plan (Phase 0)
├── data-model.md            # estructura del contenido y de la entidad de ejemplo
├── contracts/
│   └── tutorial-contract.md # contrato de cada paso y del artefacto completo
└── quickstart.md            # cómo validar el recorrido de punta a punta
```

### Source Code (repository root)

No hay código de producción. El entregable es un archivo de documentación:

```text
tutorial-crud.md             # el entregable (raíz, fuera de docs/ y .ai/)
README.md                    # una sola línea: enlace en el índice de guías
```

**Structure Decision**: un archivo único en la raíz. No se crea directorio porque el recorrido no
necesita imágenes ni adjuntos; si los necesitara, la decisión se revisa aquí y no en la
implementación (declarado como asunción en la spec). Los artefactos del feature viven en
`specs/001-tutorial-crud/`, como cualquier feature del proyecto.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Material sin guarda automática (excepción al principio V) | El solicitante decidió que el artefacto es referencia de uso, no documentación viva: fuera de `docs:check`, `lint` y PHPStan | Ponerlo bajo el gate se evaluó y se descartó: obliga a que cada bloque sea copia literal de un archivo del repo, lo que convierte el recorrido en una segunda copia del demo y mata su propósito. La guarda se sustituye por FR-014 (resultado esperado escrito antes de comprobar) y FR-009 (suite al cierre). |
