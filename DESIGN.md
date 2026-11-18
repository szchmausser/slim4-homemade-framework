# DESIGN.md

Sistema visual vigente. Describe lo que el código hace hoy; si hay divergencia, el código manda y este documento se corrige.

## Modo

**Operate** en las cuatro superficies. La persona completa una tarea; el objetivo es escaneabilidad y consistencia, no expresión. La marca vive en detalles precisos, no en decoración.

## Layouts

| archivo | para qué | qué incluye |
|---|---|---|
| `layouts/app.twig` | composición del shell autenticado | sidebar, header, toasts, `main` y footer desde partials; bloques `head`, `styles`, `header`, `sidebar`, `toasts`, `footer` y `scripts` |
| `layouts/auth.twig` | acceso | `layouts/parts/head.twig`, marca que vuelve al inicio, `main` centrado. **Sin htmx ni Alpine** |
| `pages/errors/error.twig` | página de error | autónoma, sin `extends`, sólo el pre-pintado de tema, < 4 KB, mensaje mapeado por código |

### Componentes del shell

| partial | responsabilidad |
|---|---|
| `layouts/parts/head.twig` | favicon, pre-pintado de tema y assets; flags `with_sidebar`, `with_htmx`, `with_alpine` |
| `layouts/parts/sidebar.twig` | `<aside>` con navegación data-driven, usuario y logout; recibe `active` |
| `layouts/parts/topbar.twig` | controles del sidebar, breadcrumb data-driven y toggle de tema; recibe `breadcrumbs` |
| `layouts/parts/toasts.twig` | host y markup de avisos flotantes |
| `layouts/parts/footer.twig` | datos de ejecución del shell |

Los dos layouts comparten `layouts/parts/head.twig` para favicon, tema y assets; sólo el `<title>`/metadatos y el `<style>` propio quedan en cada layout. La página de error sigue siendo una excepción deliberada: su `<head>` mínimo no carga el shell.

El `<style>` del layout no contiene reglas de ningún demo. Un módulo opcional inyecta las suyas por el bloque `styles`, que sólo se renderiza en sus páginas y viaja con el scaffold: al correr `demo:tasks:remove` se borran el archivo y sus reglas, sin dejar CSS huérfano en el shell. `tasks/_styles.twig` es el caso vivo.

## Color

- daisyUI 5 aporta los tokens: `--color-base-100/200/300/content`, `--color-primary/secondary/accent/neutral`, `--color-info/success/warning/error` y sus `*-content`.
- Tema por `data-theme` en `<html>` (`light` / `dark`), aplicado **antes del primer paint** por un script inline en el `<head>` de **los tres** layouts. La página de error era la excepción —no tenía el script, así que salía siempre en claro— y ya no lo es. **Prohibido `dark:`**: si aparece una utilidad `dark:` es un error de sistema, no una variante.
- Los semánticos de daisyUI son **fondos**, no tintas. Para iconos existen cuatro tokens propios en el `<style>` de `layouts/app.twig`: `--ink-success` (4.77:1), `--ink-error` (5.37:1), `--ink-pending` (6.55:1) y `--ink-info` (5.31:1) en claro, con reuso de los semánticos en oscuro. Se aplican con `.icon-success`, `.icon-error` y `.icon-pending`; `layouts/auth.twig` replica success/error/info para sus avisos y define `.icon-info`.
- Sin hex ni `oklch()` sueltos en las plantillas. Si hace falta un valor nuevo, va como custom property en el `<style>`.
- El placeholder de los campos se pinta al 70 % de `base-content`, no al 50 % de daisyUI (3.38:1 sobre base-100, bajo el piso): una regla sobre `input`/`textarea` en el `<style>` de `layouts/app.twig` y `layouts/auth.twig`, no una clase por campo.
- En auth los campos usan `.login-input` sobre `base-200` y el autofill de Chrome se cubre con un `box-shadow` interno; el foco es `outline` primary de 2 px. Los iconos de campo (`.login-field-icon`) comparten trazo con el resto del sistema y pasan a primary con `:focus-within`.

