# Feature: ui-estado-y-accesibilidad

Estandarización y mejora de UI de `resources/views`: que la interfaz diga la verdad sobre el estado de las acciones y sobre quién puede operarla. Modo Operate (app de tareas, server-rendered, sin build).
Antecedente verificado: crítica de dos pasadas + tres revisiones ciegas independientes. Los tres P1 ya están cerrados en `fix/list-state-contract` (`1cf123f`).

## Specs

- **S1** — Todo icono que comunique estado o acción alcanza **≥3:1** contra su fondo, en tema claro y oscuro. Medido con los tokens reales de daisyUI 5. Hoy: `text-success` 1.96:1, `text-error` 2.87:1, `text-base-content/40` 2.53:1 en claro (en oscuro pasan).
- **S2** — Ninguna clase referenciada en las plantillas falta del bundle vendorizado. Guard test que compara cada clase de `resources/views` contra `public/assets/daisyui-5.7.46.css`. Hoy fallan: `bg-base-100/95`, `outline-primary`, `form-control`, `label-text`, `input-bordered`, `select-bordered`.
- **S3** — El aviso flotante (único canal de feedback de la app) tiene fondo opaco legible; el texto no queda flotando sobre el contenido de la página.
- **S4** — Después de una acción de fila (completar, editar, borrar, guardar, cancelar) el foco de teclado sigue en la zona de la fila accionada, no vuelve al shell. Hoy cada swap con `outerHTML` destruye el nodo enfocado.
- **S5** — (a) El sidebar fuera de pantalla en móvil no recibe foco ni aparece en el orden de tabulación. (b) Plegado en desktop, cada enlace conserva un nombre accesible. Hoy solo se anima `translate-x` y `.sidebar-label{display:none}` deja los enlaces con `data-tip`, que no es un nombre.
- **S6** — Todo control de formulario tiene nombre accesible (`label`, `aria-label` o `placeholder` visible-asociado). Hoy el input de edición inline no tiene ninguno.
- **S7** — La página de error usa el sistema visual de la app y su mensaje sale en español. Hoy no carga hoja de estilos (sus ~10 utilidades son inertes) y `{{ message }}` llega en inglés desde el vendor.
- **S8** — Toda mutación htmx que falla produce un aviso visible. Hoy CSRF 400 sí lo tiene; un 5xx no: htmx no intercambia y no hay listener `htmx:responseError`.
- **S9** — Una mutación invocada sin htmx (POST normal, URL directa) devuelve una página completa, no un fragmento. Hoy `store/destroy/edit/update/toggle` renderizan siempre `_panel.twig`; solo `index()` ramifica por `HX-Request`.
- **S10** — `prefers-reduced-motion: reduce` cubre todas las transiciones, no solo la barra del toast. Hoy quedan fuera `transition-all duration-200` del sidebar y los `x-transition` de toasts y tooltips.
- **S11** — La posición de la lista (`page`, `per_page`) viaja en la URL (`hx-push-url`), así recargar, volver atrás o compartir no devuelve siempre a la página 1.

## Tasks

- **T1** — Guard de clases muertas (S2): test que extrae las clases de las plantillas y verifica su existencia en el bundle; falla con las 6 actuales. Ruta: inline, test-first. Commit: pendiente.
- **T2** — Corregir las clases muertas (S2, S3): `bg-base-100/95` → paso existente; `outline-primary` → variante válida; quitar del HTML las v4 (`form-control`, `label-text`, `*-bordered`) resolviendo lo que aportaban. Superficies: `app.twig`, `_list.twig`, `login.twig`, `index.twig`, `_pagination.twig`. Commit: pendiente.
- **T3** — Contraste de iconos de estado (S1): tokens de tinta propia o chip semántico, según decisión abierta D1. Superficie: `_list.twig`. Commit: pendiente.
- **T4** — Foco tras swap (S4): mecanismo según decisión abierta D2. Superficie: `_list.twig` + listener en `app.twig`. Commit: pendiente.
- **T5** — Sidebar accesible (S5): `inert`/`aria-hidden` fuera de pantalla y nombre accesible al plegar. Superficie: `app.twig`. Commit: pendiente.
- **T6** — Nombre accesible del input de edición (S6). Superficie: `_list.twig`. Commit: pendiente.
- **T7** — Página de error integrada (S7): hoja mínima compartida y mensaje en español. Superficies: `errors/error.twig` + controlador de errores. Commit: pendiente.
- **T8** — Fallo silencioso (S8, S9): listener `htmx:responseError` y rama `HX-Request` en las mutaciones. Superficies: `app.twig`, `TaskController.php`. Commit: pendiente.
- **T9** — Reduced motion completo (S10). Superficie: `app.twig` + `_list.twig`. Commit: pendiente.
- **T10** — Estado en la URL (S11). Superficie: `_list.twig`, `_pagination.twig`, `app.twig`. Commit: pendiente.
- **T11** — Pendientes menores (P3): favicon + `<link rel="icon">`, alta fallida no debe borrar lo tipeado (`hx-on::after-request` condicionado al éxito), URLs raíz-absolutas para subpath, marca placeholder, footer duplicado, "registros"→"tareas", tamaño alta vs edición, etiqueta visible de `per_page`, fallback silencioso de `_icon.twig`. Commit: pendiente.
- **T12** — Bloqueado: peso del motor de estilos (1.47 MB bloqueantes + JIT en navegador). Requiere decisión de producto: aceptar un paso de build contradice la premisa "sin toolchain" del README. No se toca sin esa decisión.

