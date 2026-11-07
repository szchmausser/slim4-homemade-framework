# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Para quien arranca una app PHP server-rendered en un entorno sin toolchain de compilación: Termux sobre ARM 32-bit, offline, sin Node ni bundler. La persona que copia el esqueleto espera que el clon funcione al migrar y que las decisiones de base ya estén tomadas. También lo usan quienes trabajan en 64-bit (CI, escritorio) con el mismo lock.

## Product Purpose

Esqueleto PHP en capas para apps server-rendered: una base copiable con autenticación, paginación, avisos por HTMX y un sistema visual completo sobre Slim 4 + PHP-DI + Eloquent standalone + Phinx + Twig + HTMX + Alpine + daisyUI 5. Los demos de tareas y contactos son opcionales; el producto real es el esqueleto limpio. El éxito es que el clon arranque sin build, con tests verdes, migraciones idempotentes y una app resultante que no cargue andamiaje de los demos.

## Positioning

Un esqueleto PHP server-rendered que corre en Termux ARM 32-bit sin toolchain de compilación ni bundler, con autenticación, paginación y HTMX ya resueltos, y demos instalables que se pueden quitar sin dejar archivos, tablas ni filas de migración. La separación entre `framework/` reusable y `app/` de dominio permite que el usuario sea dueño de `app/` desde el primer día.

## Operating Context

- Entorno primario: Termux ARM 32-bit, offline; también se trabaja en 64-bit con el mismo lock.
- El clon se instala con Composer 2, se migra con Phinx y se sirve con `php -S ... -t public`; `composer serve` no es el servidor.
- Los demos se instalan con `composer demo:tasks:install` (tareas) o `composer demo:contacts:install` (contactos + Tabulator) y se quitan con sus comandos `remove`; `demo:install` / `demo:remove` quedan como alias de tareas. La base clonada parte sin demos.
- `composer verify` es el gate: lint + docs-check + PHPStan nivel 5 + suite + migración fresh en SQLite temporal.
- No hay paso de build: el CSS de utilidades lo compila el browser build de Tailwind; los assets están vendorizados con SRI.

### Superficies

| superficie | ruta | notas |
|---|---|---|
| Inicio | `/` | pública, presentación del esqueleto |
| Acceso | `/login` | autenticación propia, fuera del shell |
| Tareas | `/tareas` | solo si el demo está instalado; protegida, swaps HTMX, paginación y avisos |
| Contactos | `/contactos` | solo si el demo está instalado; CRUD server-rendered y listado Tabulator client-side |
| Error | cualquiera | autónoma, mínima, en español |

## Capabilities and Constraints

- Slim 4, PHP-DI, Eloquent standalone, Phinx, Twig, HTMX, Alpine.js, daisyUI 5, SQLite, respect/validation y Monolog.
- PHP >= 8.4.1; SQLite; las migraciones son la única fuente del esquema.
- `app/` es del dominio del usuario; `framework/` contiene la infraestructura reusable (`Auth`, `Http`, `Validation`, `Docs`) con namespace `Framework\`; `framework/scaffolds/tasks/` queda fuera del autoload hasta instalarse.
- Assets vendorizados con SRI; sin CDN en runtime y sin build de CSS.
- Fuera de alcance: registro público, reseteo por email, JWT/OAuth, roles, compilación nativa, un framework PHP distinto de Slim 4 y cualquier función que exija un paso de compilación.
- `composer docs:check` falla si las copias de archivos dentro de la guía divergen del repo; al cambiar una vista o un test hay que correr `composer docs:sync`.

## Brand Commitments

- Nombre de trabajo visible: **Mi App** (placeholder).
- Logo SVG abstracto de capas; es una marca genérica y reemplazable, no una identidad final.
- Voz de producto en español neutro, directa y sin jerga innecesaria.

## Evidence on Hand

- `framework/scaffolds/tasks/` contiene un demo funcional de CRUD, completar/pendiente, paginación y avisos HTMX, con migraciones, seeds y tests.
- `framework/scaffolds/contacts/` contiene un CRUD de contactos con listado Tabulator 6.7.1 vendorizado, migración, seed y tests.
- `README.md`, `docs/guia-stack-php-termux.md`, `DESIGN.md` y `.ai/steering/` son la documentación operativa y de decisiones.
- La suite base y el round-trip del demo son evidencia ejecutable; los conteos exactos se leen de `composer test`, no se fijan acá.
- No hay testimonios, clientes, benchmarks, precios ni assets de marca definitivos; no inventarlos.

## Product Principles

1. El clon debe funcionar sin build y con las decisiones de base ya tomadas.
2. `app/` pertenece al usuario; la infraestructura reusable no vive ahí.
3. Los demos son opcionales, visibles y removibles sin residuos en archivos ni DB.
4. La calidad se demuestra con el gate (`composer verify`) y con migraciones reales, no con claims.
5. Seguridad, accesibilidad y compatibilidad con Termux son piso, no features opcionales.

## Accessibility & Inclusion

Piso WCAG AA: texto ≥ 4.5:1, objetos gráficos ≥ 3:1, foco visible, nombres accesibles en todos los controles y navegación por teclado. La interfaz es responsive y debe funcionar en el navegador móvil de Android.
