# Tutorial de uso — Spec Kit en este proyecto

Guía para usar Spec Kit en este repo (Slim 4 + Eloquent + Twig + HTMX): cómo debatir una idea,
cómo formalizarla y cómo implementarla sin que el proceso se coma el proyecto. Asume que
`specify init` ya corrió y que las extensiones `assess` y `bug` están instaladas — el estado
medido está en la sección 0.

Todo lo que sigue se ejecuta **en el chat del agente**, no en la terminal. Los únicos comandos
de terminal son los de instalación y verificación.

## Índice

- [0. Estado de esta instalación](#0-estado-de-esta-instalación)
- [1. Tres procesos, no una cascada](#1-tres-procesos-no-una-cascada)
- [2. Antes de la primera spec: tres decisiones](#2-antes-de-la-primera-spec-tres-decisiones)
- [3. Track A · Debatir una idea](#3-track-a--debatir-una-idea)
- [4. Track B · Construir](#4-track-b--construir)
- [5. Track C · Reparar](#5-track-c--reparar)
- [6. Anatomía de los artefactos](#6-anatomía-de-los-artefactos)
- [7. Cómo escribir el input de cada comando](#7-cómo-escribir-el-input-de-cada-comando)
- [8. Errores comunes](#8-errores-comunes)
- [9. Rutina diaria y cadencia](#9-rutina-diaria-y-cadencia)
- [10. Checklists](#10-checklists)
- [11. Troubleshooting](#11-troubleshooting)
- [12. Lo que dicen los tutoriales viejos y ya no aplica](#12-lo-que-dicen-los-tutoriales-viejos-y-ya-no-aplica)
- [Fuentes](#fuentes)

## 0. Estado de esta instalación

Verificado el 2026-11-11 sobre `specify 1.4.1.dev0`:

| Pieza | Valor |
|---|---|
| Integración | `pi` (comandos con punto: `/speckit.specify`) |
| Scripts | PowerShell (`pwsh` disponible; también corren con shebang) |
| Numeración de features | secuencial (`001`, `002`, …) |
| Directorio de specs | `specs/NNN-slug/` (se crea con la primera spec) |
| Comandos SDD | `constitution`, `specify`, `clarify`, `checklist`, `plan`, `tasks`, `analyze`, `implement`, `converge` |
| Extensión `assess` | v1.0.2, enabled → `assess.intake`, `assess.research`, `assess.define`, `assess.shape`, `assess.decide` |
| Extensión `bug` | v1.0.2, enabled → `bug.assess`, `bug.fix`, `bug.test` |
| Constitución | **plantilla sin llenar** (`[PRINCIPLE_1_NAME]`) |
| `specs/` | **no existe todavía** |

Tres comprobaciones rápidas para no adivinar:

```bash
specify --version                      # versión del CLI
specify extension list                 # extensiones instaladas y su estado
ls .specify/feature.json               # existe solo después de la primera /speckit.specify
```

Y en el chat, escribir `/speckit.` debería ofrecer 17 comandos. Si ofrece menos, el proceso del
agente no recargó: cerrarlo y volver a abrirlo (no `/clear`).

> **El estado vive en `.specify/feature.json`.** No importa en qué rama de git estés. Ese archivo
> apunta la feature activa, y todo comando que no sea `specify` lo lee. Sin él:
> `ERROR: Feature directory not found`. Por eso se empieza siempre por `/speckit.specify`.

## 1. Tres procesos, no una cascada

Spec Kit no es una única cadena obligatoria: son tres puertas de entrada independientes.

```
                    ┌──────────────────────────────────────────┐
   ¿vale la pena?   │ assess.intake → research → define →       │  go
   ────────────────►│ shape → decide                           ├──────┐
                    └──────────────────────────────────────────┘      │
                                   │ kill / needs-clarification      │
                                   ▼                                 ▼
                              cerrado con razón           ┌──────────────────────────┐
                                                          │ specify → clarify → plan │
   hay que          ┌──────────────────────────┐          │ → checklist → tasks →    │
   construirlo ────►│ SDD (core)               │◄─────────│ analyze → implement →    │
                    └──────────────────────────┘  handoff │ converge                │
   hay que          ┌──────────────────────────┐          └──────────────────────────┘
   repararlo ──────►│ bug.assess → fix → test  │
                    └──────────────────────────┘
```

| Pregunta | Proceso | Artefactos | Veredicto |
|---|---|---|---|
| ¿Esta idea merece inversión? | `assess` | `.specify/assessments/<slug>/` | `go` / `needs-clarification` / `kill` |
| ¿Cómo lo construimos? | SDD | `specs/NNN-slug/` | no hay veredicto: hay gates de revisión |
| ¿Por qué está roto? | `bug` | `.specify/bugs/<slug>/` | `verified` / `partial` / `failed` |

La distinción importa: **discovery responde "¿hay que hacerlo?" y delivery responde "¿cómo?"**.
Si le pasás una idea cruda a `specify`, obtenés una especificación prolija de algo que quizá no
había que construir — y el documento la hace parecer decidida.

## 2. Antes de la primera spec: tres decisiones

### 2.1 Llenar la constitución (una vez por proyecto)

Es el documento contra el que `plan`, `analyze` y `checklist` validan todo. Mientras tenga
placeholders, esos comandos no tienen con qué contrastar.

Fuentes de evidencia reales en este repo — usalas, no inventes reglas:

- `.ai/steering/principles.md` (P-001 … P-006, con MUST/SHOULD)
- `.ai/steering/conventions.md` (estilo, capas, testing)
- `DESIGN.md` (sistema visual: `data-theme`, daisyUI, componentes)
- `phpstan.neon`, `phpunit.xml`, `composer.json`

```text
/speckit.constitution Basate en .ai/steering/principles.md y .ai/steering/conventions.md,
y en DESIGN.md. Reglas que ya son verdad en este repo, no aspiraciones. No inventes.
```

Regla del taller original que vale reproducir: **principios por encima de tecnologías.**
"UI declarativa con estado predecible" envejece bien; "usar React 18.2.0" envejece mal.

### 2.2 Fijar el idioma

Los templates están en inglés. Para que los artefactos salgan en español sin pelear cada vez,
dos opciones:

1. Pedirlo en el argumento de cada comando (`… y respondé en español`).
2. Sobrescribir las plantillas del proyecto en `.specify/templates/` (override local, soportado).

Criterio que funciona: **documentación en español, código y rutas en inglés.**

### 2.3 Elegir el alcance de la primera feature

Un slice revisable en una sola sesión. La tentación es "especificá todo el framework": eso
produce un documento que nadie revisa y que queda viejo al segundo commit.

La spec define **el cambio**, no el sistema retroactivo. El código existente es contexto de
implementación, no materia prima de la spec.

## 3. Track A · Debatir una idea

Instalada como extensión porque es un *track* aparte, no una fase del SDD. Funciona incluso en
un proyecto sin código.

```text
/speckit.assess.intake "exportar contactos a CSV" slug=exportar-contactos
/speckit.assess.research slug=exportar-contactos
/speckit.assess.define slug=exportar-contactos
/speckit.assess.shape slug=exportar-contactos
/speckit.assess.decide slug=exportar-contactos
```

| Etapa | Qué hace | Artefacto |
|---|---|---|
| `intake` | Captura y normaliza la idea (texto, URL, ticket o puntero a código) | `intake.md` |
| `research` | Busca evidencia **a favor y en contra**; marca `ASSUMPTION` vs cita | `research.md` |
| `define` | Usuarios afectados, problema, goals, **non-goals**, métricas, costo de inacción | `problem.md` |
| `shape` | 2–3 opciones a nivel concepto, con *appetite* y tradeoffs; recomienda una (o ninguna) | `concept.md` |
| `decide` | Scorecard de 6 criterios y veredicto | `decision.md` |

**Es un embudo, no un trámite.** Matar una idea con la razón escrita es un resultado exitoso.
Si todo termina en `go`, el pipeline es teatro.

**La regla dura del `decide`:** `go` exige `evidence strength` **adequate** o mejor; con
`weak` o `unknown` el veredicto se degrada a `needs-clarification`. Y sin `concept.md`, un `go`
no es válido: no se entrega a `specify` una idea sin forma.

**Etapas no rígidas:** `define` es el mínimo viable y corre sola. Si la idea ya está clara, no
hace falta `intake` ni `research`.

**Cómo refinar:** los `[NEEDS CLARIFICATION: …]` **no** se resuelven re-ejecutando la etapa.
Se edita el `.md` (a mano o pidiéndolo en el chat) y se ajusta lo que dependía de eso, incluido
`decision.md`. Re-ejecutar es la excepción (slug equivocado, borrador descartado).

**Slugs:** minúsculas, kebab-case, sin timestamps. Es el identificador compartido por las 5
etapas y el nombre del directorio.

Si sale `go`, `decision.md` trae el handoff listo para pegar en `/speckit.specify`: problema,
opción recomendada, dentro/fuera de alcance, métricas y preguntas abiertas.

## 4. Track B · Construir

Dos caminos. Elegir según el tamaño, no por completitud ceremonial.

**Corto** — fix acotado o feature chica:

```text
/speckit.specify → /speckit.plan → /speckit.tasks → /speckit.implement → /speckit.converge
```

**Completo** — feature de producción, con los tres gates de calidad:

```text
/speckit.specify → /speckit.clarify → /speckit.plan
  → /speckit.checklist → /speckit.tasks → /speckit.analyze
  → /speckit.implement → /speckit.converge
```

| Comando | Produce | Cuándo |
|---|---|---|
| `specify` | `spec.md` (user stories P1/P2/P3 + Given/When/Then) + `.specify/feature.json` | al abrir el tema |
| `clarify` | sección `## Clarifications` en `spec.md` | antes de planificar; repetible con foco |
| `plan` | `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md` | spec sin ambigüedad |
| `checklist` | checklists de calidad de requisitos | si hay requisitos dudosos |
| `tasks` | `tasks.md` (T001…, `[P]`, `[US1]`) | plan aprobado |
| `analyze` | reporte de consistencia, cobertura y choques con la constitución | antes de implementar |
| `implement` | código + tasks marcadas | tasks aprobadas |
| `converge` | tareas nuevas por drift entre spec y código | después de tocar código, o al cerrar |

**Se corren de a uno.** Cada comando es una parada de revisión: leés el artefacto, lo corregís
(editando el `.md`) y recién ahí seguís. Encadenarlos de corrido convierte el proceso en
generación de documentos que nadie leyó.

**`converge` se repite** después de cada `implement` hasta que reporte `Converged`.

**Ojo con los tests:** `tasks` los genera **solo si se los pedís**. En este repo la suite es la
red de seguridad, así que conviene decirlo explícito:

```text
/speckit.tasks incluí tests PHPUnit por user story, siguiendo las convenciones de tests/.
```

## 5. Track C · Reparar

```text
/speckit.bug.assess "el toggle de tareas devuelve 419 cuando el token CSRF venció" slug=toggle-csrf
/speckit.bug.fix slug=toggle-csrf
/speckit.bug.test slug=toggle-csrf
```

- `assess` — evalúa el reporte **contra el código real** y propone remediación
- `fix` — aplica la remediación y registra qué se cambió
- `test` — valida que el síntoma original desapareció; veredicto `verified` / `partial` / `failed`

Artefactos en `.specify/bugs/<slug>/`. No requiere ciclo SDD previo: pedirle spec + plan + tasks
a un fix de veinte líneas es exactamente cómo la gente abandona el proceso.

## 6. Anatomía de los artefactos

### 6.1 `spec.md`

La unidad es la **user story priorizada e independientemente testeable**. P1 sola tiene que
entregar un MVP usable.

```text
### User Story 1 - Exportar el listado filtrado (Priority: P1)

Un usuario con sesión puede descargar los contactos que está viendo, con los filtros activos.

**Why this priority**: sin filtros respetados el archivo miente sobre lo que el usuario ve.

**Independent Test**: con dos filtros aplicados, el CSV contiene solo esas filas.

**Acceptance Scenarios**:
1. **Given** una lista filtrada por empresa, **When** pido exportar, **Then** el CSV trae solo esa empresa
2. **Given** una lista vacía, **When** pido exportar, **Then** recibo un CSV con encabezado y cero filas
```

Prohibido en la spec: el *cómo*. "Contraseñas guardadas de forma segura e irreversible" va en la
spec; "bcrypt, cost 12" va en el plan.

### 6.2 `plan.md` y compañía

- `research.md` — cada `NEEDS CLARIFICATION` resuelto como **Decisión / Razón / Alternativas consideradas**. Acá queda escrito por qué rechazaste algo.
- `data-model.md` — entidades, campos, validaciones, transiciones de estado.
- `contracts/` — rutas, payloads, respuestas HTMX. En este repo es donde se fija si una respuesta es HTML de región o `HX-Redirect`.
- `quickstart.md` — cómo se valida end-to-end; sin código de implementación.

### 6.3 `tasks.md`

Formato obligatorio, sin excepciones:

```text
- [ ] T001 [P] Crear migración 004_export_jobs_table en database/migrations/
- [ ] T004 [US1] Agregar exportCsv() en app/Contacts/ContactController.php
- [ ] T007 [US1] Cubrir export filtrado en tests/Feature/ContactExportTest.php
```

Componentes: checkbox, ID secuencial en orden de ejecución, `[P]` solo si es paralelizable
(archivos distintos, sin dependencia), `[US1]` solo en fases de user story, y la **ruta exacta**
del archivo. Una task sin ruta no es ejecutable.

Fases: setup → foundational → una fase por user story → polish. Eso permite entregar P1 sin
terminar P2.

## 7. Cómo escribir el input de cada comando

El argumento es texto libre, y la calidad del artefacto depende de él. El patrón que mejor
funciona en este repo es un **preámbulo corto y literal**, repetido al inicio de cada comando:

```text
IDIOMA: artefactos en español; código, rutas e identificadores en inglés.
STACK REAL: PHP 8.4, Slim 4, PHP-DI, Eloquent standalone, Phinx, Twig, HTMX, Alpine, daisyUI
vendorizado, SQLite. Sin Node, sin bundler, sin toolchain de compilación.
NO INVENTES: si falta un dato, preguntá; no supongas versiones ni dependencias.
ALCANCE: solo lo que dice el pedido; nada de features de cortesía.
```

Y por comando, el foco:

| Comando | Qué poner en el argumento |
|---|---|
| `specify` | Qué y por qué, con las tensiones explícitas ("¿descarga directa o cola con email?"), y los límites de compatibilidad que no se tocan |
| `clarify` | El área donde no querés sorpresas: `enfocate en concurrencia, CSRF y estados de error` |
| `plan` | Stack y forma: `usá Phinx + Twig + HTMX, sin dependencias nuevas` |
| `checklist` | `foco en criterios de aceptación testeables y casos de borde` |
| `tasks` | Tamaño y tests: `máximo N tasks por story, tests incluidos` |
| `analyze` | Sin argumento (lee todo) |

Ejemplo de `specify` bien cargado:

```text
/speckit.specify Agregar exportación CSV a la lista de contactos. Conservar filtros y
autorización actuales; exportar solo lo que el usuario ve. No cambiar las respuestas JSON
ni la paginación servidor. Duda abierta: ¿descarga sincrónica o job en background con aviso?
```

## 8. Errores comunes

| Error | Síntoma | Arreglo |
|---|---|---|
| Saltarse la constitución | Cada spec contradice a la anterior | Correr `constitution` primero, desde evidencia del repo |
| Spec demasiado técnica | El "qué" y el "cómo" mezclados en `spec.md` | Mover decisiones a `plan.md` / `research.md` |
| Spec monolítica | Una spec con seis features y P1 que no entrega nada | Una spec por feature; cortar por user story |
| Tasks no atómicas | `- [ ] Implementar autenticación` | Partir hasta que cada una tenga archivo y verificación |
| Implementar sin `analyze` | Se descubre en el código que un requisito no tenía task | `analyze` antes de `implement` — reporta cobertura y tareas huérfanas |
| Encadenar comandos sin leer | Artefactos que se contradicen entre sí | Una parada por comando, corregir el `.md` |
| Comando sin feature activa | `Feature directory not found` | Empezar por `/speckit.specify` |
| Contradicción en vez de refinar | `clarify` no cierra nunca | Editar el artefacto; `clarify` es para preguntas, no para arreglar redacción |

## 9. Rutina diaria y cadencia

Tiempos reales de una sesión, adaptados de un taller de dos horas a trabajo normal:

| Actividad | Tiempo | Salida |
|---|---|---|
| Debatir una idea (`assess`) | 15–30 min | veredicto y razón escrita |
| `specify` | 10 min | `spec.md` |
| `clarify` | 10–15 min | sección de clarificaciones cerrada |
| `plan` | 15–20 min | plan + research + data-model + contracts |
| `tasks` + `analyze` | 10–15 min | backlog sin huecos |
| `implement` | según tamaño | código + tasks marcadas |
| `converge` | 10 min | drift detectado |

No todos los días se especifica. En la práctica:

- Idea nueva → `assess`. Si es `kill`, cerraste el tema con evidencia y no perdiste la tarde.
- Feature → SDD, camino corto si es chica.
- Bug → extensión `bug`.
- Después de tocar código a mano → `converge`.
- Cuando cambiás un criterio de diseño → `constitution` otra vez (versiona `Version` y `Last Amended`).

**Git:** commiteá los artefactos junto con (o antes de) el código. Sugerencia de unidades:

```bash
git add .specify .pi && git commit -m "chore(sdd): adoptar spec-kit con assess y bug"
git add docs/tutorial-de-uso-spec-kit.md && git commit -m "docs(sdd): tutorial de uso"
git add specs/001-exportar-contactos && git commit -m "docs(spec): 001 exportar contactos"
```

`.specify/feature.json` está en `.gitignore` a propósito: es estado por checkout. La caché de
catálogo (`.specify/extensions/.cache/`) no lo está — ruido que conviene ignorar localmente.

## 10. Checklists

Antes de implementar:

- [ ] La constitución refleja reglas que ya son verdad, no deseos
- [ ] La spec tiene criterios de aceptación verificables y casos de error
- [ ] `clarify` no tiene preguntas nuevas que hacer
- [ ] El plan reusa la arquitectura y las dependencias existentes
- [ ] Cada requisito tiene al menos una task (lo dice `analyze`)
- [ ] Las tasks tienen ID, ruta de archivo y son ejecutables sin contexto extra

Después de implementar:

- [ ] `converge` reporta `Converged`
- [ ] `composer test` en verde
- [ ] `vendor/bin/phinx rollback -t 0 && composer migrate` sin sorpresas
- [ ] `composer verify` (lint + docs-check + PHPStan + suite + fresh)
- [ ] Los artefactos quedaron actualizados si cambió una decisión

## 11. Troubleshooting

| Síntoma | Causa | Solución |
|---|---|---|
| `Feature directory not found` | No hay `.specify/feature.json` | Correr `/speckit.specify` primero |
| Los comandos nuevos no aparecen | Proceso del agente sin recargar | Cerrar y reabrir (no `/clear`); verificar con `/speckit.` |
| Un script `.ps1` no ejecuta | Bash sin shebang resuelto | `pwsh -NoProfile -File .specify/scripts/powershell/<x>.ps1` |
| `specify: command not found` | PATH sin el binario de `uv tool` | `uv tool update-shell` o terminal nueva |
| El agente dice que no hay plantilla | Override local roto | `pwsh .specify/scripts/powershell/resolve-template.ps1 spec-template -Json` |
| Un comando escribe en inglés | Templates en inglés | Pedir español en el argumento o sobrescribir `.specify/templates/` |
| `docs:check` falla al agregar docs | Cifras de tests hardcodeadas o copias de archivo desactualizadas | Ver sección 12 y `composer docs:sync` |

## 12. Lo que dicen los tutoriales viejos y ya no aplica

Los materiales didácticos que circulan (incluidos los de la sección Fuentes) son de la era 0.7 y
arrastran diferencias que rompen el primer intento. Esta tabla evita esa pérdida de tiempo:

| Lo que se lee por ahí | Realidad en esta instalación |
|---|---|
| `specify init . --ai copilot` | `--ai` ya no existe; es `--integration pi` |
| `.specify/constitution.md`, `.specify/specs/`, `.specify/plans/`, `.specify/tasks/` | `constitution` vive en `.specify/memory/`; los demás en `specs/NNN-slug/` |
| `.speckit.yaml` como archivo de configuración | No existe en esta versión |
| `.specify/memory/project.md` para la política de idioma | No existe; el idioma se pide en el argumento o se sobrescriben plantillas |
| Cinco comandos slash y nada más | Nueve de SDD + los que aporten las extensiones |
| `specs/todo-crud.md` (un archivo por spec) | Un directorio por feature: `specs/001-slug/spec.md` |
| Tasks como `- [ ] Crear Todo entity` | Requiere ID, `[P]`/`[US]` y ruta: `- [ ] T001 [P] [US1] … app/Models/Task.php` |
| Git: rama por spec obligatoria | Opcional; el estado es `.specify/feature.json`, no la rama |

Cómo lo verificás sin creerme: `specify init --help`, `specify extension search`, y el contenido
real de `.specify/templates/`.

Sobre la guía local `.ai/sdd/`: es un sistema SDD anterior y paralelo, con su propio
`INDEX.md` y su numeración. Sus skills (`/skill:sdd-*`) ya no existen. Sus documentos de
`.ai/steering/` siguen siendo buena evidencia para la constitución, pero el flujo operativo es
este. Dos sistemas con `specs/001` propio solo confunden al agente.

## Fuentes

Compuesto y corregido a partir de:

- **`github.com/armandoblanco/taller-speckit`** (README, Spec Kit v0.7.0) — tomado: la argumentación de por qué especificar, el orden de gates con `clarify` y `analyze` en su lugar, la tabla de comandos con "cuándo usarlo", el patrón de preámbulo repetido en cada prompt, y la agenda por bloques de tiempo.
- **`siemprelisto.cl/tecnologias/spec-kit/`** (capítulos 01–05) — tomado: el encuadre tradicional vs. spec-driven, cuándo **no** usar SDD, las cuatro propiedades de una buena task (atómica, verificable, ordenada, estimable), los pares malo/bueno de specs y constituciones, y la tabla de errores comunes.
- **`github.com/github/spec-kit`** (README, `docs/`, extensión `assess`) — fuente de verdad para nombres de comandos, artefactos, veredictos, reglas del `decide` y estructura de directorios.
- Los scripts del propio repo (`scripts/docs-check.php`, `framework/Docs/CodeBlocks.php`) — para que este documento pase `composer docs:check`.

Las afirmaciones de estado (§0) y las correcciones (§12) están verificadas contra el CLI instalado,
no copiadas de las fuentes.

## Siguiente paso

```text
/speckit.constitution Basate en .ai/steering/principles.md, .ai/steering/conventions.md y DESIGN.md.
```

y después, sobre una idea real:

```text
/speckit.assess.intake "la idea en una o dos frases" slug=mi-idea
```
