# Tech Stack

## Frameworks y Librerías

| Capa | Tecnología | Versión / Notas |
|------|------------|-----------------|
| **Framework** | Slim 4 | ^4.15 (server-rendered, PSR-7 compliant) |
| **DI Container** | PHP-DI | ^7.1 |
| **ORM** | Eloquent (Capsule standalone) | ^4.x |
| **Routing** | Slim Router | ^1.x |
| **Validation** | respect/validation | ^2.0 |
| **CSRF** | slim/csrf | ^1.5 |
| **Frontend** | Twig | ^3.30 |
| **HTTP Client** | Phinx | ^0.13 (migrations + seeds) |
| **Testing** | PHPUnit | ^13+ |
| **Logging** | Monolog | ^3.12 |
| **Language** | PHP 8.2+ | ARM 32-bit compatible |

## Herramientas de Desarrollo

- **Composer** – Gestión de dependencias
- **Phinx** – Migraciones y seeds
- **PHPUnit** – Pruebas unitarias y de integración
- **Termux** – Entorno de ejecución (ARM 32-bit)

## Patrones Recomendados

- **CRUD estándar**: Modelo → Controlador → Ruta → Vista (sin paginación directa en controlador)
- **Paginación**: Usar `App\Models\Pagination` con `Pagination::paginate()`
- **HTMX**: Una sola región por página que devuelve HTML de la sección modificada
- **HTMX actions**: `hx-on::save="this.save()"`, `hx-on::update="this.update()"`, `hx-on::delete="this.delete()"`
- **Dark/Light theme**: Controlado por `data-theme` (ninguna utility `dark:` o `light:` en CSS)
- **Assets**: Vendorizados con SRI (ningún CDN en runtime)

## Limitaciones en Termux

- PHP 64-bit puede fallar con `int32` en SQLite; se recomienda PHP 32-bit
- Memoria limitada: evitar cargas pesadas en el contenedor
- Sin compilador de PHP, solo binario precompilado

## Referencias

- [Guía completa: `docs/guia-stack-php-termux.md`](docs/guia-stack-php-termux.md)
- [SDD Practical: `../_shared/references/sdd-practical.md`](../_shared/references/sdd-practical.md)
