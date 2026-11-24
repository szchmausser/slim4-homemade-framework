# ListQuery: búsqueda, filtro y orden por URL

Objetivo: un primitivo que convierta `?q=&sort=&dir=&<filtro>` en una consulta segura sobre un whitelist,
y que componga con la paginación existente. Base `975e591`.

## Specs

- **S1**: el usuario eligió «ListQuery: búsqueda, filtro y orden» sobre una propuesta con esta forma:
  «un primitivo que resuelva búsqueda, filtro y orden con whitelist, integrado con la paginación que ya
  existe; cero dependencias, cero build, cero JS nuevo: el estado va en la URL».
- **S2 (estado en la URL)**: `q` (término), `sort` (una columna del whitelist), `dir` (`asc`/`desc`) y
  filtros nombrados. Nada en sesión: la URL es el estado y se comparte.
- **S3 (seguridad, es el corazón)**: el nombre de columna **nunca** sale del request; solo entran las
  columnas declaradas en el whitelist. `dir` se normaliza a dos literales. `q` se recorta y se limita en
  largo. Los comodines de `LIKE` (`%`, `_`) del término se escapan para que buscar `100%` busque eso y no
  «todo».
- **S4 (composición)**: `ListQuery::url('/tasks')` devuelve la ruta con su query string, y la paginación
  conserva ese estado en los links. `Pagination` gana un helper de URL de página que elige `?` o `&` según
  corresponda; el componente `pagination/simple.twig` lo usa.
- **S5 (superficies demo)**: `tasks` usa búsqueda (`title`), filtro (`status`) y orden (`title`,
  `created_at`); `notes` usa búsqueda (`title`, `body`) y orden (`title`, `created_at`). Los textos nuevos
  van al catálogo de cada demo, en `es` y `en`.
- **S6 (contacts queda afuera)**: su orden no viene de la URL sino de Tabulator (`sort[i][field]`,
  multi-orden). Migrarlo obligaría a que el primitivo acepte esa forma; se documenta como contrato
  distinto y no se toca.
- **S7 (docs)**: sección nueva en la guía (junto a la de paginación), fila de `framework/Http` con la pieza
  y nota de por qué `contacts` no la usa.

## Tasks

- **T1** — Primitivo y composición (S2-S4): `framework/Http/ListQuery.php`, helper de URL de página en
  `Pagination`, uso en `pagination/simple.twig`, y tests unitarios + HTTP del escapado, el whitelist, la
  normalización de `dir`, los filtros inválidos y la URL con y sin query string. Ruta: worker. Commit:
  `9837b1b`
- **T2** — Demos y docs (S5-S7): `tasks` y `notes` con el formulario GET y los links de orden en `url()`,
  textos en los catálogos de cada demo (es/en), tests HTTP de cada demo, guía y fila del mapa. Ruta:
  worker. Commit: `b58b294`
- **T3** — Verificación: cerrado. `composer test` 336 tests / 5448 assertions sin demos; 400 con `tasks`;
  358 con `notes`; `lint`, `phpstan` y `docs:check` verdes. Verificación independiente
  (`gentle-ai-verify`) sobre copia en scratch: **inyección OK** (nombres y valores del request mueren en el
  parseo; `sort=title);drop`, `dir=ASC;--`, `q=%`, `q=_`, `q=\`, arrays de parámetros, todos neutralizados;
  `ESCAPE '\'` con bindings y la barra escapada primero), tests OK (mutaciones probadas: aceptar cualquier
  `sort` → 6 fallos; saltear el escapado → 2; aceptar cualquier filtro → 4; separador fijo → 3), i18n OK.
  Tres CONCERN, todos cerrados: el ejemplo de la guía no coincidía con el código (ahora usa dos sentencias
  por el tipado de PHPStan), la frase de `pageUrl()` confundía los dos caminos (`page_url_base` para los
  `<a>`, `pageUrl()` para `HX-Push-Url`), faltaba la salvedad de `contacts` en `AGENTS.md`, y el lápiz de
  notes perdía el estado (arreglado con RED/GREEN). Ruta: padre + verificador. Commit: `9837b1b` (primitivo),
  `b58b294` (demos) y `322cf2c` (documentación).

## Log

- **L1**: «fijate todas las piezas que le hemos puesto a este framework, que mas crees que conviene
  agregarle, es un framework para uso general... respetando sus alcances y limitaciones que me sugeririas
  como proxima feature util?»