## Tipografía

Sin tipografía propia: la del sistema, la de daisyUI. Jerarquía por tamaño y peso, no por color.

| uso | clases |
|---|---|
| título de página | `text-2xl font-bold` |
| título de portada | `text-3xl font-bold` |
| sección | `text-lg font-semibold` |
| cuerpo | por defecto |
| secundario | `text-xs` + atenuado |

Atenuar se hace con `opacity-*` (medido: 60 % = 4.64:1, 70 % = 6.54:1, 80 % = 9.39:1 en claro, todos AA). No se inventa un token de "muted" para eso.

## Estructura

- Una sola columna, ancho completo del área de contenido, `p-4 lg:p-6`; sin `max-width` adicional para que la separación lateral coincida con la superior.
- Portada: fila superior de identidad a ancho completo; debajo, stack a la izquierda y demo a la derecha desde `lg`, apilados en móvil. El stack va agrupado por capa y subcategoría, con una sinopsis por tecnología.
- Tarjetas de fila: `rounded-box border border-base-300 bg-base-100 p-3`.
- Cromo de altura fija: header `h-16`, footer `h-12`. Las líneas del footer y del sidebar son continuas.
- Separadores con `border-base-300`; nunca sombras para delimitar estructura (las sombras son para capas flotantes: modal y avisos). En oscuro el modal de borrado suma un borde de tinta y un telón más denso (`--modal-scrim`), porque ahí la sombra no se ve.

## Componentes

daisyUI, sin capa propia de componentes: `btn`, `input`, `select`, `join`, `modal`, `alert`, `badge`, `card`, `hero`, `menu`, `swap`, `breadcrumbs`, `checkbox`, `fieldset`/`fieldset-legend`.

**Regla dura:** toda clase de daisyUI usada tiene que existir en `public/assets/daisyui-5.7.46.css`. Lo verifica `tests/Support/ViewClassIntegrityTest.php`, que además exige que cada clase de proyecto usada esté definida en el `<style>` del layout. Si el guard falla, se corrige la plantilla, nunca el guard.

Clases de proyecto (definidas en el `<style>` de `app.twig`): `toast-host`, `toast-stack`, `toast-card`, `toast-icon`, `toast-progress`, `sidebar-head`, `sidebar-label`, `icon-success`, `icon-error`, `icon-pending`, `icon-info`. En `auth.twig`: `login-field`, `login-field-icon`, `login-input`, `login-remember` e `icon-info`.

### Componentes reutilizables

- `components/ui/button.twig`: botón o enlace con `label`, `variant`, `size`, `icon`, `icon_class`, `href`, `type`, `name`, `value`, `title`, `aria_label`, `disabled`, `target`, `extra_class`, `icon_position` (`start` por defecto, `end` para los CTA con flecha), `data_modal_open`, `data_modal_close` y atributos HTMX/`data-focus-key` opcionales. Con `href` emite `<a>`; sin `href`, `<button>`. No emite `onclick`: abrir o cerrar un diálogo se pide con `data_modal_open`/`data_modal_close`, que resuelve el shell.
- `components/ui/modal.twig`: `<dialog>` de daisyUI con `id`, `title`, `subtitle`, `open` y bloque `body`. `open` emite el atributo nativo del `<dialog>` y sirve para devolver desde el servidor un formulario inválido con su modal abierto, sin JS; un `<dialog open>` no entra al top layer, así que Escape no lo cierra y el cierre queda en los botones.
- `components/ui/page-header-title.twig`: icono opcional, `title` y `subtitle` opcional; emite el `<h1>`
  y el párrafo sin dejar huecos. Es la mitad izquierda de `page-header` y se puede usar solo: encabezado
  de tarjeta, estado vacío, portada.
