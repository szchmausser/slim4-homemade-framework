# Filtros de la grilla de contacts (Tabulator nativo)

Objetivo: filtrar los contactos desde la propia grilla con los filtros de cabecera de Tabulator y
`filterMode: remote`, sin UI propia. Base `874bf42`.

## Specs

- **S1**: «en el caso de tabulator, en vivo, 400ms». Se implementan los filtros de cabecera nativos, con
  `headerFilterLiveFilterDelay` de 400 ms.
- **S2 (solo features de la librería)**: `filterMode: "remote"`, `headerFilter: "input"`,
  `headerFilterFunc: "like"`, `headerFilterPlaceholder` por columna y `dataSendParams` fijado
  explícitamente (la documentación se contradice entre `filter` y `filters`). Nada de cajas propias,
  debounce casero ni `setFilter` manual.
- **S3 (servidor)**: los filtros llegan como array de `{field, type, value}` en el parámetro fijado. `field`
  pasa por whitelist (`name`, `email`, `phone`), `type` por un conjunto permitido (`like`, `=`), y el valor
  se escapa para `LIKE` con `ESCAPE` (mismo criterio que `ListQuery`). **Se filtra antes de paginar** y
  `last_page`/`last_row` se calculan sobre el conjunto filtrado.
- **S4 (estado y borde)**: `paginationOutOfRange: 'first'` para que un filtro que reduce las páginas no deje
  la tabla en una página vacía. Tabulator vuelve solo a la página 1 al cambiar el filtro.
- **S5 (textos)**: los placeholders de cada filtro van al catálogo de `contacts` en `es` y `en` y llegan al
  JS por el JSON que ya se inyecta: el JS sigue sin texto visible.
- **S6 (docs)**: sección de la grilla en la guía con la receta (por qué remoto, el escapado, el `last_row`
  después del filtro) y la aclaración de que este contrato es del demo, no de `ListQuery`.

## Tasks

- **T1** — Grilla (S1-S5): JS con los filtros y los params fijados, controller con el parseo whitelisteado
  y el filtrado antes de paginar, tres claves por idioma, y test HTTP del JSON (filtra, `last_row`
  correcto, campo fuera de whitelist ignorado, `%` literal). Ruta: worker. Commit: `dd19e50`
- **T2** — Docs y verificación (S6): sección en la guía, referencia del test de catálogos, `composer verify`,
  round-trip de `contacts`, y una comprobación con la tabla corriendo para confirmar la forma del request.
  Ruta: worker + padre. Commit: `3149f71`
  Estado: cerrado salvo el tramo de navegador. `docs:check` verde, `lint` y `phpstan` verdes, round-trip de
  `contacts` (install → 40 tests / 2547 assertions, SRI incluido → remove). **Pendiente**: abrir la tabla en
  un navegador real y tipear en un filtro para confirmar el request en vivo; el entorno del worker no tiene
  navegador, así que la forma se confirmó leyendo el bundle 6.7.1 y con el test HTTP.

## Log

- **L1**: «en el caso de contacts, tenemos la posibilidad de agregar esos filtros a la tabla de tabulator? que
  si quiero buscar registros segun los valores del campo?» y luego «quiero implementar esos filtros, pero no
  con soluciones hechas en casa, quiero usar las herramientas que la libreria tabulator ofrece, siguiendo sus
  buenas practicas, deberias investigar en internet, consultar en context7 y ahi proponer una solucion
  factible».
- **L2**: investigación. Context7 no respondió (`fetch failed`) y web_search quedó bloqueado (403 del
  proveedor); se usó la documentación oficial de Tabulator (fetch directo) y el bundle 6.7.1 vendorizado.
  Datos: `filterMode: "remote"` envía los filtros en el parámetro configurable por `dataSendParams` con
  forma `{field, type, value}`; `headerFilter`/`headerFilterFunc`/`headerFilterPlaceholder`; 
  `headerFilterLiveFilterDelay` (300 ms por defecto); respuesta `{last_page, data}` + `last_row` para el
  contador; `paginationOutOfRange` para el borde; cambiar filtro u orden vuelve a la página 1 solo.
- **L3**: decisión del usuario: filtro en vivo, 400 ms. La doc se contradice en el nombre del parámetro
  (`filter` vs `filters`), así que se fija `dataSendParams` en vez de confiar en el default.
- **L4**: bloqueo real al implementar: cambiar el JS invalida su SRI, y el `<script integrity>` vive en la
  vista del scaffold, fuera de las superficies del worker. Se autorizó esa vista para un cambio de un
  atributo; el hash nuevo es
  `sha384-dkvuE7TAc4JTtgjqkp4bbne5xysT+YAjyCsV1G2uBkMpQ3x3+FpeJ2qwd0pHM1wR` y el test del SRI pasa.
- **L5**: para decisiones de librería, el bundle instalado es la fuente más confiable: los defaults de
  `dataSendParams` no aparecen en la documentación. Context7 no respondió (`fetch failed`) y `web_search`
  quedó bloqueado por el proveedor (403); la fuente fue la doc oficial (fetch directo) más el bundle.
- **L6**: límites conocidos. Los filtros de cabecera viven en la celda del encabezado, así que el colapso
  responsive los oculta (filtrado de escritorio en esta iteración); `LIKE` de SQLite es insensible a
  mayúsculas solo en ASCII; un filtro con valor vacío se ignora. Y con `tasks`+`notes` instalados, la suite
  base tiene un fallo preexistente de `NoteTest` que espera un conjunto concreto de demos: no es regresión
  de esta feature.
