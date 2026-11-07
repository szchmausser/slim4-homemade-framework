# Producto: Slim 4 Homemade Framework

> La fuente de verdad del producto es [`PRODUCT.md`](../../PRODUCT.md). Este archivo resume el contexto operativo para los agentes.

## Product Vision

Esqueleto PHP full-stack optimizado para **Termux (ARM 32-bit)**, sin toolchain de compilación. Provee una base estructurada (Slim 4 + PHP-DI + Eloquent + Twig + HTMX + Alpine.js + daisyUI + SQLite) para apps server-rendered con testing y troubleshooting completos. La infraestructura reusable vive en `framework/`; `app/` es del dominio del usuario. Los demos de tareas y contactos son opcionales, no el producto; el segundo incluye una isla Tabulator vendorizada.

## Usuarios / Personas

- **Desarrollador PHP:** Quiere un punto de partida rápido y mantenible, sin configuración compleja en Termux.
- **Operador Termux:** Necesita que la app funcione en dispositivos ARM 32-bit sin dependencias de compilación.
- **Dueño de Proyecto:** Busca tests verdes, migraciones Phinx idempotentes y despliegues consistentes.

## Propuesta de Valor

- **Separación de contextos:** `framework/` contiene la infraestructura reusable (`Auth`, `Http`, `Validation`, `Docs`); `app/` queda para modelos, controladores y helpers del dominio; `framework/scaffolds/tasks/` y `framework/scaffolds/contacts/` son los demos instalables.
- **Demos opcionales y removibles:** `composer demo:tasks:install` / `composer demo:tasks:remove` operan sobre tareas (`demo:install` / `demo:remove` siguen como alias); `composer demo:contacts:install` / `composer demo:contacts:remove` sobre contactos + Tabulator. Ambos limpian archivos, tablas y filas de `phinxlog`.
- **Sin CDN en runtime:** assets vendorizados con SRI en `public/assets/`; sin build de CSS ni bundler.
- **Gate de calidad:** `composer verify` ejecuta lint, docs-check, PHPStan nivel 5, suite y migración fresh sobre SQLite temporal.
- **Servidor dev fuera de Composer:** `php -S 0.0.0.0:8080 -t public`; `composer serve` quedó como guarda que explica el comando correcto.

## Límites del Producto (Boundaries)

### In Scope

- Slim 4, PHP-DI, Eloquent standalone, Phinx, Twig, HTMX, Alpine.js, daisyUI 5 y SQLite.
- Autenticación, paginación, avisos HTMX, migraciones y seeds.
- Infraestructura reusable en `framework/` y código app-owned en `app/`.
- Demo de tareas y demo de contactos con listado Tabulator, ambos instalables y removibles.
- Testing con PHPUnit (unit + HTTP) y comandos Composer acotados (`migrate`, `test`, `verify`, `docs:check`, `docs:sync`, `db:backup`, `demo:tasks:install`, `demo:tasks:remove`, `demo:contacts:install`, `demo:contacts:remove`, `check:termux`).
- Despliegue con prefijo de ruta mediante `APP_BASE_PATH`.

### Fuera de Scope

- Compilación nativa, toolchain de C/ASM, build de CSS o bundler.
- CDN externo en runtime.
- Framework PHP distinto de Slim 4.
- Registro público, reseteo por email, JWT/OAuth y roles.

## Métricas de Éxito

- `composer verify` → lint, docs-check, PHPStan, suite y migración fresh sin errores.
- `php -S 0.0.0.0:8080 -t public` → servidor respondiendo en `:8080` (nunca `composer serve`).
- `composer check:termux` → detecta correctamente 32-bit PHP.
- Round-trip de cada demo: instalar → tests/PHPStan → remover → tests, sin residuos en archivos, DB ni migraciones.

## Glosario

- **Framework:** infraestructura reusable en `framework/` con namespace `Framework\`.
- **Scaffold:** plantilla instalable que no se autolodea hasta copiarse al proyecto.
- **Demo:** scaffold `tasks` o `contacts` que se instala con sus comandos `composer demo:*:install`.
- **Slim 4:** Micro-framework PHP PSR-7.
- **PHP-DI:** Inversión de control container.
- **Eloquent:** ORM standalone (no Laravel).
- **Phinx:** Migraciones y seeding.
- **Twig:** Motor de plantillas.
- **HTMX:** HTML sobre HTTP para interacciones.
- **Alpine.js:** Minimal JS reactivity.
- **daisyUI:** Componentes UI sobre Tailwind.
- **SQLite:** Base de datos embutida.
