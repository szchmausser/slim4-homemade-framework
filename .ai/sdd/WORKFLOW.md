# Guía de Flujo SDD

> Propósito: ayudar a humanos y agentes IA a seguir el flujo SDD sin ceremonia extra.
> Última actualización: 2026-10-18

## Flujo Simple

```text
IDEA → PLAN → PRD → SPEC → TASKS → EXEC → REVIEW
```

La mayoría de los chequeos de calidad están dentro de cada paso, no como comandos separados.

## Paso a Paso

### 1. IDEA

**Comando:** `/skill:sdd-idea`

**Para qué:** explorar una idea cruda, comparar direcciones, identificar usuarios, valor, riesgos y límites; decidir si seguir, planificar o pasar directo a requisitos.

**Salida típica:** `.ai/sdd/ideas/NNN-nombre-idea.md`

### 2. PLAN

**Comando:** `/skill:sdd-plan`

**Para qué:** definir límites del MVP, organizar features en fases, mapear dependencias y alinear el roadmap antes de escribir requisitos.

**Salida típica:** `.ai/sdd/PLAN.md`

**Atajo:** para features pequeñas, saltar PLAN e ir directo a PRD.

### 3. PRD (Requisitos)

**Comando:** `/skill:sdd-prd`

**Para qué:** definir QUÉ y POR QUÉ con user stories, criterios de aceptación, requisitos EARS (`FR-`/`NFR-`) y bordes de alcance.

**Gate:** requisitos aprobados antes de un SPEC vinculante.

**Salida típica:** `.ai/sdd/specs/NNN-feature-slug/requirements.md`

### 4. SPEC (Diseño)

**Comando:** `/skill:sdd-spec`

**Para qué:** definir CÓMO: mapping de requisitos, arquitectura, componentes, datos/estado, contratos, casos de borde, decisiones técnicas y estrategia de verificación.

**Gate:** diseño aprobado antes de TASKS vinculante.

**Salida típica:** `.ai/sdd/specs/NNN-feature-slug/design.md`

### 5. TASKS

**Comando:** `/skill:sdd-tasks`

**Para qué:** descomponer el diseño aprobado en tareas pequeñas con dependencias, archivos, criterios de aceptación y verificación. Incluye el chequeo de readiness.

**Gate:** tareas aprobadas antes de EXEC.

**Salida típica:** `.ai/sdd/specs/NNN-feature-slug/tasks.md`

### 6. EXEC (Implementación)

**Comando:** `/skill:sdd-exec`

**Para qué:** implementar una tarea aprobada por vez, con alcance acotado, tests y evidencia fresca de verificación.

**Estado permitido:** `tasks:approved` o `implementation:in-progress`

### 7. REVIEW

**Comando:** `/skill:sdd-review`

**Para qué:** comparar la implementación contra requisitos, diseño y tareas; verificar cobertura y calidad; determinar readiness de merge.

**Salida típica:** `.ai/sdd/specs/NNN-feature-slug/review.md`

## Atajo para Feature Pequeña

```text
PRD → SPEC → TASKS → EXEC → REVIEW
```

Usar IDEA solo si la idea no está clara. Usar PLAN solo si hay múltiples features, fases o dependencias.

## Contexto de Steering

Contexto reutilizable en `.ai/steering/`:

- `product.md` — visión, usuarios, valor, límites;
- `tech-stack.md` — runtime, frameworks, infraestructura, comandos de verificación;
- `conventions.md` — estilo, arquitectura, testing, workflow;
- `principles.md` — principios `MUST`/`SHOULD`/`MAY` y reglas de decisión.

Crear o actualizar con `/skill:sdd-steering`.

## Gates y Status

Cada spec tiene un archivo `.status` (fuente de verdad de gates):

```text
requirements:draft → requirements:approved
design:draft → design:approved
tasks:draft → tasks:approved
tasks:approved → implementation:in-progress
implementation:in-progress → implementation:done
implementation:done → review:done
```

Reglas:

- Los drafts se pueden guardar, pero no desbloquean la fase siguiente.
- La existencia de un archivo NO implica aprobación.
- `.status` es la fuente de verdad para gates.
- Un skill se detiene si `.status` falta, es inválido o es inconsistente.
- La aprobación de requirements, design y tasks requiere aprobación humana explícita.

## IDs de Feature

Directorios con forma canónica:

```text
.ai/sdd/specs/NNN-feature-slug/
```

La numeración se calcula desde el filesystem, no desde `INDEX.md`:

1. listar directorios bajo `.ai/sdd/specs/`;
2. extraer prefijos numéricos de nombres como `001-feature`;
3. usar `max + 1`;
4. rellenar a al menos 3 dígitos;
5. crear un slug corto y seguro.

## Próxima Acción Recomendada

Si no sabés por dónde empezar:

- idea poco clara → `/skill:sdd-idea`
- múltiples features → `/skill:sdd-plan`
- feature conocida → `/skill:sdd-prd`
- requisitos aprobados → `/skill:sdd-spec`
- diseño aprobado → `/skill:sdd-tasks`
- tareas aprobadas → `/skill:sdd-exec`
- feature implementada → `/skill:sdd-review`
- estado incierto → `/skill:sdd-status`
