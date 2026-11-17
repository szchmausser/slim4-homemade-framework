# Hallazgos del recorrido del tutorial

Registro de lo observado al seguir `tutorial-crud.md` literalmente en
`C:/Desarrollo/slim4-homemade-framework-walkthrough` (worktree limpio, servidor en `127.0.0.1:8090`),
capítulo por capítulo, con la pantalla a la vista en ego-browser.

Cada hallazgo indica capítulo, síntoma observado, causa, corrección y estado. Todos los marcados
«corregido» se aplicaron a `tutorial-crud.md` en el mismo commit que este documento.

## F1 — Capítulo 1: el servidor de desarrollo no debe escuchar en `0.0.0.0` (corregido)

- **Síntoma**: `php -S 0.0.0.0:8080 -t public`.
- **Causa**: contradice `AGENTS.md` («servidor de desarrollo (no usar `0.0.0.0` en LAN)») y `README.md`
  (`127.0.0.1`). `0.0.0.0` publica la app en la red local, donde el admin sembrado tiene acceso total.
- **Corrección**: `127.0.0.1` y una nota de por qué, con la salida por túnel/TLS.

## F2 — Capítulo 2: el número de migración de ejemplo está desactualizado (corregido)

- **Síntoma**: «Si el último es `006_...`, tu archivo es `007_create_notas_table.php`» y, para el
  generador de Phinx, «renombralo a `007_create_notas_table.php`».
- **Causa**: la última migración del clon es `010_add_rotated_at_to_remember_tokens.php`; el siguiente
  libre es `011`. Y `007`/`008` no son libres: los reservan las migraciones de los demos de notas y
  contactos, que viven en `framework/scaffolds/*/database/migrations/<demo>/`. `scripts/demo.php`
  aborta con «Colisión de migraciones» si el lector elige uno de esos números.
- **Corrección**: el ejemplo pasa a `011`, con una nota sobre las versiones reservadas por demos.

## F3 — Capítulos 4.2 y 6.6: nadie define `active`, el menú nunca marca la página (corregido)

- **Síntoma**: 4.2 explica que `key` «es lo que el sidebar compara con el `active` de la página para
  marcarla», pero `index()` no pasaba `active`. En `/notas` no se resaltaba nada, sin
  `aria-current="page"`. El recorrido recién lo agregaba en 13.1: seis capítulos tarde.
- **Causa**: omisión. El render de error 422 sí lo pasaba, así que la misma página se veía distinta
  según hubiera habido rechazo.
- **Corrección**: `'active' => 'notas'` desde 6.6, con la explicación de para qué sirve.

## F4 — Capítulo 7.3: `only` vacía el token CSRF y el alta falla en silencio (corregido)

- **Síntoma**: con el parcial tal como lo escribe 7.3, el `<form>` sale con dos `input` ocultos de
  `name=""` y `value=""`. Al enviar, el servidor responde **303 a `/login`**, no se crea la nota y no
  aparece ningún mensaje.
- **Causa**: `{% include 'notas/_form.twig' … only %}` corta el contexto de `index.twig`, y
  `_form.twig` incluye `partials/_csrf.twig`, que necesita `csrf_name`, `csrf_value`, `csrf_name_key`
  y `csrf_value_key`. `Controller::render()` los inyecta como variables de contexto, no como globales
  de Twig. El propio 7.2 advierte que ese rechazo «no explica nada»: el recorrido se tropieza con la
  trampa que acaba de describir.
- **Evidencia**: el demo incluye `_form.twig` **sin** `only`; el capítulo 9 usa la misma forma. La
  inconsistencia estaba sólo en 7.3 y 7.4.
- **Corrección**: `only` fuera de los dos `include`, con la explicación del porqué y una fila nueva en
  «Problemas frecuentes».

## F5 — Capítulos 7.5 y 9.2: el 422 no se puede reproducir desde la interfaz (corregido)

- **Síntoma**: el recorrido pide «envía el alta con el título vacío». Al hacerlo, el navegador **no
  envía nada**: el campo lleva `required`, la validación nativa bloquea el envío y no hay POST en el
  log del servidor.