- `components/ui/page-header.twig`: encabezado de página con `icon` opcional, `title`, `subtitle` y bloque `actions` para el control de la esquina —un botón, dos o un formulario—. Compone `page-header-title.twig` a la izquierda y deja el hueco de las acciones a la derecha: no repite el markup del título. `icon` y `subtitle` son opcionales y no dejan huecos vacíos. El bloque `actions` acepta **una sola unidad**: si hay más de un control, entra `components/ui/action-bar.twig`.
- `components/ui/action-bar.twig`: fila de controles para la esquina de `page-header` o para una tarjeta; bloque `controls` y `extra_class` opcional. Agrupa uno o varios controles y fija la separación y el wrap; el orden lo decide quien la usa —secundario primero, primario al final—. Sin ella, dos controles sueltos en `actions` se reparten por el `justify-between` del encabezado y el del medio cae en el centro de la pantalla.
- `components/feedback/alert.twig`: aviso semántico con icono y `role`; variantes `error`, `success` e `info`.
- `components/form/field.twig`: `fieldset` + label + input consistente, con `error`, `error_class`, `required`, `optional`, `maxlength`, `input_class`, `field_class`, `label_class`, `aria_label` y `data_focus_key`. Con `error` el input sale con `aria-invalid="true"` y `aria-describedby` apuntando al mensaje, que se renderiza debajo con `role="alert"`: un error por campo se lee donde se cometió, no como un aviso flotante que desaparece.
- `components/list/simple.twig`: contenedor de lista server-rendered; recibe `items`, `empty`, `list_class`, `empty_class`, un bloque `row` por elemento y un bloque `after` que se emite después del `</ul>` —ahí van los diálogos por fila, porque un `<dialog>` no es hijo válido de una lista—.
- `components/table/tabulator.twig`: shell y contrato de datos (`data-tabulator-table`, `data-url`) para una tabla Tabulator en modo remoto; el initializer de columnas vive con la tabla y las filas las sirve el endpoint de `url`.
- `components/pagination/simple.twig`: paginación server-rendered + HTMX; recibe `pagination`, `target`, `swap` y `show_size`.
- `components/form/csrf.twig`, `components/ui/icon.twig`, `components/ui/logo.twig`: piezas primitivas compartidas.
- `layouts/parts/toasts.twig`: host único de avisos flotantes; acepta `toast_items` iniciales para flashes de pantallas con redirect.

## Organización de `resources/views`

Una carpeta por pregunta, y una sola respuesta:

| Pregunta | Dónde |
|---|---|
| ¿Es el esqueleto del documento? | `layouts/` y sus fragmentos en `layouts/parts/` |
| ¿Es reutilizable, con contrato y consumidores? | `components/<familia>/` |
| ¿Es una pantalla? | `pages/<feature>/` |
| ¿Es un fragmento interno de una pantalla o feature? | `_nombre.twig` en la carpeta de esa feature |

Familias de componentes: `ui` (presentación genérica: botón, icono, logo, botonera, modal, encabezado),
`form`, `feedback`, `list`, `table`, `pagination`. Una familia nueva se declara en
`tests/Support/ViewStructureTest`.

**Excepciones de composición**, con su razón y su guard (`tests/Support/ViewComponentsTest.php`):

- El chrome del shell (`layouts/parts/{topbar,sidebar,toasts}.twig`) usa botones con directivas de
  Alpine (`x-bind`, `@click`, `swap`). `button.twig` no las acepta a propósito: aceptarlas lo convertiría
  en contenedor de comportamiento. Sus `<svg>` con `x-show` y el par sol/luna (`swap`, estilo `fill`)
  tampoco salen del registro.
- `components/ui/modal.twig` dibuja su propio botón de cierre: está dentro de la capa de componentes.
- Un checkbox o radio inline se permite mientras no exista su componente; `field.twig` cubre inputs de
  texto.
- Todo icono funcional sale de `components/ui/icon.twig`; el único svg propio es la marca
  (`components/ui/logo.twig`).

Los componentes no llevan guion bajo y no incluyen desde `pages/` ni desde `layouts/`: el guion bajo es
para fragmentos internos (`pages/notas/_panel.twig`, `pages/errors/_htmx.twig`). `partials/` ya no
existe; si una pieza es reutilizable va en `components/`, y si es del shell va en `layouts/parts/`.

