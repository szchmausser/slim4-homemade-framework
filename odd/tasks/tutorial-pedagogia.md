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
  Resolución (L7): no hace falta captura nueva. `08-2-acciones.png` ya retrata el estado exacto del
  cap. 12 —encabezado con icono, subtítulo y botón, una fila con lápiz y papelera, sin paginador—;
  el demo `notes` como fuente habría mostrado el paginador del cap. 14, que en el cap. 12 todavía no
  existe. El defecto era la referencia: las tres secciones apuntaban a `06-7-encabezado.png`, retrato
  del cap. 6 sin acciones de fila, y afirmaban «la pantalla no cambia» sobre una pantalla que el
  lector ya no tiene.
- **S8 (precedencia de las cadenas)**: «fijate algo, hay un choque en lo que enseñamos en apenas la
  seccion "6.2 Ahora sí, las notas desde el modelo" y es que ya empezamos a usar la internacionalizacion,
  sin poner en contexto o explicar que siginifican lineas como est "<h1>{{ t('notes.title') }}</h1>"
  Deberias mantener la progresion sencilla a estas alturas del tutorial, y empezar a promover estas
  modificaciones para soportar regionalizacion cuando corresponda, o de plano, ir metiendo esa
  informacion aqui... yo recomiendo dejar la regionalizacion como algo para despues, y mantenerlo
  sencillo en estas primeras explicaciones... que propones para solucionar este tema de precedencia
  pedagogica en el tutorial respecto a las cadenas de texto y su regionalizacion?»
  Resolución (L9): se respeta el criterio —regionalización fuera— y se corrige la aplicación: el catálogo
  no se difiere porque la invariante lo exige desde la primera página y `t()` ya está en 98 líneas del
  documento. Una sola explicación donde nace la primera clave (4.1), referencias cortas en 6.1 y 6.2, y
  el límite declarado en Prerrequisitos.

## Tasks

- **T1** — Estructura atómica (S1, S2, S5, S6-fusión): nuevo esqueleto de 16 capítulos en 3 partes;
  fusionar viejos 6.1+6.2; renumerar cap. 6; mover viejos 6.8-6.10 → cap. 12; viejo 12 → 13; viejo
  13 (sin 13.5) → 14; viejo 13.5 → 15; viejo 14 → 16; Índice en fases; actualizar TODAS las
  cross-referencias; reescribir Antes/Después y prosa de las secciones movidas al estado post-CRUD;
  preservar la única copia verificada (page-header-title.twig, viejo 6.10). Ruta: inline. Commit: `4c1e901`.
- **T2** — Checks y piezas (S3, S4): caja Pieza de `button.twig` en 7.5; recortar re-enseñanza de
  `embed` en nuevo 13.1; camino de navegador para el 422 en 7.6 y 9.2 con `curl` opcional. Ruta:
  inline. Commit: `0ac12ff`.
- **T3** — Apéndice Phinx (S6): mover «Cómo funciona Phinx aquí» (viejo 2, líneas 219-250) a un
  apéndice tras Problemas frecuentes; dejar en cap. 2 solo lo operativo (número por comando, aviso
  del scaffold, rollback one-liner). Ruta: inline. Commit: `977e8dc`.
- **T4** — Verificación y capturas (S1-S7): cerrado. `composer docs:check` OK (24 md, 50 fuentes, 57
  copias). `composer verify` verde: lint, docs, PHPStan sin errores, 271 tests / 5699 assertions,
  migrate:fresh OK. Round-trip del demo `notes` (el estado final que el tutorial produce) verde: install +
  migrate + seeder de 12 notas, 286 tests / 5812 assertions, y `remove` sin dejar restos en el árbol.
  Capturas: resueltas por referencia y no por recaptura (S7/L7): las tres secciones del cap. 12 apuntan a
  `08-2-acciones.png` y sus epígrafes nombran la pantalla real. Sin re-walk en navegador: no hay estado
  nuevo que fotografiar ni comportamiento que este cambio toque, y la cobertura del estado final la da el
  round-trip del demo. Ruta: inline. Commit: `a338869` (capturas y comprobación de 12.1) y `6cceae3`
  (heading del cap. 2).
- **T5** — Cadenas (S8): explicación única del catálogo en 4.1 (clave/valor, comprobación de diez
  segundos), referencia corta en 6.1, `t()` nombrado como helper del proyecto en 6.2 y la regionalización
  declarada fuera de alcance en Prerrequisitos. Ruta: inline. Commit: `e78cd22`.

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
- **L7**: el usuario pidió verificar la auditoría de un tercero y cerrar lo pendiente. Verificación
  propia contra `68d5b33`: los hallazgos son exactos (cap. 6 = 636 líneas y 10 subsecciones en el
  baseline; `INSERT` manual en 788-789; 13.5 en 3055; promesa del navegador en la línea 16; `button.twig`
  con 17 menciones y 0 cajas Pieza; Phinx en 219). Correcciones menores al informe: la primera recompensa
  cae al 42%, no al 44%; el `curl` del 422 mide 36 líneas, no 20; la sección 10.1 mide 56 líneas aunque el
  wrapper son ~12; y 6.6 no es un paso «sin cambio visual» sino donde aparece el encabezado que las otras
  tres secciones reutilizan como captura.
- **L8**: cierre de T4. Se descarta recapturar (S7/L7) porque la captura correcta ya existe; se corrigen
  la referencia y la comprobación de 12.1, que comparaba contra la pantalla de 6.6 en vez de contra la
  que el lector tiene. Retoque: el heading del cap. 2 ya no repite el título del apéndice. Revisión de
  cross-refs del renumbering: los 20 «capítulo N» del documento apuntan al capítulo correcto.
- **L9**: el usuario eligió «literales primero y catálogo después» para S8. Verificación de alcance
  antes de aplicarlo: el literal ya existe (6.1 es HTML crudo con «Notas» escrito a mano) y `t()` aparece
  98 veces entre 6.2 y el cap. 15; diferir el catálogo exigía reescribir esas 98 líneas o enseñar código
  que viola la invariante durante diez capítulos. Aplicación acordada: explicarlo una vez en 4.1 (donde
  aparece `'label' => 'nav.notes'` y no había explicación del helper), dejar 6.1 y 6.2 como referencias,
  y declarar la regionalización fuera de alcance. Sin capítulo nuevo y sin renumeración.
- **L10**: tras T5 quedaba un puntero desactualizado en la sección de transferencia (cap. 16): remitía a la
  guía §15.3 para el `Trans::t()` del flash, que ahora se explica en 4.1. Se alinea: la transferencia
  apunta a 4.1 y 6.2 para el mecanismo y a la guía solo para el resolver de locale.
