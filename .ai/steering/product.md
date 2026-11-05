# Producto: Slim 4 Homemade Framework

## Producto Vision

Esqueleto PHP full-stack optimizado para **Termux (ARM 32-bit)**, sin necesidad de toolchain de compilación. Provee una base estructurada (Slim 4 + PHP-DI + Eloquent + Twig + HTMX + Alpine.js + daisyUI + SQLite) para apps server-rendered con testing y troubleshooting completos.

## Usuarios / Personas

- **Desarrollador PHP:** Quiere un punto de partida rápido y mantenible, sin configuración compleja en Termux.
- **Operador Termux:** Necesita que la app funcione en dispositivos ARM 32-bit sin dependencias de compilación.
- **Dueño de Proyecto:** Busca tests verdes, migraciones Phinx idempotentes y despliegues consistentes.

## Propuesta de Valor

- **Arranque en 6 pasos:** `cp .env.example .env && composer install:termux && composer dump-autoload -o && composer migrate && ADMIN_EMAIL=... vendor/bin/phinx seed:run && php -S 0.0.0.0:8080 -t public`
- **Suite de tests verde** como señal de calidad garantizada.
- **Estandarizado:** Cada CRUD sigue el patrón de 8 pasos (migración → modelo → controlador → rutas → vistas → tests → seed → verificar).
- **Sin CDN en runtime:** Assets vendorizados con SRI en `public/assets/`.

## Límites del Producto (Boundaries)

### In Scope

- Estructura de capas Slim 4 (Controllers, Middleware, Models, Support).
- Integración Twig + HTMX + Alpine.js + daisyUI.
- Migraciones y seeds con Phinx.
- Testing con PHPUnit (unit + HTTP).
- Comandos `composer` acotados (migrate, test, verify, db:backup, check:termux).
- Servidor dev **fuera** de Composer: `php -S 0.0.0.0:8080 -t public`. `composer serve` está retirado (Composer mata los scripts a los 300 s de `process-timeout`); el script quedó como guarda que aborta explicando el comando correcto.

### Fuera de Scope

- Compilación nativa o toolchain de C/ASM.
- Integración con CDN externo en producción.
- Soporte para arquitecturas x86_64 fuera de Termux.
- Framework PHP distinto a Slim 4.

## Métricas de Éxito

- `composer test` → suite verde (la cifra la imprime el comando).
- `php -S 0.0.0.0:8080 -t public` → servidor respondiendo en `:8080` (nunca `composer serve`).
- `composer check:termux` → detecta correctamente 32-bit PHP.
- Migración fresh (`phinx rollback -t 0` + `composer migrate`) sin errores.

## Glosario

- **Slim 4:** Micro-framework PHP PSR-7.
- **PHP-DI:** Inversión de control container.
- **Eloquent:** ORM standalone (no Laravel).
- **Phinx:** Migraciones y seeding.
- **Twig:** Motor de plantillas.
- **HTMX:** HTML sobre HTTP para interacciones.
- **Alpine.js:** Minimal JS reactivity.
- **daisyUI:** Componentes UI sobre Tailwind.
- **SQLite:** Base de datos embutida.