## Decisiones abiertas

- **D1 (T3)** — Contraste de iconos: (a) tokens de tinta propia en `@theme` (`--color-success-ink`, `--color-error-ink`) usados solo como color de icono; (b) chip con fondo semántico (`bg-success/15` + icono oscuro), cambia el diseño de la fila; (c) el check pasa a `text-base-content` y el verde queda como acento secundario.
- **D2 (T4)** — Foco tras swap: (a) mantener el swap del panel y restaurar el foco explícitamente (`data-focus-key` + `htmx:afterSettle`); (b) swaps a nivel de fila (`<li>`), preserva el foco por construcción pero obliga a resolver flash y paginación por separado; (c) dejarlo en P3.

## Log

- **L1** — Pedido original, verbatim: «quiero que con la skill impeccable, me digas que esta bien y que esta mal a nivel de frontend en este proyecto, que es corregible o mejorable, esta es una tarea de estandarizacion y mejora u optimizacion de UI». Resultado: crítica con 10 heurísticas (27/40), 5 P1 y 8 observaciones.
- **L2** — Pedido, verbatim: «quiero que hagas una segunda revision, y filtres que es un hallazgo real y que es ruido, y encuentres de ser posible nuevos hallazgos reales, y todos los hallazgos los clasifiques con una prioridad segun importancia o criticidad». Resultado: 25/40 corregido; descartados como ruido "sin capa de tokens", "contraste por opacity" (medido: 4.64/6.54/9.39:1, pasan AA), "el estado solo se ve al hacer click", "sin ruta de teclado al alta".
- **L3** — Pedido, verbatim: «genera esa revision en 3 agentes en simultaneo con contexto nuevo y las mismas indicaciones y filtra sus resultados tal como te lo pedi y organizalos por prioridad». Resultado: adjudicación ciega triple; hallazgos nuevos confirmados por grep sobre el bundle: `bg-base-100/95` inexistente, paginación sin `href`, foco perdido tras swap, sidebar en el tab order.
- **L4** — Decisión de ejecución: 2 escritores en paralelo sobre superficies disjuntas, con el contrato fijado antes por tests que escribe el padre. Motivación: P1-1 y P1-2 comparten el contrato de URL; `tests/bootstrap.php` recrea una SQLite compartida, así que la suite no puede correr en paralelo.
- **L5** — Pedido, verbatim: «comitea y arregla el tests tambien, luego avisame para que me digas que es lo siguiente a abordar, de que trata y cual es tu plan para implementarlo». Resultado: rama `fix/list-state-contract`, commits `1cf123f` (contrato de estado de la lista + guarda de envío) y `63aa4f4` (BackupTest sin depender del separador de ruta). Suite: 112 tests, 452 assertions, 0 fallos. `assess` devolvió `medium`, verificación del escritor, sin verificador independiente.
- **L6** — Evidencia: el hueco del split lo reportó el propio escritor A — `hx-put` (guardar edición) quedaba sin posición. Cerrado en `1cf123f` con su test. Dos asserts preexistentes (`DeleteTaskTest:92`, `EditTaskTest:63`) fijaban las URLs viejas y se relajaron; la cobertura del query string vive en `ListStateContractTest`.
- **L7** — Pedido, verbatim: «sobre .impeccable/ se versiona o no? si quiero seguir trabajando en las correcciones en otra computadora, lo necesito versionado o no?» + «ok, de acuerdo con tus recomendaciones». Decisión: `.impeccable/` no se versiona (caché local regenerable, `target_identity` con ruta absoluta de esta máquina, medición ya vencida por `1cf123f`); lo durable es este documento, `PRODUCT.md` y `DESIGN.md`. Efecto secundario buscado: desbloquea `assess`, que fallaba por archivos sin trackear.
