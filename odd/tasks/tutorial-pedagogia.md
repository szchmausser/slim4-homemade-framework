# Reestructuración pedagógica del tutorial CRUD

Objetivo: aplicar las propuestas A–F de la auditoría didáctica (mem #1918) sobre `docs/tutorial-crud.md`:
aliviar la densidad del cap. 6, consolidar la extracción de componentes en una fase, sacar el refactor
de renders de paginación, y corregir checks y señalización. Rama `docs/tutorial-pedagogia`, base `68d5b33`.
Estrategia de entrega: `single-pr` (la renumeración es atómica; los slices intermedios no son entregables
independientes). Runner: `composer verify` + re-walk de los capítulos afectados con ego-browser.

## Specs

- **S1 (A)**: «Partir el cap. 6 en la costura natural. 6.1-6.5 = "la pantalla" (termina con lista
  estilizada y encabezado visible: payoff real). Mover 6.6-6.10 al bloque del cap. 12, así toda la
  extracción de componentes vive en una fase, después de que el CRUD funciona.»
  Resolución de implementación (L4): se mueven solo las extracciones de componentes reutilizables
  (viejos 6.8, 6.9, 6.10); los partials de dominio cargados por features posteriores (viejo 6.6 panel,
  viejo 6.7 encabezado con botón) quedan en el cap. 6. El cap. 6 fusiona viejos 6.1+6.2 (F) y renumera.
- **S2 (B)**: «Sacar 13.5 de paginación: al final del cap. 9 (cuando el dolor de la duplicación está
  fresco) o a un micro-capítulo "un solo camino de render" antes del 13. Paginación enseña solo
  paginación.» Resolución (L4): viejo 13.5 → nuevo cap. 15 «Un solo camino de render», después de
  paginación, porque `renderList()` nace en 13.3 y el guard `HX-Push-Url` en 13.4. Transferencia → 16.
- **S3 (C)**: «Darle a `button.twig` su caja Pieza en 7.5 u 8.2, y en 12.1 cortar la re-enseñanza de
  `embed`: referenciar 6.8/7.5 y enseñar solo lo nuevo (`row` se renderiza una vez por item).»
- **S4 (D)**: «Arreglar el check del 422: camino de navegador primario (quitar `required`
  temporalmente, o un `fetch` de una línea desde devtools) y `curl` como opcional. O decir
  honestamente en 7.6 que este check necesita `curl` y por qué.»
- **S5 (E)**: «Señalar las tres fases en el Índice: 1-5 Fundamentos / 6-11 CRUD completo y probado /
  12-14 Piezas reutilizables y transferencia. El intro ya las describe (63-65); el Índice no las
  muestra. Cero cambio de contenido.» (Con la nueva estructura: 12-16.)
- **S6 (F)**: «Mover el sidebar de Phinx a Problemas frecuentes; colapsar 6.1+6.2 en un paso (el
  contraste es invisible) y conservar el paso crudo solo donde el defecto es observable (8.1, 10.1).»
  El sidebar va a un apéndice (no es una tabla síntoma→causa). La fusión 6.1+6.2 se hace en T1.
- **S7 (capturas)**: «si necesitas actualizar las capturas usa ego browser para que hagas las nuevas
  tomas de las secciones mejoradas que propones». Nuevas capturas del cap. 12 (encabezado sobre lista
  poblada con acciones) y de toda sección cuyo estado visual cambie por la reestructura.

## Tasks

- **T1** — Estructura atómica (S1, S2, S5, S6-fusión): nuevo esqueleto de 16 capítulos en 3 partes;
  fusionar viejos 6.1+6.2; renumerar cap. 6; mover viejos 6.8-6.10 → cap. 12; viejo 12 → 13; viejo
  13 (sin 13.5) → 14; viejo 13.5 → 15; viejo 14 → 16; Índice en fases; actualizar TODAS las
  cross-referencias; reescribir Antes/Después y prosa de las secciones movidas al estado post-CRUD;
  preservar la única copia verificada (page-header-title.twig, viejo 6.10). Ruta: inline. Commit: —.
- **T2** — Checks y piezas (S3, S4): caja Pieza de `button.twig` en 7.5; recortar re-enseñanza de
  `embed` en nuevo 13.1; camino de navegador para el 422 en 7.6 y 9.2 con `curl` opcional. Ruta:
  inline. Commit: —.
- **T3** — Apéndice Phinx (S6): mover «Cómo funciona Phinx aquí» (viejo 2, líneas 219-250) a un
  apéndice tras Problemas frecuentes; dejar en cap. 2 solo lo operativo (número por comando, aviso
  del scaffold, rollback one-liner). Ruta: inline. Commit: —.
- **T4** — Verificación y capturas (S1-S7): `composer verify`; re-walk de los capítulos afectados
  (6, 7.5, 7.6, 12, 13, 14, 15) en worktree limpio con ego-browser; nuevas capturas de las secciones
  movidas; diff del estado final contra `framework/scaffolds/notes/`; `composer docs:check` verde.
  Ruta: inline (delegación no disponible en este runtime). Commit: —.

## Log

- **L1**: «quiero que verifiques el tutorial que venimos trabajando, y me digas desde el punto de vista
  didactico o pedagogico, si la progresion actual es correcta o si hay secciones que mezclan cosas que
  no deberian haciendolas pesadas de estudiar... que propones o mejorarias al respecto? si requieres
  visualizar el sitio en el navegador, puedes usar la skills de ego browser»
- **L2**: «aborda todas tus propuestas, si necesitas actualizar las capturas usa ego browser para que
  hagas las nuevas tomas de las secciones mejoradas que propones»
- **L3**: auditoría pedagógica completa en mem #1918 (topic `guia/tutorial-crud/revision-pedagogica`).
  Hallazgos: cap. 6 maratón (665 líneas, 4 subsecciones sin cambio visual); primera recompensa en 7.2
  (~44%); extracción dispersa + button.twig sin Pieza; 13.5 refactor ajeno a paginación; check 422
  rompe promesa de navegador; fatiga construir-para-demoler; sidebar Phinx front-load; Índice sin fases.
- **L4**: decisiones de resolución. (a) Principio: los partials de dominio (`_panel`, `_form`) quedan
  con su feature porque son prerrequisitos de caps. 7-13; las extracciones de componentes reutilizables
  (`page-header`, `action-bar`, `list/simple`) se consolidan en la fase III. Por eso se mueven viejos
  6.8-6.10, no 6.6-6.7. (b) `renderList()` nace en viejo 13.3 y el guard en 13.4: el refactor de
  renders no puede ir antes de paginación; va a nuevo cap. 15. (c) Estructura final: Parte I
  Fundamentos (1-5), Parte II El CRUD completo (6-11), Parte III Piezas reutilizables (12-16).
  Cap. 6 queda en 6 subsecciones; cap. 12 «El encabezado a componentes» (3); cap. 13 «La lista a un
  componente» (4); cap. 14 «Paginación del servidor» (4); cap. 15 «Un solo camino de render»;
  cap. 16 «Repetir el patrón con otra entidad».
- **L5**: gates y entorno. `composer docs:check` baseline OK (24 md, 50 fuentes, 57 copias). Única
  copia verificada en el tutorial: `resources/views/components/ui/page-header-title.twig` en viejo
  6.10 (línea 1249); preservar fence + intro al mover. `DocsLiveViewNamesTest` excluye
  `tutorial-crud.md`. RDD off (global): sin review nativo; checks ordinarios. Delegación a subagentes
  no disponible (gentle-ai-explore: variante de modelo; general: permission denied) → todo inline.
- **L6**: la renumeración es atómica (no se puede renumerar a medias un doc con cross-refs); el commit
  de T1 puede superar las ~400 líneas del heuristic por ser un slice mecánico coherente. Se explica en
  el mensaje de commit.
