# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Versionado semántico mientras el proyecto esté en `0.x`.

## [Unreleased]

### Incluido

- Componente `components/ui/page-header.twig`: icono opcional, título, subtítulo y bloque `actions`.
  Los demos de notas y contactos lo comparten; tasks conserva su encabezado propio porque su alta es
  un formulario inline, no un control en la esquina.

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
