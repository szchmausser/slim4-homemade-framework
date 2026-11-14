# Contribuir

Gracias por querer mejorar el esqueleto. Antes de abrir un PR:

## Entorno

- PHP 8.4.1+ con `pdo_sqlite`, `sqlite3` y `mbstring`.
- Composer 2.
- No se requiere Node, bundler ni toolchain de compilación.

```bash
composer install:termux     # o composer install en 64-bit
cp .env.example .env
composer migrate
composer test
```

## Gate mínimo

```bash
composer verify
```

Debe quedar verde: lint, docs-check, PHPStan, PHPUnit y `migrate:fresh`. En
Termux ARM 32-bit, sumá:

```bash
composer check:32bit
```

Si tu cambio toca un demo:

```bash
composer demo:contacts:install
composer test
composer demo:contacts:remove
```

## Invariantes

- Sin paso de build, sin bundler y sin CDN en runtime.
- Assets locales con `integrity="sha384-..."`; si cambiás un asset, actualizá el SRI.
- URLs con `url()` en Twig o `Url::path()` en PHP; nunca literales raíz-absolutos.
- Un archivo de rutas por entidad; no edites `bootstrap/app.php` para registrarla.
- Migraciones secuenciales; no edites una migración ya publicada.
- Si copiás un archivo a la guía, corré `composer docs:sync` y `composer docs:check`.
- Si agregás una vista/test copiado por la guía, actualizá la copia correspondiente.

## Estilo

- `declare(strict_types=1);` en PHP.
- Controladores finos; la lógica de dominio puede vivir en clases propias.
- Tests para el happy path y para la ruta de fallo (CSRF, 403/404/405, validación).
- Comentarios en español neutro; nombres de código en inglés si el dominio lo permite.

## Pull requests

- Un cambio por PR, con contexto y tradeoffs.
- Incluí el resultado de `composer verify`.
- Si el cambio afecta seguridad, datos o migraciones, explicá el plan de rollback.
- No incluyas `.env`, bases SQLite, logs, backups ni secretos.

## Reportes de seguridad

Ver [`SECURITY.md`](SECURITY.md). No abras un issue público para una
vulnerabilidad.
