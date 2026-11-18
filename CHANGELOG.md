# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Versionado semántico mientras el proyecto esté en `0.x`.

## [Unreleased]

### Incluido

- `components/ui/button.twig` acepta `icon_position: 'end'`; `components/form/field.twig` acepta
  `icon`, `icon_class`, `wrap_class` y `autofocus`. Los cinco CTA de página y los dos campos del login
  usan los componentes en vez de reescribir su markup.
- `components/ui/icon.twig` suma `menu`, `chevrons-left`, `power` y `clock`, y su wrapper declara
  `aria-hidden`: el icono es decorativo y el nombre lo lleva el control.
- `tests/Support/ViewComponentsTest.php`: una vista no escribe botones, campos, diálogos ni svg a mano.
  Las excepciones del chrome están declaradas en el test y en `DESIGN.md`.

### Cambiado

- `resources/views` reorganizado por rol: `layouts/` con sus fragmentos en `parts/`, `components/<familia>/`
  y `pages/`. `partials/` desaparece: `_icon` y `_logo` pasan a `components/ui/`, `_csrf` a
  `components/form/` y `partials/layout/header.twig` a `layouts/parts/topbar.twig`, para no confundirlo
  con `components/ui/page-header.twig`. Los demos instalan sus vistas en `pages/<entidad>/` y el tutorial
  sigue la misma convención. `tests/Support/ViewStructureTest` convierte la convención en un contrato.

### Corregido

- `components/list/simple.twig` expone un bloque `after`, que se emite después del `</ul>`. Los demos
de notas y contactos ponen ahí los diálogos de borrado: un `<dialog>` no es hijo válido de una lista.
- `tests/Support/ShellBreadcrumbContractTest.php` recorre las rutas GET que pintan el shell y falla si
  alguna no declara `breadcrumbs`: un solo «Inicio» no emite `<nav>`, así que el olvido se detecta.
- Se quitó el contador de registros del encabezado de notas y contactos: el total ya lo informa la
  paginación, y el encabezado queda con título, subtítulo y acciones. Se van el badge, la variable
  `total` del recorrido, `contacts_count` y el bloque del JS de contactos que actualizaba el número.

### Incluido

- Componente `components/ui/page-header-title.twig`: icono opcional, `title` y `subtitle`; la mitad
  izquierda de `page-header`, usable sola en una tarjeta o un estado vacío. `page-header` ahora lo
  incluye en vez de repetir el markup.
- Componente `components/ui/action-bar.twig`: fila de controles para la esquina del encabezado o una
  tarjeta, con bloque `controls` y `extra_class`. El hueco `actions` de `page-header` acepta una sola
  unidad: la botonera es la que agrupa varias y fija la separación, sin decidir el orden.
- Componente `components/ui/page-header.twig`: icono opcional, título, subtítulo y bloque `actions`.
  Los demos de notas y contactos lo comparten; tasks conserva su encabezado propio porque su alta es
  un formulario inline, no un control en la esquina.

### Documentación

- Tutorial: el paso 6 separa el panel en su propio parcial, escribe el encabezado a mano en 6.6 y
  lo pasa a `components/ui/page-header.twig` en 6.7, en el mismo capítulo; la lista cruda llega
  hasta el capítulo 12, que extrae la fila a `components/list/simple.twig` con `embed`.
  Paginación y repetición pasan a los capítulos 13 y 14.

## [0.1.1] - 2026-11-14

### Documentación

- Paso 6 del tutorial separado en seis pasos: HTML crudo del controlador, plantilla
  Twig estática, acceso al modelo, shell, componente de lista y encabezado.
- Referencias internas renumeradas (`6.1` a `6.6`).

[0.1.1]: https://github.com/szchmausser/slim4-homemade-framework/releases/tag/v0.1.1

## [0.1.0] - 2026-11-14

Primera entrega pública del esqueleto.

### Incluido

- Slim 4 + PHP-DI + Eloquent standalone + Phinx + Twig + SQLite, sin build ni
  CDN en runtime.
- Autenticación propia: login, logout, sesión con ID regenerado, remember-me
  con split-token, rotación, detección de replay y throttle por email/IP.
- CSRF global, validación, flash/toasts, paginación server-rendered y helper de
  URLs con `APP_BASE_PATH`.
- Sistema visual con daisyUI vendorizado, Tailwind browser build, Alpine y HTMX,
  assets locales con SRI.
- Demos instalables:
  - `tasks`: CRUD con HTMX, paginación y avisos.
  - `contacts`: Tabulator remoto con paginación/orden server-side y mutaciones
    HTMX que refrescan la tabla sin recargar.
  - `notas`: CRUD incremental, modales y tutorial paso a paso.
- Gate `composer verify`: lint, docs-check, PHPStan 5, PHPUnit y migración fresh.
- CI para PHP 8.4/8.5 y round-trip de cada demo.
- Documentación: README, guía de stack Termux, tutorial CRUD y `AGENTS.md`.

### Seguridad y operación

- `APP_DEBUG=false` por defecto; Whoops solo en local.
- Sesiones en `storage/sessions` con idle/absolute timeout.
- `display_errors=0`, `log_errors=1`, sin `X-Powered-By`, `Permissions-Policy`.
- Instalador de demos con backup/restore y `remove` con checksum/`--force`.
- Tests aislados por proceso y árbol temporal para el instalador.

### Limitaciones conocidas

- Tailwind se compila en el navegador; hay FOUC y dependencia de JS para las
  utilidades. daisyUI se sirve completo.
- Sin CSP estricta por el JS inline y Alpine/Tailwind runtime.
- `expires_at`/`attempted_at` son enteros Unix; en PHP de 32 bits el límite es
  2038.
- Los demos no implementan ownership multiusuario.
- CI hosted es x86_64; la verificación 32-bit es manual en Termux.

[0.1.0]: https://github.com/szchmausser/slim4-homemade-framework/releases/tag/v0.1.0
