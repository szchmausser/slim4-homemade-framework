# Stack Tecnológico

## Runtime / Plataforma

- **PHP:** >= 8.4.1 (Termux ARM 32-bit detectado por `PHP_INT_SIZE === 4`). El piso lo impone el lock: `symfony/clock` y `symfony/translation` exigen `>=8.4.1`, `illuminate/database ^13` exige `^8.3` y `phpunit ^13.3` exige `>=8.4.1`.
- **Sistema:** Termux en Android, sin toolchain de compilación.
- **Administrador de paquetes:** Composer (`composer install:termux` / `composer install`).

## Frontend

- **Framework:** Slim 4 (micro-framework PHP).
- **Motor de plantillas:** Twig ^3.30 + slim/twig-view ^3.4.
- **Estilizado:** daisyUI 5 (vendorizado, sobre Tailwind).
- **Interactividad:** HTMX + Alpine.js (vendorizados en `public/assets/`).
- **SRI:** Subresource Integrity en assets (ningún CDN en runtime).

## Backend

- **DI:** PHP-DI ^7.1.
- **Base de datos:** illuminate/database ^13.33 (standalone Eloquent capable).
- **Migraciones:** phinx ^0.13.
- **Validación:** respect/validation ^2.0.
- **Logs:** monolog/monolog ^3.12.

## Testing / Verificación

- **Lint/Typecheck:** `composer lint` + `composer docs:check` + `composer phpstan` (PHPStan 2.x nivel 5, phar dev-only, baseline vacía).
- **Test:** PHPUnit ^13.3 (`vendor/bin/phpunit` o `composer test`).
- **Verificación completa:** `composer verify` = lint + docs-check + PHPStan + suite + migración fresh sobre DB temporal.
- **CI:** GitHub Actions con matriz PHP 8.4/8.5 ejecutando `composer verify`.
- **Build:** Ninguno (PHP interpretado).
- **Comandos clave:**
  - `php -S 0.0.0.0:8080 -t public` — servidor dev en `:8080`. **No** `composer serve`: Composer mata los scripts al vencer `process-timeout` (300 s) y el script quedó como guarda (`scripts/serve.php`).
  - `composer migrate` — `phinx migrate`.
  - `composer test` — suite completa.
  - `composer verify` — lint + docs-check + PHPStan + tests + migración fresh.
  - `composer docs:check` — cifras hardcodeadas, CDN runtime y claims 64-bit.
  - `composer check:termux` — detección 32/64-bit.

## Restricciones

- **Versiones bloqueadas** en composer.json (no usar `*` suelto en producción).
- **Sin compilación** en el entorno Termux (solo `--prefer-dist`).
- **Assets vendorizados** en `public/assets/` con SRI (no CDN en runtime).
- **ARM 32-bit** como plataforma objetivo principal.

## Decisiones Arquitectónicas

- **Capas separadas:** `app/Http/Controllers/`, `app/Http/Middleware/`, `app/Models/` (solo dominio), `app/Support/` (infraestructura: `Auth`, `RememberMe`, `RememberToken`, ...).
- **PDO/SQLite** como driver de base de datos (via illuminate/database).
- **Sesión + CSRF** manejados por middleware Slim built-in.
- **Tests** con body raw + URI absoluta + login inyectado para rutas protegidas.