**Capas:** `ui/`, `form/` y `feedback/` son base independiente; `list/`, `table/` y `pagination/` son compuestos opcionales. Las filas de tareas y las columnas/acciones de contactos son código de dominio del demo, no componentes universales. Un usuario puede usar sólo los botones y toasts, o sumar una lista simple, sin adoptar Tabulator ni la paginación server-side.

**Composición:** una vista no arma la estructura de una lista, una tabla o una paginación a mano. Incluye el componente y aporta sólo el contrato de datos y el contenido de la fila. La lista de tareas usa `components/list/simple.twig`; la tabla de contactos usa `components/table/tabulator.twig`; la paginación de tareas usa `components/pagination/simple.twig`. Tareas también usa los componentes base para su alta, sus acciones y su modal de borrado; contactos para botones, campos, modales y toasts. Notas y contactos comparten el encabezado en `components/ui/page-header.twig`; tareas conserva el suyo porque su alta es un formulario inline, y puede adoptarlo metiendo ese formulario en `block actions`.

```twig
{% embed 'components/list/simple.twig' with { items: tasks, empty: 'No hay tareas todavía.' } %}
    {% block row %}
        <li>{{ item.title }}</li>
    {% endblock %}
{% endembed %}

{% include 'components/table/tabulator.twig' with {
    id: 'contacts-table',
    url: url('/contactos/datos'),
    label: 'Listado de contactos'
} only %}

{% include 'components/pagination/simple.twig' with {
    pagination: pagination,
    target: '#tareas-panel',
    label: 'tareas'
} %}
```

El demo de contactos es una isla de tabla avanzada con **Tabulator 6.7.1** vendorizado: `public/assets/vendor/tabulator/6.7.1/` + `contacts-tabulator.css` + `contacts-tabulator.js`, cargados solo en `/contactos` mediante los bloques `head`/`scripts`. Su CSS remapea encabezado, filas y celdas a `base-200`/`base-100`/`base-content`; sin eso, el tema oscuro conserva los colores claros del vendor. **No usa `components/pagination/simple.twig` a propósito**: esa paginación es server-rendered y emite URLs/HTMX, mientras que esta tabla activa el modo remoto de Tabulator contra `GET /contactos/datos`, que devuelve `{last_page, data}` para la `page` y el `per_page` pedidos (10 por página, selector 5/10/25/50/100, contador en español). El orden también es remoto: los encabezados mandan `sort[i][field]`/`sort[i][dir]` y el servidor los resuelve contra una whitelist explícita, así que el `#` es la posición en el orden visible. **Modo remoto no es mezclar los dos modelos de paginación**: Tabulator no pagina ni ordena una copia local, sólo pide páginas y muestra lo que llega, y el servidor es el único que cuenta, recorta y ordena sobre la tabla completa. Mezclar sería lo otro —filas locales paginadas por el cliente sobre un dataset que el servidor ya recortó— y ahí el estado de página no correspondería con los datos. Por eso el HTML de `/contactos` no embebe ninguna fila. El footer de Tabulator se mapea a los tokens del tema, incluido el estado activo y el selector de tamaño.

## Interacción

