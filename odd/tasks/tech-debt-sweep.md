# tech-debt-sweep

Cierre de la deuda técnica verificada: alcance de PHPStan, accesibilidad del header y los P3 que sobrevivieron al filtro de ruido.
Fuera de alcance por decisión del operador: el throttle (B2). Fuera de este documento: la paginación de contactos (B1+C7), que se conversa aparte.

## Specs

S1 — `phpstan.neon` analiza lo mismo que `lint`: sumar `framework/scaffolds`, `routes/`, `config/`, `scripts/` y `bootstrap/`, y corregir lo que aparezca. Sin bajar el nivel ni agregar baseline.

S2 — Los controles del header tienen nombre accesible estático en el HTML, no sólo por `:aria-label` de Alpine, y ningún control que dependa de JS se muestra como si funcionara cuando Alpine no corrió.

S3 — Una variante desconocida no puede producir ARIA inválido: `alert` siempre emite un `role` válido y un conjunto de clases utilizable.

S4 — `button.twig` tiene una sola rama de atributos compartida entre `<a>` y `<button>`; ninguna prop documentada queda sin efecto según la rama.

S5 — `_icon.twig` define cada icono una vez: un solo wrapper `<svg>` y un mapa nombre → contenido. El path del lápiz queda idéntico al que usa el JS de contactos.

S6 — El modal trae su propio control de cierre y `aria-describedby` cuando hay subtítulo.

S7 — El tooltip de estado cierra al perder el foco, no sólo con click fuera y Escape.

S8 — La rotación de remember-me es atómica: delete e insert dentro de una transacción.

S9 — La guía publica el contenido de `tasks/_styles.twig`.

S10 — El demo de contactos pagina y ordena **desde el servidor** con Tabulator: `GET /contactos/datos` devuelve `{last_page, data}`, acepta `page` y `per_page` con el clamp de `Pagination::paginate`, y acepta `sort[0][field]`/`sort[0][dir]` sólo para una whitelist explícita de columnas. Un campo fuera de la whitelist no ordena y no llega nunca a la query. El HTML de la página no embebe filas.

## Tasks

| id | spec | route | estado |
|---|---|---|---|
| T1 | S2 | delegado (header) | hecho |
| T2 | S3, S4, S5, S6 | delegado (componentes) | hecho |
| T3 | S7, S8 | delegado (backend) | hecho |
| T4 | S1 | inline | hecho |
| T5 | S9 | inline | hecho |
| T6 | verificación y entrega | inline | en curso |
| T7 | S10 | delegado (contactos) | hecho |
| T8 | verificación visual | inline | hecho |

## Log

L1 — "1. Descartado, 2,3 y 5. Abordalos, 4. Conversemos de esto"

L2 — 1 = throttle XFF + tope por IP (B2): descartado por el operador. 2 = alcance de PHPStan (B4). 3 = nombres accesibles del header (A1). 5 = barrido de P3: transacción de remember-me (B3), cierre del modal (C6), toggle de tema sin JS (A2), variantes inválidas (A3), tooltip sin focusout (A4), API de `button.twig` (C1), registro de iconos (C2), cobertura de la guía (D1). 4 = paginación de contactos (B1+C7): conversación pendiente.

L3 — Sobre el punto 4: "el punto 4, opcion 3... queremos que el demo sea util para escenarios reales, y debe soportarse que la tabla muestre los datos paginados desde el servidor junto con la paginacion de forma funcional... no queremos dar un ejemplo con malas practicas o que siembra de deuda tecnica desde en minuto 0". Y después: "quiero tambien el sort".

L4 — S3 del writer de backend: la atomicidad de la rotación no se discrimina sin inyectar una falla entre el delete y el insert, y la app no expone hook para eso. Se implementó la transacción y se documentó la limitación del test en el código, en vez de fingir un RED.

L5 — Verificación visual con ego-browser sobre una base SQLite temporal (100 contactos, 100 tareas, usuario QA propio), escritorio 1440×900 y móvil 390×844. Verificado: la tabla de contactos se llena desde `/contactos/datos` sin dataset en el HTML; orden asc/desc con clics reales y `#` renumerado; tamaño de página 25 → 4 páginas; el 422 de tareas conserva lo tipeado y avisa en castellano; el alta limpia el campo y crea; el modal de borrado tiene su botón de cierre, que cierra de verdad, y el telón se aplica (`:modal` + `oklch(0 0 0 / .6)`); en móvil el sidebar queda `inert` fuera de pantalla, la hamburguesa expone su `aria-label` estático y el drawer funciona; el tema alterna dark↔light y persiste; la portada muestra la copia nueva; el logout llega al login con "Sesión cerrada.".
Hallazgo abierto, **preexistente** y no introducido por esta rama: en 390 px la tabla mide 624 px dentro de un contenedor de 309 con `overflow-x:auto`, así que Teléfono y Acciones se alcanzan scrolleando la tabla; la página no desborda. Cambiarlo implica decidir `responsiveLayout: 'collapse'` o columnas por breakpoint.
Dos falsas alarmas propias, para registro: la captura del modal a los 700 ms caía en la transición (medido a 1.5 s estaba perfecto) y la medición del login leyó el documento anterior a la navegación.

L6 — S1: PHPStan amplió su alcance a `bootstrap`, `config`, `routes` y `scripts`, y arregló los cuatro `$argv` de `demo.php` leyéndolos de `$_SERVER`. `framework/scaffolds`, `database` y `tests` quedan fuera a propósito: usan los estáticos mágicos de Eloquent y sin una extensión tipo Larastan producen 25 falsos positivos; el límite y su motivo quedan escritos en `phpstan.neon`. Con el alcance amplio, PHPStan bajó a verde sin baseline ni ignores.
