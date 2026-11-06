# DESIGN.md

Sistema visual vigente. Describe lo que el código hace hoy; si hay divergencia, el código manda y este documento se corrige.

## Modo

**Operate** en las cuatro superficies. La persona completa una tarea; el objetivo es escaneabilidad y consistencia, no expresión. La marca vive en detalles precisos, no en decoración.

## Layouts

| archivo | para qué | qué incluye |
|---|---|---|
| `layouts/app.twig` | shell de la app autenticada | sidebar plegable, header con breadcrumb y toggle de tema, host de avisos, `main` centrado, footer |
| `layouts/auth.twig` | acceso | pre-pintado de tema, hoja + build de Tailwind, marca que vuelve al inicio, `main` centrado. **Sin htmx ni Alpine** |
| `errors/error.twig` | página de error | autónoma, sin `extends`, sólo el pre-pintado de tema, < 4 KB, mensaje mapeado por código |

Un layout nuevo sólo se justifica si no puede ser una rama de estos tres. Hay dos layouts con el `<head>` repetido: si aparece un tercero, extraer un partial. La página de error no cuenta como tal: repite sólo el favicon y el pre-pintado, y su `<head>` es mínimo a propósito (sin build de Tailwind ni htmx/Alpine).

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

Clases de proyecto (definidas en el `<style>` de `app.twig`): `toast-host`, `toast-stack`, `toast-card`, `toast-icon`, `toast-progress`, `sidebar-head`, `sidebar-label`, `icon-success`, `icon-error`, `icon-pending`. En `auth.twig`: `login-field`, `login-field-icon`, `login-input`, `login-remember` e `icon-info`.

## Interacción

- **Tema**: toggle `swap` en el header; el estado vive en `localStorage` y se refleja en `data-theme`. El sidebar plegado también se pre-pinta.
- **Sidebar**: overlay en móvil, columna plegable en desktop. Fuera de pantalla lleva `inert`, aplicado también en un script de parseo para cubrir la ventana previa a Alpine.
- **Avisos**: uno solo, abajo a la derecha, pila `column-reverse` (el nuevo arriba, crece hacia arriba), tope 3, vida gobernada por `animationend`, pausa al hover, `prefers-reduced-motion` conserva el fade que retira el nodo.
- **URL**: el servidor emite `HX-Push-Url` con la página que efectivamente renderizó (no se pone `hx-push-url` en las plantillas, porque `Pagination` recorta la página y el atributo mentiría).
- **En vuelo**: `hx-disabled-elt` en el formulario de alta y en el panel, para que un doble clic no duplique la mutación.
- **Foco**: los controles llevan `data-focus-key`; un listener lo guarda en `htmx:beforeRequest` y lo restaura en `htmx:afterSettle`. Si la fila desapareció, el foco cae en `#tareas-panel` (`tabindex="-1"`).
- **CSRF**: dos transportes, según el disparador. Dentro de un formulario, los campos ocultos de `partials/_csrf.twig`; un botón suelto que emite una mutación, `hx-headers` con el par serializado. Sin htmx el fallo deja flash de error y 303 al login; con htmx, 400 + `HX-Trigger`.

## Nombres

En los bloques `<script>` y en las expresiones de Alpine los identificadores van en inglés (`shell`, `toastStack`, `toggleMini`, `restoreFocusAfterSwap`, `pendingKey`, `open`) y los comentarios en español con acentos. Un mismo bloque no mezcla los dos idiomas.

## Accesibilidad (piso, no objetivo)

- Texto ≥ 4.5:1; objetos gráficos ≥ 3:1.
- Todo control tiene nombre accesible: iconos con `aria-label` + `title`, campos con `label` asociado o `aria-label`.
- Foco visible siempre; nunca `outline: none` en un control interactivo.
- La paginación es un enlace real (`href`) además de `hx-get`: sin `href` no hay rol ni foco.
- Skip link presente: `.skip-link` es el primer elemento tabulable y apunta a `#contenido`. Va fuera de pantalla por posicion, no por `display`, para que siga recibiendo el primer Tab.

## Despliegue con prefijo de ruta

`APP_BASE_PATH` (vacío por defecto) permite servir la app desde una carpeta en vez de la raíz del dominio. Con `/app`, todas las URLs de las vistas, las acciones htmx, los assets, los redirects y el `HX-Push-Url` salen prefijadas; vacío, todo queda idéntico al caso raíz.

- En plantillas: `{{ url('/tareas') }}`. Nunca un literal `href="/tareas"`.
- En PHP: `Framework\Http\Url::path('/tareas')`.
- El servidor tiene que reenviar el prefijo a `public/index.php` (nginx: `handle_path /app/*`). **`php -S -t public` no puede servir una ruta prefijada**: sirve solo el caso raíz, así que un despliegue con prefijo exige proxy o regla de reescritura.
- Un literal raíz-absoluto nuevo deja esa URL fuera del prefijo: es la única regla que hay que recordar al agregar vistas.

## Assets

Vendorizados con SRI, servidos desde `public/assets/`. `php -S` no comprime; en despliegue, la compresión HTTP (nginx/Caddy) lleva los ~1.47 MB a ~150 KB sobre el cable. Eso es configuración del servidor, no del proyecto.

## Prohibiciones

- Paso de build, bundler o `@theme`.
- Utilidades `dark:`.
- Colores fuera de los tokens.
- Clases de daisyUI que no existan en el bundle vendorizado.
- JS nuevo fuera de los bloques inline existentes: los **tres** de `layouts/app.twig` (los dos de pre-pintado del `<head>` y el del final del `<body>`), el pre-pintado de `layouts/auth.twig` y `errors/error.twig`, y el `x-data` de Alpine.