- **Tema**: toggle `swap` en el header; el estado vive en `localStorage` y se refleja en `data-theme`. El sidebar plegado también se pre-pinta.
- **Sidebar**: overlay en móvil, columna plegable en desktop. Fuera de pantalla lleva `inert`, aplicado también en un script de parseo para cubrir la ventana previa a Alpine.
- **Avisos**: un solo host, abajo a la derecha, pila `column-reverse` (el nuevo arriba, crece hacia arriba), tope 3, vida gobernada por `animationend`, pausa al hover, `prefers-reduced-motion` conserva el fade que retira el nodo. En htmx el flash viaja en `HX-Trigger-After-Swap`; las pantallas con redirect pueden optar por `usesToastFlash()` y recibir `toast_items`, que el host incorpora al inicializarse. Contactos usa esa segunda vía: su CRUD redirige a `/contactos` y muestra el mismo toast, sin banner inline.
- **URL**: el servidor emite `HX-Push-Url` con la página que efectivamente renderizó (no se pone `hx-push-url` en las plantillas, porque `Pagination` recorta la página y el atributo mentiría).
- **En vuelo**: `hx-disabled-elt` en el formulario de alta y en el panel, para que un doble clic no duplique la mutación.
- **Foco**: los controles llevan `data-focus-key`; un listener lo guarda en `htmx:beforeRequest` y lo restaura en `htmx:afterSettle`. Si la fila desapareció, el foco cae en `#tareas-panel` (`tabindex="-1"`).
- **CSRF**: dos transportes, según el disparador. Dentro de un formulario, los campos ocultos de `components/form/csrf.twig`; un botón suelto que emite una mutación, `hx-headers` con el par serializado. Sin htmx el fallo deja flash de error y 303 al login; con htmx, 400 + `HX-Trigger`.

## Nombres

En los bloques `<script>` y en las expresiones de Alpine los identificadores van en inglés (`shell`, `toastStack`, `toggleMini`, `restoreFocusAfterSwap`, `pendingKey`, `open`) y los comentarios en español con acentos. Un mismo bloque no mezcla los dos idiomas.

## Accesibilidad (piso, no objetivo)

- Texto ≥ 4.5:1; objetos gráficos ≥ 3:1.
- Todo control tiene nombre accesible: iconos con `aria-label` + `title`, campos con `label` asociado o `aria-label`.
- Foco visible siempre; nunca `outline: none` en un control interactivo.
- La paginación es un enlace real (`href`) además de `hx-get`: sin `href` no hay rol ni foco.
- El menú lateral es un `<nav>` con `aria-label`; el ítem activo lleva `aria-current="page"`.
- Skip link presente: `.skip-link` es el primer elemento tabulable y apunta a `#contenido`. Va fuera de pantalla por posicion, no por `display`, para que siga recibiendo el primer Tab.

## Despliegue con prefijo de ruta

`APP_BASE_PATH` (vacío por defecto) permite servir la app desde una carpeta en vez de la raíz del dominio. Con `/app`, todas las URLs de las vistas, las acciones htmx, los assets, los redirects y el `HX-Push-Url` salen prefijadas; vacío, todo queda idéntico al caso raíz.

- En plantillas: `{{ url('/tareas') }}`. Nunca un literal `href="/tareas"`.
- En PHP: `Framework\Http\Url::path('/tareas')`.
- El servidor tiene que reenviar el prefijo a `public/index.php` (nginx: `handle_path /app/*`). **`php -S -t public` no puede servir una ruta prefijada**: sirve solo el caso raíz, así que un despliegue con prefijo exige proxy o regla de reescritura.
- Un literal raíz-absoluto nuevo deja esa URL fuera del prefijo: es la única regla que hay que recordar al agregar vistas.

## Assets

Vendorizados con SRI, servidos desde `public/assets/`; la lógica no crítica del shell vive en `public/assets/app.js` (también con SRI). `php -S` no comprime; en despliegue, la compresión HTTP (nginx/Caddy) lleva los ~1.47 MB a ~150 KB sobre el cable. Eso es configuración del servidor, no del proyecto.

## Prohibiciones

- Paso de build, bundler o `@theme`.
- Utilidades `dark:`.
- Colores fuera de los tokens.
- Clases de daisyUI que no existan en el bundle vendorizado.
- JS nuevo fuera de: los bloques inline de pre-pintado/shell/toastStack de `layouts/app.twig`, el pre-pintado de `layouts/auth.twig` y `pages/errors/error.twig`, el `x-data` de Alpine y `public/assets/app.js` (con SRI, solo lógica no crítica del shell). **Excepción acotada:** el demo de contactos carga Tabulator vendorizado y su initializer local con SRI, solo en `/contactos`; no se usa en el shell ni en listados simples.
