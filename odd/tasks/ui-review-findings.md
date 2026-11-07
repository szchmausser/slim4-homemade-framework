# ui-review-findings

Cierre de los hallazgos 1-4 de la revisión de calidad de UI, componentes y arquitectura de vistas.
Alcance: sidebar, validación (mensajes + errores por campo), semántica de fallo, guardas de clases.

## Specs

S1 — El sidebar mantiene su estado coherente al cruzar el breakpoint `lg`: al pasar a escritorio la navegación queda alcanzable con teclado y mouse; al pasar a móvil el drawer no queda abierto tapando el contenido.

S2 — Una regla de validación desconocida falla de forma ruidosa (excepción con la regla nombrada), nunca valida en silencio.

S3 — Los mensajes de validación se muestran en castellano en toda la UI. Hoy el flash de un formulario inválido dice "The value must not be empty" / "must be valid email" / "must have a length lower than or equal to 50".

S4 — Los errores de validación por campo llegan a la vista: el campo marca `aria-invalid="true"`, referencia su mensaje con `aria-describedby`, y el mensaje se lee junto al campo. `Validator::errors()` deja de tener cero llamadores.

S5 — Un fallo de validación no responde 2xx: la petición de un recurso inexistente en petición de documento completo responde 404; htmx sigue recibiendo un panel re-renderizado cuando la petición es un fragmento.

S6 — El formulario de alta de tareas sólo se limpia cuando el servidor confirmó éxito, nunca en un fallo ("en éxito cuando el usuario no pierde lo tipeado").

S7 — Un fallo de alta o edición en contactos re-renderiza el formulario con los valores tipeados y los mensajes, en lugar de redirigir y descartar lo escrito.

S8 — Las guardas de clases cubren las dos demos y toda la familia de clases de proyecto, no sólo `icon-`. `framework/scaffolds/contacts/resources/views` deja de estar fuera de `ViewClassIntegrityTest`.

## Tasks

| id | spec | route | estado |
|---|---|---|---|
| T1 | S1 | inline | hecho |
| T2 | S2, S3 | inline | hecho |
| T3 | S4 | inline | hecho |
| T4 | S5, S6 | inline | hecho |
| T5 | S7 | inline | hecho |
| T6 | S8 | inline | hecho |
| T7 | verificación | inline | hecho |

## Log

L1 — "aborda del 1 al 4"

L2 — Revisión original: "quiero que hagas una revision de calidad en el codebase actual, sobre todo a nivel de UI y componentes y como los implementamos... quiero saber si encuentras malas practicas, errores o areas de mejora"

L3 — "necesito que delegues una segunda revision, con el mismo scope del primero y que tambien el resultado de esta primera revision la sometas a revision y confirmacion con otro agente, es decir lanzaras una revision nueva y otra de verificacion de estos primeros hallazgos, luego, filtra el ruido y presenta los resultados por prioridad o criticidad de los hallazgos encontrados"

L4 — Propuesta aceptada, items 1-4: (1) sidebar con `matchMedia`/`resize` + test de contrato del layout; (2) validación con mensajes en castellano, `errors()` cableado a `field.twig` con `aria-invalid`/`aria-describedby` y regla desconocida que explota; (3) 422/404 reales y reset del formulario condicionado a eso; (4) cerrar huecos de las guardas (scaffold de contactos + familia completa de clases de proyecto).

L5 — Evidencia de la verificación adversarial: `sidebarOpen` se inicializa leyendo `window.innerWidth` una vez y no hay listener de `resize`/`matchMedia`; `Validator` ejecutado con `'requird|mxa:120'` devuelve `fails() === false`; mensajes reales de Respect filtrados a `Flash::set`; `TaskController::store` devuelve 200 en fallo mientras el comentario de `tasks/index.twig:22` afirma que es false en un 400.
