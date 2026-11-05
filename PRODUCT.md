# PRODUCT.md

Esqueleto PHP en capas para apps server-rendered, pensado para **Termux ARM 32-bit** y para funcionar **sin compilar nada**.

## Qué es

Una base copiable: CRUD de tareas con autenticación, paginación, avisos por HTMX y un sistema visual completo, sobre Slim 4 + PHP-DI + Eloquent standalone + Phinx + Twig + HTMX + Alpine + daisyUI 5. La app de tareas es la demostración; el producto real es el esqueleto.

## Para quién

Para quien va a arrancar una app PHP server-rendered en un entorno sin toolchain de compilación: ARM 32-bit, offline, sin Node, sin bundler. La persona que lo copia espera que todo funcione al clonar y migrar, y que las decisiones ya estén tomadas.

## Restricciones duras

- **No hay paso de build.** Ni ahora ni después. El CSS de utilidades lo compila `public/assets/tailwind-browser-4.3.3.js` en el navegador; por eso no existe `@theme` y los tokens propios son custom properties planas en el `<style>` del layout.
- **Assets vendorizados con SRI.** Offline y sin cambio de runtime sin deploy. `daisyui-5.7.46.css`, `tailwind-browser-4.3.3.js`, `htmx-2.0.11.min.js`, `alpine-3.17.4.min.js`.
- **PHP >= 8.4.1** por el lock.
- **SQLite** como base; las migraciones son la única fuente del esquema.

## Superficies

| superficie | ruta | modo | notas |
|---|---|---|---|
| Inicio | `/` | Operate | pública, presentación del esqueleto |
| Acceso | `/login` | Operate | superficie de autenticación propia, fuera del shell |
| Tareas | `/tareas` | Operate | protegida; lista con swaps parciales, paginación y avisos |
| Error | cualquiera | Operate | página autónoma, mínima, en español |

## Fuera de alcance

Registro público, reseteo por email, JWT/OAuth, roles, build de CSS, y cualquier función que exija un paso de compilación.

## Contrato de calidad

`composer verify` (lint + docs-check + PHPStan nivel 5 + suite + migración fresh sobre DB temporal) es el gate. `composer docs:check` falla si las copias de archivos dentro de la guía divergen del repo: al cambiar una vista o un test hay que correr `composer docs:sync`.
