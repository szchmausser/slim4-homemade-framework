# Feature Specification: Recorrido de primera entidad para usuarios nuevos

**Feature Branch**: `001-tutorial-crud`

**Created**: 2026-11-11

**Status**: Draft

**Input**: User description: handoff de `.specify/assessments/tutorial-crud/decision.md` (veredicto `go`, opción B). Problema: quien adopta el framework no tiene un recorrido corto y ordenado de «cloné el repo» a «mi entidad funciona». El material existente construye el framework o entrega código terminado; ninguno muestra el orden de creación. Alcance: recorrido canónico con verificación por paso, en un artefacto nuevo fuera de `docs/`, sin modificar la guía ni los demos.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Llegar a una entidad funcionando, paso por paso (Priority: P1)

Una persona que acaba de clonar el repositorio y nunca lo vio antes arranca la aplicación hasta
tener una sesión iniciada y, desde ahí, sigue un recorrido de pasos atómicos que la lleva a una
entidad propia que se lista, se da de alta, valida sus datos y persiste. En cada paso sabe qué hizo, por qué, y cómo comprobar que quedó bien antes de seguir.

**Why this priority**: es el problema entero. Sin esto no hay artefacto que entregar a un usuario
nuevo, y las otras dos historias no tienen sobre qué apoyarse.

**Independent Test**: con un clon limpio y sin abrir la guía ni `framework/scaffolds/`, seguir el
recorrido de punta a punta: arrancar la app, iniciar sesión y terminar con la entidad listando y
persistiendo datos reales. Se
considera logrado cuando el lector puede señalar, en cada paso, la comprobación que hizo y su
resultado.

**Acceptance Scenarios**:

1. **Given** un clon recién instalado, **When** el lector ejecuta el arranque que el recorrido indica, **Then** la aplicación responde en su navegador y puede iniciar sesión con el administrador sembrado.
2. **Given** una sesión iniciada, **When** el lector abre una ruta protegida, **Then** accede sin que el sistema lo devuelva al login.
3. **Given** un clon recién instalado, **When** el lector sigue el recorrido desde el primer paso, **Then** al terminar el último ve su entidad listada y puede dar de alta un registro que persiste tras recargar.
4. **Given** un paso cualquiera del recorrido, **When** el lector ejecuta la comprobación que el paso declara, **Then** obtiene un resultado observable que distingue «quedó bien» de «no quedó».
5. **Given** un lector que se salteó un paso, **When** ejecuta la comprobación del paso siguiente, **Then** la comprobación falla y el recorrido indica cómo volver al estado esperado.
6. **Given** el estado final del recorrido, **When** el lector intenta dar de alta un registro con datos inválidos, **Then** el sistema lo rechaza y el mensaje aparece junto al campo que lo causó.

---

### User Story 2 - Entender el idioma del framework mientras se usa (Priority: P2)

A medida que el recorrido avanza, cada pieza reutilizable que el framework ofrece aparece en el
momento en que hace falta, con una explicación de qué resuelve, cuándo conviene usarla y qué se
evita al usarla. El lector no copia una receta: entiende por qué el framework hace las cosas así.

**Why this priority**: el recorrido puede producir una entidad sin esta historia, pero entonces
enseña a copiar. La diferencia entre «funcionó» y «entiendo qué usé» es lo que hace al material
reutilizable.

**Independent Test**: pedirle al lector que nombre las piezas reutilizables que usó y explique, para
cada una, qué problema evita. Si puede hacerlo sin releer el recorrido, la historia está lograda.

**Acceptance Scenarios**:

1. **Given** un paso que introduce una pieza reutilizable, **When** el lector lo lee, **Then** encuentra qué problema resuelve esa pieza, cuándo usarla y qué se evita al usarla.
2. **Given** el listado de la entidad, **When** el lector lo compara con una lista hecha a mano, **Then** puede explicar qué aporta la pieza que el framework ya provee para ese caso.
3. **Given** una operación que muta datos desde la interfaz, **When** el lector revisa cómo quedó protegida, **Then** entiende el mecanismo que la protege y qué pasaría si faltara.

---

### User Story 3 - Repetir el patrón con una entidad propia (Priority: P3)

Al terminar, el lector tiene el patrón lo bastante explícito como para aplicarlo a su propia
entidad, distinta de la del ejemplo, sin volver a leer todo el recorrido.

**Why this priority**: convierte el material en camino y no en caso único. Es lo que sostiene el
valor del artefacto en el tiempo.

**Independent Test**: pedirle al lector que agregue una segunda entidad, con otro nombre y otro
campo, usando el recorrido sólo como consulta. Si llega a listar y persistir sin releerlo entero,
está logrado.

**Acceptance Scenarios**:

1. **Given** el recorrido terminado, **When** el lector agrega una entidad con otro nombre y otro campo, **Then** la ve listada y persistida sin repetir el recorrido completo.
2. **Given** esa segunda entidad, **When** el lector comete un error en un paso, **Then** reconoce en qué paso está y cómo comprobarlo, sin ayuda externa.

### Edge Cases