- **Causa**: `components/form/field.twig` emite `required` y `maxlength` cuando se los pide; no queda
  ninguna entrada por la que el formulario llegue inválido al servidor. La validación del servidor
  funciona —comprobada con POST directo: 422, modal abierto, «Este campo es obligatorio.»,
  `aria-invalid="true"`, cuerpo conservado—, pero es inalcanzable desde la pantalla.
- **Corrección**: receta `curl` con el par CSRF y la cookie de sesión, esperando 422 y tres `grep -c` en
  `1`; y se explica por qué `required` se queda (defensa en profundidad).

## F6 — Restos de léxico rioplatense (corregido)

- **Síntoma**: el tutorial conservaba formas que el resto de la documentación ya había barrido.
- **Casos**: «Acá va a vivir el listado» (3×), «pasale la colección», «Si insertás una nota», «revisá
  el `nota: null`», «Hasta acá», «acá el bloque», «se lo pasás vos».
- **Corrección**: `Aquí`, `pásale`, `insertas`, `revisa`, `Hasta aquí`, `aquí`, `pasas`.

## F7 — `DESIGN.md` describía props que `button.twig` no acepta (corregido)

- **Síntoma**: documentaba `onclick` entre las props del botón. El componente no lo emite y el propio
  tutorial lo afirma («El componente no acepta `onclick`»). Faltaban además `label`, `title`, `name`,
  `value`, `disabled`, `target`, `data_modal_open` y `data_modal_close`.
- **Corrección**: la línea de `DESIGN.md` se alinea con el encabezado del componente, incluida la
  aclaración de que abrir/cerrar un diálogo se pide por atributo, no por `onclick`.

## F8 — El badge dice «1 notas» (documentado, sin corregir)

- **Síntoma**: con una sola nota, «1 notas».
- **Causa**: `{{ total }} notas` fijo, igual que `{{ contacts_count }} contactos` en contactos. El
  tutorial es fiel al demo, así que no se corrige ahí: si se arregla, hay que arreglar el demo y las
  dos vistas a la vez.

## F9 — Capítulo 12 (entonces 12.3): la comprobación menciona paginación antes de que exista (corregido)

- **Síntoma**: «el listado sigue paginando y refrescándose sin duplicar el shell», en el capítulo 12.
  La paginación llega en el 13.
- **Corrección**: la comprobación se limita a lo construido; se aclara que la paginación llega en 13.

## F10 — Capítulo 14: el demo no puede convivir con la entidad escrita a mano (corregido)

- **Síntoma**: `composer demo:notas:install` aborta si ya hay archivos instalados, y con `--force`
  copia los archivos y después revierte todo porque la tabla existe. Además, `composer test` queda en
  rojo: `DemoInstallerTest::test_install_revierte_los_archivos_si_la_db_falla` esperaba el mensaje
  `table \`notas\` already exists` y recibía
  `Migration "007_create_notas_table.php" has the same name as "011_create_notas_table.php"`, porque la
  migración manuscrita define la misma clase `CreateNotasTable` que la del demo.
- **Causa**: el demo y el recorrido son la misma entidad —misma tabla, misma clase de migración—, así
  que no pueden estar instalados a la vez. El test fijaba, de más, el texto de Phinx.
- **Corrección**: (a) el tutorial avisa, en la introducción y en el capítulo 14, que el demo se mira en
  un clon limpio o antes de empezar; (b) el test afirma el contrato —la DB falló y los archivos se
  revirtieron— sin fijar el mensaje del motor.

## F11 — Capítulo 12.2: la fila final no mostraba el `cuerpo` (corregido)

- **Síntoma**: el `cuerpo` se guardaba desde el capítulo 7 y no se veía en ninguna parte; el demo lo
  muestra bajo el título y hace del título un enlace a editar. El recorrido terminaba con
  `<span>{{ item.titulo }}</span>`.
- **Causa**: omisión. Una nota con cuerpo y otra sin él se veían igual, y el HTML final no coincidía
  con el demo que el propio capítulo 14 propone como referencia.
- **Corrección**: la fila de 12.2 pasa a la del demo —título enlazado y `{% if item.cuerpo %}`, con el
  `min-w-0` que evita que un texto largo empuje los botones— y se explica por qué aparece recién ahí.

