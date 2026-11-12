# Tasks: Recorrido de primera entidad para usuarios nuevos

**Input**: Design documents from `specs/001-tutorial-crud/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: el entregable **enseña** tests; no se escriben tests del entregable. Las tareas de "test" aquí son tareas de redacción del cierre. La validación se hace siguiendo el recorrido, no ejecutando una suite del tutorial.

**Organization**: tareas agrupadas por historia de usuario. El entregable es un único archivo (`tutorial-crud.md`), así que varias tareas tocan el mismo archivo y **no** son paralelizables entre sí dentro de una fase; las marcadas `[P]` trabajan sobre documentos distintos o sobre verificación independiente.

## Cómo leer este archivo

- **`T001`…`T032`**: número de tarea, en orden de ejecución. No son pasos del tutorial: son tareas para escribirlo.
- **`US1` / `US2` / `US3`**: a cuál de las tres historias de `spec.md` sirve la tarea. US1 = llegar a una entidad funcionando; US2 = entender las piezas mientras se usan; US3 = repetir el patrón con otra entidad.
- **`[P]`**: puede hacerse en paralelo con otras tareas.
- **`FR-0NN`**: requisito de `spec.md` que la tarea cubre.
- **`[ ]`**: sin hacer. Lo marca quien implementa.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: puede ejecutarse en paralelo (archivo distinto, sin dependencia)
- **[Story]**: US1 / US2 / US3
- Cada tarea nombra el archivo y la sección exacta

## Path Conventions

- Entregable: `tutorial-crud.md` (raíz del repositorio)
- Documentación de la feature: `specs/001-tutorial-crud/`
- Validación: copia descartable del repositorio, nunca la working tree

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: esqueleto del artefacto y convención de escritura de cada paso

- [x] T001 Crear `tutorial-crud.md` en la raíz con la estructura del contrato: título, propósito, prerrequisitos, índice de pasos y cierre, según `specs/001-tutorial-crud/contracts/tutorial-contract.md`
- [x] T002 [P] Escribir el bloque de prerrequisitos en `tutorial-crud.md` § Prerrequisitos: PHP 8.4.1, Composer, cliente HTTP, navegador, y la lista explícita de lo que el recorrido **no** enseña
- [x] T003 Escribir la convención interna que repite cada paso en `tutorial-crud.md`: objetivo, estado de partida, acción, resultado esperado, señal de falla, qué no prueba y pieza presentada (si hay)

**Checkpoint**: el esqueleto existe y cualquier paso que se agregue ya tiene forma fija

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: datos que el documento no puede inventar porque dependen del repositorio real

**⚠️ CRITICAL**: ninguna historia puede escribirse sin estos valores fijados

- [x] T004 Verificar el número de migración libre en `database/migrations/` y escribir en `tutorial-crud.md` § migración el **criterio** para calcularlo, con el número actual como ejemplo y no como constante
- [x] T005 Verificar el punto de registro de rutas en `bootstrap/app.php:48` y el patrón de `routes/web.php`; dejar elegidos los extractos que se van a citar en `tutorial-crud.md`
- [x] T006 Fijar en `tutorial-crud.md` los literales de la primera comprobación: método, URL, `Content-Type`, cuerpo exacto `{"status":"ok","recurso":"notas"}` y código de estado
- [x] T007 [P] Confirmar en `tests/Support/HttpTestCase.php` el patrón de login inyectado, `setUp`/`tearDown` y limpieza que el cierre va a enseñar; dejar el extracto elegido
- [x] T008 [P] Definir en `specs/001-tutorial-crud/quickstart.md` § validación el procedimiento de la copia descartable (clon local en directorio temporal) y el criterio para dar por válido un recorrido seguido de punta a punta

**Checkpoint**: el documento tiene todos los valores reales que necesita; nada queda a inventar durante la redacción

---

## Phase 3: User Story 1 - Llegar a una entidad funcionando, paso por paso (Priority: P1) 🎯 MVP

**Goal**: el lector arranca la app, inicia sesión y llega a una entidad propia que lista y persiste, con una comprobación por paso y la suite al cierre.

**Independent Test**: seguir el recorrido completo en la copia descartable, sin abrir la guía ni `framework/scaffolds/`, y terminar con `notas` listando y persistiendo datos reales.

- [x] T009 [US1] Escribir en `tutorial-crud.md` el paso de arranque: `.env`, dependencias, autoload, migraciones, siembra del administrador con `ADMIN_EMAIL`/`ADMIN_PASSWORD` desde el entorno, servidor de desarrollo e inicio de sesión en `/login`; incluir esperado, señal de falla y qué no prueba
- [x] T010 [US1] Escribir el paso de migración de `notas` en `tutorial-crud.md`, citando los constraints textuales de `data-model.md`: `titulo` requerido 1–120, `cuerpo` opcional hasta 2000, timestamps
- [x] T011 [US1] Escribir el paso del modelo `Nota` en `tutorial-crud.md`, con `$fillable` y la relación con la tabla; explicar por qué el modelo no valida y quién valida
- [x] T012 [US1] Escribir el paso de rutas en `tutorial-crud.md`: archivo `routes/notas.php` completo y el registro en `bootstrap/app.php`, explicando por qué existe el registro y no sólo el archivo
- [x] T013 [US1] Escribir el paso de la primera ruta JSON en `tutorial-crud.md` con la comprobación `http` completa: comando copiable, cuerpo esperado literal, código de estado y señal de falla
- [x] T014 [US1] Escribir el paso del listado en `tutorial-crud.md`: vista que extiende `layouts/app.twig` con `{% block content %}`, sin HTMX todavía, y comprobación por navegador con lo que debe verse y lo que no (depende de T013: FR-017 exige la respuesta simple verificada antes de introducir vistas)
- [x] T015 [US1] Escribir el paso del alta con validación en `tutorial-crud.md`: rechazo por título vacío y por exceder 120, con el mensaje junto al campo; comprobación por navegador y señal de falla
- [x] T016 [US1] Escribir el paso de persistencia comprobable en `tutorial-crud.md`: dar de alta, recargar y ver la fila; declarar explícitamente qué **no** prueba esa comprobación
- [x] T017 [US1] Escribir el cierre de US1 en `tutorial-crud.md` § Tests: tres casos sobre `Tests\Support\HttpTestCase` —alta correcta, validación que rechaza, identificador inexistente que avisa— presentados como el reemplazo de las comprobaciones manuales; no adelantar tests ni pistas de automatización en los pasos anteriores (FR-016)
- [x] T018 [US1] Validar US1: seguir el recorrido en la copia descartable, registrar cada desvío entre lo esperado y lo obtenido, y corregir `tutorial-crud.md` hasta que no haya desvíos; confirmar además que cada paso declara estado de partida y de llegada (FR-005) y que ningún paso reproduce literalmente código de `framework/scaffolds/` (FR-006)

**Checkpoint**: US1 funciona sola. El recorrido produce una entidad que lista, persiste y tiene suite propia

---

## Phase 4: User Story 2 - Entender el idioma del framework mientras se usa (Priority: P2)

**Goal**: cada pieza reutilizable aparece cuando hace falta, con qué resuelve, cuándo usarla, qué evita y alternativa manual.

**Independent Test**: pedir a alguien que siguió el recorrido que nombre las piezas que usó y qué evita cada una, sin releerlo.

- [x] T019 [US2] Definir en `tutorial-crud.md` la lista de piezas que el recorrido presenta y en qué paso aparece cada una, sin presentar ninguna antes de que el lector la necesite
- [x] T020 [US2] Completar en `tutorial-crud.md`, en cada paso que presenta una pieza, los cuatro campos del contrato: qué resuelve, cuándo usarla, qué evita y cómo se haría sin ella
- [x] T021 [US2] Escribir en `tutorial-crud.md` la sección del listado como región HTMX: qué se intercambia, dónde aparece el aviso y qué no prueba la comprobación visual
- [x] T022 [US2] Escribir en `tutorial-crud.md` la sección de paginación del servidor: por qué el servidor cuenta y recorta, y qué se evita frente a traer todo el conjunto
- [x] T023 [US2] Escribir en `tutorial-crud.md` la sección de protección de formularios: los dos transportes —campo oculto dentro del formulario y cabecera en un botón suelto— explicados como uso, sin entrar en cómo se construyen
- [x] T024 [US2] Validar US2: ejecutar el test de P2 (nombrar y justificar las piezas usadas) y ajustar `tutorial-crud.md` donde la explicación no alcance

**Checkpoint**: el recorrido ya no se lee como receta: cada pieza tiene razón y alternativa

---

## Phase 5: User Story 3 - Repetir el patrón con una entidad propia (Priority: P3)

**Goal**: el cierre deja el patrón explícito para aplicar a otra entidad sin releer el recorrido.

**Independent Test**: agregar una segunda entidad con otro nombre y otro campo usando el recorrido sólo como consulta.

- [x] T025 [US3] Escribir en `tutorial-crud.md` § Transferencia la guía de aplicación a otra entidad: qué cambia (nombre, campos, archivos) y qué no cambia (orden, comprobaciones, cierre)
- [x] T026 [US3] Validar US3: crear la segunda entidad en la copia descartable usando el recorrido como consulta, anotar cuántas relecturas hicieron falta y ajustar `tutorial-crud.md` § Transferencia

**Checkpoint**: las tres historias se sostienen por separado y el patrón es repetible

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: descubribilidad y verificación de que no se tocó lo intocable

- [x] T027 [P] Agregar una única línea de enlace a `tutorial-crud.md` en el índice de guías de `README.md`
- [x] T028 [P] Verificar que `git status` sólo muestre `tutorial-crud.md` nuevo y una línea en `README.md`; nada en `docs/`, `framework/scaffolds/` ni `DESIGN.md`
- [x] T029 Ejecutar `composer verify` y `composer docs:check`; confirmar el mismo resultado que antes del artefacto (23 markdown, 44 fuentes, 57 copias)
- [x] T030 Recorrer `specs/001-tutorial-crud/checklists/verificacion.md` y resolver o registrar explícitamente CHK007, CHK010 y CHK023
- [x] T031 [P] Verificar que `tutorial-crud.md` no contiene credenciales ni valores de `.env` reales: la siembra del administrador debe mostrar la lectura desde el entorno, nunca un valor concreto (FR-003)
- [x] T032 Verificar que `tutorial-crud.md` no introduce utilidades `dark:`, assets nuevos ni respuestas 303 para HTMX (FR-018)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: sin dependencias
- **Foundational (Phase 2)**: depende de Setup; **bloquea las tres historias** porque fija valores que salen del repositorio real
- **US1 (Phase 3)**: depende de Foundational
- **US2 (Phase 4)**: depende de US1 (las piezas se explican sobre pasos que ya existen en el texto)
- **US3 (Phase 5)**: depende de US1; se beneficia de US2 pero no la requiere
- **Polish (Phase 6)**: depende de las historias que se entreguen

### Within Each Story

- Un paso se escribe completo (con su comprobación) antes del siguiente: el recorrido es lineal
- La validación va **después** de que la historia esté escrita entera: validar por partes no dice nada sobre el orden
- Tareas sobre `tutorial-crud.md` no son paralelizables entre sí; `[P]` sólo aparece cuando el objetivo es otro archivo o una verificación independiente

### Parallel Opportunities

- T002, T007 y T008 (`[P]`) pueden ir en paralelo con T001/T003: archivos distintos
- T029 y T030 pueden correr juntas con T027/T028
- Nada más: el entregable es un solo archivo y escribirlo en paralelo produce un texto incoherente

## Implementation Strategy

### MVP First (US1 only)

1. Setup + Foundational
2. US1 completa, incluida la validación en la copia descartable
3. **Parar y evaluar**: si US1 no le permite a alguien llegar a la entidad funcionando, US2 y US3 no tienen sentido

### Incremental Delivery

1. US1 → validación → el recorrido ya sirve para construir
2. US2 → validación → el recorrido además enseña
3. US3 → validación → el recorrido se vuelve repetible
4. Polish → descubrible y sin daño colateral

## Notes

- El entregable **no** se valida con un gate: se valida siguiéndolo. Una tarea de validación que no registra desvíos no valida nada
- Toda comprobación que se escriba sin señal de falla está incompleta, aunque el paso se lea bien
- La copia descartable es obligatoria: si la entidad de ejemplo queda en la working tree, se contamina el repositorio y se rompe el supuesto de "el artefacto no agrega código"

---

## Cierre

Todas las tareas quedaron cumplidas. La guía se escribió, se siguió de punta a punta **dos veces** en
worktrees descartables (una por mí, otra por un revisor independiente con el prompt de revisión) y se
corrigió con la evidencia de cada corrida.

| Fase | Evidencia |
|---|---|
| 1 · Setup (T001–T003) | `tutorial-crud.md` existe en la raíz con prerrequisitos, índice y la convención de paso (objetivo / estado de partida / acción / esperado / falla si / qué no prueba / pieza). |
| 2 · Fundación (T004–T008) | Migración `007` verificada en `phinx status`; `bootstrap/app.php` y el patrón de `routes/*.php` revisados; literales de la primera comprobación fijados (`{"status":"ok","recurso":"notas"}`); patrón de `tests/Support/HttpTestCase.php` confirmado; procedimiento de copia descartable escrito en `quickstart.md`. |
| 3 · US1 (T009–T018) | Recorrido seguido dos veces: arranque con sesión, migración, modelo, rutas, JSON, lista, alta en modal, edición, borrado, y 5 tests en verde. Desvíos corregidos: import faltante en 6.1 y 9, `<dialog>` fuera del bloque, `only` en dos includes, dos `<h1>` en 6.4, email del usuario en el sidebar y no en el header. |
| 4 · US2 (T019–T024) | Cada pieza se presenta cuando hace falta, con qué resuelve, cuándo usarla, qué evita y alternativa manual (revisado en el texto). El test de P2 con un lector nuevo queda para la validación humana. |
| 5 · US3 (T025–T026) | Repetido con `libros` usando sólo el inventario de seis archivos: pantalla con modal, dos acciones por fila y paginación, con 5 tests en verde y ninguna mención a `notas` en piezas compartidas. |
| 6 · Polish (T027–T032) | Línea única en el índice de guías del README; `git status` con sólo `tutorial-crud.md` y esa línea; `composer docs:check` en verde; checklist revisado (CHK007 y CHK010 cerrados en `contracts/`, CHK023 documentado en el plan); sin credenciales reales, sin utilidades `dark:`, sin assets nuevos, sin 303 para HTMX. |

**Cambio de alcance durante la ejecución:** el paso 4 ya no edita `bootstrap/app.php`. Con
`feat(routing): registrar routes/*.php por convención`, agregar una entidad es dejar su archivo en
`routes/`, así que seguir el recorrido ya no rompe `composer docs:check` (antes fallaba porque ese
archivo está copiado en la guía del stack).

**Pendiente fuera de estas tareas:** la validación humana —que alguien sin conocimiento del repo siga
la guía de punta a punta—. Es el único test que no puede hacer un agente, porque los agentes ya saben
dónde está cada archivo.