- El lector no tiene el entorno listo (Composer, migraciones, servidor): el recorrido declara los prerrequisitos al inicio y no los enseña, pero debe decir qué hacer si faltan.
- El nombre de entidad elegido colisiona con una entidad que ya existe en el repositorio: el recorrido debe nombrar el conflicto y cómo detectarlo.
- El repositorio puede estar con los demos instalados o sin ellos; el recorrido no puede depender de ese estado.
- El lector ya tiene una entidad parecida y arranca a mitad de camino: cada paso debe declarar el estado de partida que asume.
- Una comprobación que pasa por el motivo equivocado (por ejemplo, la ruta responde pero no persistió nada): el recorrido debe declarar qué NO prueba cada comprobación.
- El lector no logra iniciar sesión porque no hay administrador sembrado o no definió las credenciales: el recorrido debe decirlo antes de que el lector crea que su entidad está mal.
- Las credenciales del administrador no cumplen el mínimo exigido por el proyecto: el recorrido debe anticipar el rechazo en lugar de dejar que aparezca como error del paso siguiente.
- El lector abre una ruta protegida sin sesión y el sistema lo devuelve al login: el recorrido debe explicar el desvío en vez de dejarlo como sorpresa.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El recorrido MUST partir de un clon recién instalado y declarar sus prerrequisitos sin enseñarlos.
- **FR-002**: El recorrido MUST incluir el arranque mínimo de la aplicación hasta una sesión iniciada contra una ruta protegida: variables de entorno, dependencias, migraciones, usuario administrador y servidor de desarrollo.
- **FR-003**: Las credenciales del administrador MUST provenir del entorno y MUST NOT quedar escritas en el artefacto.
- **FR-004**: Cada paso MUST declarar una comprobación observable, ejecutable por el lector, que distinga el éxito del fracaso.
- **FR-005**: Cada paso MUST declarar el estado de partida que asume y el estado en que deja el sistema.
- **FR-006**: El recorrido MUST construir una entidad nueva, escrita por el lector, y no copiada de un demo existente.
- **FR-007**: El recorrido MUST usar el idioma vigente del framework para listar, dar de alta, validar y avisar, en lugar de una variante propia.
- **FR-008**: Cada pieza reutilizable presentada MUST explicarse con qué resuelve, cuándo usarla y qué se evita al usarla.
- **FR-009**: El recorrido MUST incluir la suite mínima de la entidad: alta correcta, validación que rechaza, y aviso ante un identificador inexistente.
- **FR-010**: El cierre del recorrido MUST dejar el patrón explícito para aplicarlo a una entidad distinta de la del ejemplo.
- **FR-011**: El artefacto MUST vivir fuera de `docs/`, `.ai/` y el README, y MUST poder agregarse sin romper el gate del proyecto.
- **FR-012**: El artefacto MUST NOT modificar la guía existente, el README, ni los demos.
- **FR-013**: El recorrido MUST NOT enseñar a construir el framework (instalación de PHP/Termux, contenedor de dependencias, middlewares, auth, assets) ni a modificar sus piezas; MUST explicarlas para usarlas, y sí incluye el arranque de la aplicación hasta una sesión iniciada porque sin sesión no hay ruta protegida que ver.
- **FR-014**: Las comprobaciones por paso MUST usar [NEEDS CLARIFICATION: qué cuenta como comprobación suficiente — ¿una instrucción manual que el lector ejecuta, un comando del proyecto, un test propio del recorrido, o una combinación?]
- **FR-015**: El artefacto MUST ubicarse en [NEEDS CLARIFICATION: ruta concreta dentro del árbol, sabiendo que `docs/`, `.ai/` y el README quedan excluidos]
- **FR-016**: La suite de la entidad MUST escribirse [NEEDS CLARIFICATION: dentro de los pasos, a medida que aparece cada comportamiento, o como cierre al final del recorrido]

### Key Entities

- **Paso**: unidad atómica del recorrido. Tiene un objetivo, un estado de partida, una acción del lector, una comprobación observable y el estado en que deja el sistema.
- **Comprobación**: evidencia que el lector obtiene al ejecutar un paso. Es lo que reemplaza al gate automático y lo que distingue un material vigente de uno que envejeció.
- **Pieza reutilizable**: capacidad que el framework ya provee y que el recorrido presenta en el momento en que hace falta, con su propósito y su alternativa manual.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Una persona que nunca vio el repositorio llega a una entidad propia que lista y persiste, siguiendo el recorrido sin abrir la guía ni `framework/scaffolds/`.
- **SC-002**: El 100% de los pasos declara una comprobación que falla cuando el paso no se hizo.
- **SC-003**: El lector agrega una segunda entidad propia, con otro nombre y otro campo, sin releer el recorrido completo.
- **SC-004**: El gate del proyecto sigue en verde después de agregar el artefacto.
- **SC-005**: El recorrido no exige ningún prerrequisito que un clon del repositorio no traiga ya, y el arranque hasta una sesión iniciada está incluido en sus pasos.

## Assumptions

- El recorrido cubre el arranque de la aplicación hasta una sesión iniciada, pero no enseña a instalar PHP, Composer ni Termux: eso queda en la guía.
- La entidad de ejemplo es nueva y su nombre no colisiona con las entidades que ya existen en el repositorio.
- El demo de tareas puede usarse como contraste, pero el recorrido no depende de que esté instalado.
- Las convenciones vigentes del proyecto son la autoridad: un recorrido que las contradiga es un error del recorrido, no una alternativa válida.
- El artefacto se lee de arriba abajo, en orden; no está pensado como material de consulta salteada.