## F12 — Capítulo 11: el helper `crear()` descartaba el título de cada test (corregido)

- **Síntoma**: `Nota::create(['titulo' => 'De prueba'] + $datos)`. En PHP, `+` conserva el valor de la
  izquierda, así que `$this->crear(['titulo' => 'Antes'])` creaba «De prueba». El test pasaba igual
  porque sólo comprobaba el estado final; el fixture mentía en silencio.
- **Evidencia**: el demo lo tiene al revés y lo comenta: `Nota::create($datos + ['titulo' => 'De
  prueba'])`.
- **Corrección**: el orden correcto, con el comentario que explica el `+`.

## F13 — Capítulo 14: la lista de archivos omitía el middleware de sesión (corregido)

- **Síntoma**: la lista de seis pasos para repetir el patrón no mencionaba
  `->add(RequireAuthMiddleware::class)`, que sólo apareció en 4.1.
- **Causa**: omisión con consecuencia de seguridad: un CRUD nuevo quedaría público. Los tests no lo
  detectan porque `setUp()` hace `loginAs()`.
- **Corrección**: el paso 3 de la lista lo nombra y explica qué pasa si falta.

## F14 — Capítulo 14: cambiar el icono no pinta nada si el nombre no existe (corregido)

- **Síntoma**: «El icono y el subtítulo del encabezado» figuran como lo que cambia por entidad, sin
  decir de dónde salen los nombres.
- **Causa**: `partials/_icon.twig` no renderiza nada ante un nombre desconocido —y es deliberado—, así
  que un lector que inventa `libro` obtiene un `<h1>` sin icono y una entrada de menú sin icono, sin
  ningún error.
- **Corrección**: usar un nombre del registro (`list`, `contacts`, `pencil`, `trash`…) o agregar el
  glifo primero.

## F15 — Capítulo 14: los conteos de tests no coinciden (corregido)

- **Síntoma**: «ocho tests» para el demo y «cinco tests en verde» para el cierre.
- **Causa**: el demo tiene once; el recorrido termina con seis (cinco de 11 más el de 13.3).
- **Corrección**: once para el demo, seis para el cierre, y el capítulo 11 avisa que 13.3 suma el
  sexto.

## F16 — Capítulo 13.2: la comprobación nombra un `<aside>` que no existe (corregido)

- **Síntoma**: «dos `<aside>` (el sidebar y el de avisos)».
- **Causa**: los `<aside>` son el sidebar y la nota del pie; los avisos son un `<div class="toast-host">`.
  El número 2 coincide por casualidad, así que la comprobación «pasaba» mirando el elemento equivocado.
- **Corrección**: se nombran los dos `<aside>` reales y se aclara que los avisos no son uno.

## F17 — Capítulo 10.2: la instrucción dejaba la fila sin papelera (corregido)

- **Síntoma**: «saca el `onsubmit` y el formulario de la fila, y pon el diálogo justo después del
  `</li>`». El fragmento sólo muestra el diálogo; la frase siguiente afirma que «la papelera del paso 8
  ya apunta a ese diálogo», pero en 10.1 la papelera era el `submit` de ese formulario. Siguiendo el
  texto al pie de la letra, la fila queda sin botón y ningún diálogo se puede abrir.
- **Corrección**: «desenvolvé la papelera»: el botón sale del `<form>` y recupera `data_modal_open`, con
  el fragmento de la fila completo.

## Comprobado sin hallazgos

- **Capítulo 9** (edición): `edit()`/`update()`, el guard `{% if nota is defined %}`, el `action` con
  `nota.id`, el 404 por `HttpNotFoundException` y el 422 con el modal de edición abierto.
- **Capítulo 12.1**: el contrato de `components/list/simple.twig` (`items`, `empty`, `list_class`,
  `empty_class`, bloque `row` dentro del `for`) y el de `components/ui/page-header.twig`
  (`icon`, `title`, `subtitle`, bloque `actions`) coinciden con los archivos.
- **Capítulo 11**: `HttpTestCase` provee `app()`, `request()`, `token()` y `loginAs()`; los cinco tests
  pasan tal como están escritos (24 aserciones).
- **El límite de 4096 bytes del 404** (`assertLessThan`) se cumple: la prueba pasó.