- **L2**: elección: «ListQuery: búsqueda, filtro y orden».
- **L3**: corrección de alcance antes de escribir: la propuesta original decía «migrando el orden ad hoc de
  `contacts`». Al leerlo, ese orden es de Tabulator (`sort[i][field]`, multi-orden, contrato AJAX), no
  estado de URL; migrarlo contorsionaría el primitivo. Se aplica a `tasks` y `notes` (server-rendered) y
  `contacts` queda documentado como contrato distinto (S6).
- **L4**: decisiones. (a) Vive en `framework/Http` junto a `Pagination`, que ya mezcla consulta y HTTP.
  (b) API por opciones declaradas (whitelist), no por convención de nombres de columna. (c) El primitivo
  no pagina: compone con `Pagination`. (d) Sin JS: un formulario GET y links de orden.
- **L5**: la verificación independiente marcó que el HTML de la paginación **no** es byte-idéntico: además
  del separador, cambia `hx-include="this"` por `"closest form"` (necesario para los campos ocultos; es
  equivalente cuando no se pasan parámetros) y una indentación. Las URLs sí son idénticas. Se acepta y se
  documenta: la afirmación «byte-idéntico» solo vale para las URLs.
- **L6**: límites conocidos que quedan: `apply()` no es idempotente (aplicarlo dos veces duplica cláusulas);
  hay arrays de paginación armados a mano en tests que no traen `page_url_base` y el componente, con
  `strict_variables=false`, emitiría el link sin separador (ningún test los renderiza); las mutaciones de
  `notes` siguen redirigiendo a `/notes` sin estado (comportamiento previo del demo). El formulario de alta
  de `tasks` postea solo `?page=&per_page=` (heredado, no regresión de esta feature).
- **L7**: T5 verificado en navegador real (ego-browser) con la app corriendo. Estado inicial: 10 filas y sin
  enlace de limpiar. Al tipear `demo 42` con pausas de 150 ms: **1 fila** («Tarea demo 42»), la URL pasa a
  `?q=demo%2042&sort=created_at&dir=desc&page=1&per_page=10`, el foco sigue en `#task-search`, la marca
  `window.__probe` sobrevive (no hubo recarga) y aparece «Limpiar» con `href="/tasks"`. Al clickearlo, la
  URL queda vacía y el input también. Confirmado: debounce, foco y caret conservados, push-url y limpiar.
  La × nativa del `type="search"` quedó sin ejercitar, pero dispara el mismo evento `search` que ya está en
  el trigger. El usuario temporal se creó y se borró, el servidor se detuvo y el demo se desinstaló: el
  árbol quedó sin demos.
- **L8**: el usuario reportó que «Limpiar» necesitaba dos clics. No se pudo reproducir en Chromium
  (automatizado ni con clic real), pero el mecanismo es claro y dependiente del motor: el trigger incluía
  `change` para el input de texto, así que al hacer clic en el enlace el input perdía el foco, disparaba
  `change`, htmx pedía y reemplazaba el panel justo cuando el clic iba a navegar. Fix: el texto escucha
  solo `input changed delay:400ms` y `search`; `change` queda acotado al selector con `change from:select`
  (`notes` no lo necesita). Verificado en navegador con clic real y el input enfocado: limpia al primer
  clic, la búsqueda sigue en vivo (10 filas → 1 con `demo 42`) y el selector dispara (`status=completado`).
  Suites: notes 26/206, tasks 68/394, base 336/5781; `docs:check` verde. Commit `1edfd7d`.
- **S8 (búsqueda en vivo y limpiar)**: «se puede hacer la busqueda en vivo en los demos de tareas y notas? no
  me gusta tener que presionar un boton para que se inicie la busqueda... tambien hace falta un boton para
  limpiar la busqueda y resetear todos los filtros». Implementación con HTMX sobre el formulario existente
  (`hx-get` + `hx-trigger="input changed delay:400ms, change, search"` + `hx-push-url`), con `hx-preserve` e
  `id` en el formulario para que el input no pierda foco ni caret cuando se reemplaza el panel. El control
  de limpiar es un enlace a la ruta base (sin estado), con texto de catálogo, visible solo si hay estado
  activo. Sin JS propio, y el envío normal queda intacto para quien no tiene JS.
- **T5** — Búsqueda en vivo (S8): atributos HTMX + `hx-preserve` en los formularios de `tasks` y `notes`,
  `type="search"` para que la × nativa dispare `search`, enlace de limpiar con `list_query.active()`,
  claves de catálogo en es/en, tests del render parcial con `q` y de los atributos, y nota en la guía.
  Ruta: worker. Commit: `e048e4c`
