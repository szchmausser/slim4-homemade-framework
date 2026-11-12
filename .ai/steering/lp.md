# Layered PHP / Framework Termux

## Descripción

This document defines the **layered architecture** of the Slim 4 Homemade Framework, optimized for Termux (ARM 32-bit) deployment.

## Capas (Layers)

| Capa | Responsabilidad | Ejemplos | Patrones |
|------|-----------------|----------|----------|
| **Presentation** | Vistas, HTML, HTMX regions | `layouts/app.twig`, `resources/views/tasks/_panel.twig` | Region-based rendering |
| **Application** | Controllers, business logic | `App\Http\Controllers\Auth\AuthController` | Single responsibility per controller |
| **Domain** | Models, entities, validation | `App\Models\User`, `Framework\Validation\Validator` | Eloquent ORM + Respect validation |
| **Infrastructure** | DB, middleware, services | `Framework\Http\Pagination`, `Framework\Http\Middleware\RememberMeMiddleware` | Dedicated classes per concern |
| **Data** | Migrations, seeds, schema | `database/migrations/*`, `database/seeds/*` | Phinx-driven, sequential numbering |

## Principios de Diseño

1. **Separación de capas**: Cada capa tiene una responsabilidad única y no mezcla preocupaciones.
2. **HTMX-first**: Las interacciones UI se manejan con HTMX, no con AJAX/JSON polling.
3. **SQLite-native**: Todas las operaciones de base de datos usan SQLite (embedded, no servidor externo).
4. **Termux-compatible**: Evitar características que asuman 64 bits (int64, caches globales pesados).
5. **Single Responsibility**: Cada controlador, modelo y middleware cumple una función clara.

## Arquitectura Detallada

### 1. Routes (`Routes/web.php`)
- Define endpoints con nombres descriptivos.
- Aplica middleware de autorización (`RequireAuthMiddleware`).
- Agrupa rutas por recurso (ej: `task` collection).

### 2. Controllers (`App\Http\Controllers`)
- Implementan la lógica de negocio en métodos sobrecargados (`index`, `create`, `store`, `show`, `edit`, `destroy`).
- Delegan a modelos para persistencia.
- Retornan regiones HTMX o HTML completo según la respuesta.

### 3. Models (`App\Models`)
- Extensión de `Illuminate\Database\Eloquent\Model`.
- Usan `$fillable` para campos permitidos.
- Implementan métodos estáticos para operaciones comunes (paginación, filtros).

### 4. Framework (`Framework`)
- `Auth/`: `Auth`, `RememberMe`, `RememberToken`, `Throttle`.
- `Http/`: `CsrfTokens`, `Flash`, `Url`, `Pagination`.
- `Validation/`: `Validator`.
- `Docs/`: `CodeBlocks` (tooling de la guía).
- `App\Support/` queda para helpers propios de la app; hoy no tiene clases.
- El demo de tareas vive en `framework/scaffolds/tasks/` y se instala/desinstala con `composer demo:tasks:install` / `composer demo:tasks:remove` (`demo:install` / `demo:remove` quedan como alias).

### 5. Middleware (`Framework\Http\Middleware`)
- `Session`, `SecurityHeaders`, `RememberMe`, `RequireAuth`.
- El pipeline base vive aquí; `app/Http/Middleware` queda para middleware propio de la app.

### 6. Database (`database`)
- **Migrations**: Phinx-generated, numbered sequentially.
- **Seeds**: Datos iniciales idempotentes.
- **Schema**: SQLite tables creadas dinámicamente.

### 7. Public Assets
- CSS/Twigs/vendored JS (daisyUI, Alpine) embebidos con SRI.
- No CDN en producción.

## Diagrama de Flujo

```
[Request] → [Route] → [Middleware] → [Controller] → [Model] → [DB]
                              ↓
                     [HTMX Response] → [Template] → [HTML]
```

## Consideraciones Termux

- **Memoria**: Mantener el contenedor ligero; evitar cargas innecesarias.
- **Int32**: SQLite en Termux 32-bit requiere índices y claves enteras de 32 bits.
- **PHP 32-bit**: El runtime objetivo es PHP con `PHP_INT_SIZE === 4`; el código evita asumir 64 bits.
- **Compilación**: No se necesita compilar código PHP; todo es precompilado.

## Próximos Pasos

- Capturar la primera idea con `/skill:sdd-idea`
- Crear un plan con `/skill:sdd-plan`
- O definir requisitos para una característica concreta con `/skill:sdd-prd`

---

*Documento de dominio (lp.md)*
*Actualizado: 2026-08-28*