## Método

Recorrido ejecutado en el worktree `slim4-homemade-framework-walkthrough`, con PHP 8.5.5, SQLite y el
servidor en `127.0.0.1:8090` (el 8080 estaba ocupado por otro proceso). Cada capítulo se aplicó tal
como está escrito, se comprobó en pantalla con ego-browser y se contrastó contra
`framework/scaffolds/notas/`. Al terminar, el diff contra el demo quedó reducido a los nombres de
namespace, el orden de los métodos y los comentarios.

## Segunda pasada: clon limpio con la guía corregida

La sección 12.3 se eliminó después: el encabezado se escribe a mano en 6.6 y se pasa al componente en 6.7, dentro del capítulo 6.

Recorrido completo desde `4a6281e` en un worktree sin `vendor/`, sin base y sin la entidad. Capítulos
1 a 13 ejecutados literalmente; 14 no es ejecutable literalmente porque es un ejercicio sin código.

### Resultado

Cada comprobación de los capítulos 1 a 13 se reprodujo y dio lo que la guía anticipa: instalación,
migración, modelo (79 a 80 archivos de lint), rutas (303 sin sesión, 200 con sesión), JSON exacto,
HTML crudo sin shell, Twig idéntico, escapado, shell, parcial, encabezado con `active`, alta con
toast, campos del componente, parcial del formulario, modal, 422 con modal abierto, botones con
nombre accesible, edición en modal, 404, borrado con `confirm()`, diálogo por fila, los cinco tests,
la extracción a `simple.twig` y a `page-header`, paginación del servidor (`10/25/6` filas según el
caso, `?page=99` recortado), `HX-Request` con 0 headers y 0 asides en la respuesta, y el 422 sin
`HX-Push-Url`. Suite completa al cierre: **160 tests, 1328 aserciones**.

### F18 — 7.5: la receta `curl` no iniciaba sesión (corregido)

- **Síntoma**: ejecutada tal cual, el `GET /notas` devolvía 0 bytes (303 a `/login`) y el POST daba 303
  a `/login` con los tres `grep -c` en `0`. El texto decía «con sesión iniciada», pero el bloque nunca
  iniciaba sesión.
- **Causa**: error introducido en la primera pasada. La comprobación se había hecho con un `fetch`
  desde la página, que ya llevaba la cookie; el bloque de shell, con un jar vacío, no.
- **Corrección**: el bloque arranca con el login por `curl` (par CSRF del formulario de acceso, `-c`
  y `-b` sobre el mismo jar) y sigue con el alta. Reejecutado desde el propio archivo: `login=303`,
  `post=422`, greps `1/1/1`.

### F19 — Capítulos 3, 6.3, 12.1, 12.2 y 13.2: redacción ambigua (corregido)

- **3**: la comprobación exigía «un archivo más que en la corrida anterior» sin decir cuándo correr el
  lint. Ahora indica correrlo antes y da el total de un clon limpio (79, luego 80).
- **6.3**: mandaba a `phinx seed:run` «cuando exista el seed», y no existe seed de `notas` en ese
  punto. Ahora propone un `INSERT` puntual o el modelo, que sí existe.
- **12.1**: presentaba `components/list/simple.twig` bajo el mismo encabezado **Archivo** que se usa
  para los archivos a editar. Pasa a **Componente**, con «léelo, no lo edites».
- **12.2**: decía que la lista se ve «idéntica» a la del paso 10, y el mismo apartado introduce dos
  cambios en la fila. Ahora nombra los dos cambios.
- **13.2**: «dos `<aside>`» confunde si se cuenta texto, porque hay un `<aside>` dentro de un comentario
  de JS. Ahora aclara que se cuentan elementos.

### Observado y no corregido

- **Capítulo 14**: es un ejercicio del lector, no un paso del libro, así que no se ejecuta de nuestro
  lado. Sus instrucciones se corrigieron en la primera pasada y se contrastaron contra el demo.

Todo lo demás se corrigió: el `<dialog>` salió de la `<ul>` con el bloque `after` de
`components/list/simple.twig` (dos tests de contrato), y los badges de notas y contactos distinguen
singular de plural.
