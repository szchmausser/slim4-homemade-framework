# Research — Recorrido de primera entidad

Decisiones del plan. Cada una resuelve un punto que la spec deja abierto a propósito, o que el
recorrido necesita fijar para no improvisar durante la implementación.

## Entidad de ejemplo

- **Decision**: `notas`, con dos campos: `titulo` (requerido, 1–120) y `cuerpo` (opcional). Tabla `notas`, ruta base `/notas`.
- **Rationale**: no colisiona con `tasks` ni `contacts`; dos campos alcanzan para mostrar validación (rechazo por título vacío o demasiado largo) sin que el recorrido se vuelva un ejercicio de modelado. El propósito es el camino, no la entidad.
- **Alternatives considered**: `tasks` (rechazada: obliga a copiar o desarmar el demo); `recetas` (rechazada: `conventions.md` ya la usa como ejemplo de nombre de migración, presta a confusión); una entidad de tres o más campos (rechazada: agrega pasos sin agregar enseñanza).

## Registro de rutas

- **Decision**: mostrar `bootstrap/app.php` donde se registran los archivos de rutas —como excerpt— y el archivo nuevo `routes/notas.php` completo. Explicar el patrón condicional que usan los demos, sin copiarlo.
- **Rationale**: el lector tiene que entender que una ruta existe porque algo la registra, no porque el archivo esté en disco. El patrón condicional de los demos es una decisión de producto (demos removibles), y copiarlo convertiría su entidad en un demo.
- **Alternatives considered**: pedir un `require` dentro de `routes/web.php` (rechazada: mezcla dominio del usuario con el arranque del framework); no explicar el registro (rechazada: deja el paso como magia).

## Primera comprobación

- **Decision**: la primera ruta devuelve JSON con `Content-Type: application/json` y el cuerpo exacto `{"status":"ok","recurso":"notas"}`, escrito en el texto antes de que el lector ejecute la petición.
- **Rationale**: FR-017 pide una respuesta simple verificable por petición directa antes de vistas; con JSON no hay ambigüedad de renderizado y el lector compara cadena contra cadena.
- **Alternatives considered**: `var_dump` o texto plano (rechazada: menos comparable); una página HTML mínima como primera comprobación (rechazada: adelanta vistas y debilita la progresión).

## Vistas

- **Decision**: una vista que extiende `layouts/app.twig` y usa `{% block content %}` con un título y un texto; después, el listado como región HTMX. Sin utilidades `dark:` y sin assets nuevos.
- **Rationale**: reutiliza el shell existente, que es justamente la pieza que hay que enseñar a usar. Introduce HTMX como idioma del proyecto, no como adorno.
- **Alternatives considered**: vista autónoma sin layout (rechazada: enseña a salirse del sistema); Alpine para el listado (rechazada: el proyecto resuelve el listado en el servidor).

## Migración

- **Decision**: numeración secuencial, tomando el siguiente número libre del repositorio en el momento de escribir el recorrido; el texto explica cómo calcularlo en lugar de fijarlo como si fuera eterno.
- **Rationale**: la convención del proyecto es secuencial y sin timestamps. Un número hardcodeado en un tutorial envejece en el primer cambio de esquema.
- **Alternatives considered**: poner el número en un placeholder genérico (rechazada: el lector necesita un ejemplo concreto); usar timestamps (rechazada: viola la convención vigente).

## Cierre con tests

- **Decision**: tres casos al cierre —alta correcta, validación que rechaza, identificador inexistente que avisa— sobre `Tests\Support\HttpTestCase`, con sesión inyectada y limpieza por test. Un test completo en el texto y los otros dos por contraste.
- **Rationale**: reproduce la convención real de `tests/Http/` y cumple FR-009: los tests reemplazan las comprobaciones manuales que el lector ya hizo, no las repiten con otra sintaxis.
- **Alternatives considered**: un harness propio del recorrido (rechazado en clarify); tests desde el principio (rechazado: interrumpe la progresión manual).

## Interacción del ejemplo

- **Decision**: la lista del primer recorrido se escribe a mano con `<ul>`; al llegar a la región HTMX se reemplaza por `components/list/simple.twig`, explicando qué se ganó. La paginación no existe en el primer recorrido: aparece cuando la lista crece, con `Pagination::paginate()` y `components/pagination/simple.twig`. El campo `cuerpo` se mantiene (requerido vs opcional, dos reglas). Los errores se muestran junto al campo (`components/form/field.twig` con `error`) y el éxito por toast. Se agrega `GET /notas/{id}` para poder demostrar el aviso ante un identificador inexistente (FR-009).
- **Rationale**: cada pieza aparece cuando el problema que resuelve ya se sintió; presentarlas antes las vuelve obligatorias y el lector no aprende cuándo usarlas (criterio del contrato de pieza). Escribir la lista a mano primero es lo que hace visible qué aporta el componente. Los dos canales de aviso (campo y toast) son los que el proyecto ya usa, así que enseñarlos no inventa un patrón.
- **Alternatives considered**: paginar desde el primer paso (rechazada: la paginación sin problema visible es ceremonia); Tabulator (rechazada: fuera de alcance en `concept.md`, y es una isla avanzada con demo propio); sólo `titulo` (rechazada: pierde el contraste requerido/opcional); un solo canal de aviso (rechazada: el proyecto usa dos y el lector los va a encontrar igual).

## Validación sin contaminar el repositorio

- **Decision**: el recorrido se valida siguiéndolo en una copia descartable del repositorio (clon local en un directorio temporal), no en la working tree. La entidad de ejemplo nunca queda en el árbol de trabajo.
- **Rationale**: validar en la working tree dejaría `notas` en `app/`, `routes/`, `resources/` y `tests/`, es decir, agregaría código de producción que la spec no autoriza (FR-011) y rompería el supuesto de «el artefacto no agrega código». Una copia descartable permite seguir el recorrido literalmente, incluida la migración y la siembra, sin dejar rastro.
- **Alternatives considered**: validar y limpiar con `git clean -fd` (rechazada: borra trabajo no relacionado si alguien tiene cambios sueltos); validar sólo leyendo (rechazada: no es validación, es relectura); dejar la entidad de ejemplo instalada como demo (rechazada: convierte el tutorial en un tercer demo).

## Descubribilidad

- **Decision**: una única línea en el índice de guías del `README.md`, con enlace relativo a `tutorial-crud.md`.
- **Rationale**: FR-012 permite explícitamente esa línea; sin ella el material no se encuentra desde ningún lado.
- **Alternatives considered**: no tocar el README (rechazada en clarify); agregar un bloque de presentación (rechazada: es modificar la guía por la puerta de atrás).

## Idioma y estilo

- **Decision**: prosa en español, código, identificadores y rutas en inglés; mensajes de interfaz en español.
- **Rationale**: es la política vigente del proyecto (`DESIGN.md` § Nombres y `.ai/steering/conventions.md`).
- **Alternatives considered**: todo en inglés (rechazada: la audiencia y el resto de la documentación están en español).
