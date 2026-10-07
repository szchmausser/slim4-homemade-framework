# Producto

## Slim 4 Homemade Framework

Un framework de capas para aplicaciones server-rendered en PHP (Slim 4) diseñado específicamente para ejecutarse en **Termux** (ARM 32-bit). 

### Características principales

- **Arquitectura en capas**: Controllers → Middleware → Models (Eloquent/Capsule) → Support → Routes → Views (Twig)
- **Sin toolchain de compilación**: Todo se ejecuta en Termux sin necesidad de PHP-FPM, Docker, etc.
- **Base de datos SQLite**: Fácil de usar en dispositivos móviles
- **HTMX + Alpine.js + daisyUI 5**: Interactividad reactiva ligera sin JavaScript pesado
- **Phinx**: Migraciones y seeds gestionados desde la consola
- **Respect/Validation**: Validación de entrada robusta
- **Monolog**: Logging estructurado

### Estructura de carpetas

```
app/
├── Http/
│   ├── Controllers/   # Controladores (Auth, Task, etc.)
│   └── Middleware/   # Middleware (Session, CSRF, RememberMe, Auth)
├── Models/           # Modelos Eloquent (User, Task, RememberToken)
├── Support/          # Helpers (Auth, Flash, Validator, Pagination, RememberMe)
├── Routes/           # Definición de rutas (web.php)
├── Config/           # Configuración (container.php, middleware.php)
└── Database/         # Migraciones y Seeds
```

### Uso típico

1. **Migración** – Crear tabla con Phinx, renombrar secuencialmente, ejecutar `composer migrate`
2. **Modelo** – Extender `App\Models\{Name}` con `$fillable`
3. **Controlador** – Extender `Controller` con lógica de negocio
4. **Ruta** – Registrar en `Routes/web.php` con `->add()`
5. **Vista** – Extender `layouts/app.twig` con componentes reutilizables
6. **Test** – Clonar el estilo de tests (request con body crudo + tearDown)

### Requisitos

- PHP ≥ 8.2
- Slim 4 · PHP-DI · Eloquent (Capsule standalone) · Phinx · respect/validation · Twig · slim/csrf · monolog
- Base de datos SQLite

### Enlace

[Guía paso a paso: `docs/guia-stack-php-termux.md`](docs/guia-stack-php-termux.md)
