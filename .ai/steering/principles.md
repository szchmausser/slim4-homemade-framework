# Principios del Proyecto

> Status: Active
> Last Updated: 2025-10-08

## Propósito

Estas reglas gobiernan decisiones de diseño y coordinación en el proyecto. Se aplican en todas las fases (PRD, SPEC, TASKS, EXEC, REVIEW). Si un principio entra en conflicto con un artifact aprobado, se detiene y se pregunta al usuario.

## Cómo Usar Estos Principios

- **MUST:** no negociable a menos que el usuario cambie explícitamente.
- **SHOULD:** fuerte default; excepciones necesitan razón.
- **MAY:** opción permitida, no requerimiento.

## Principios

### P-001: MUST — Sin toolchain de compilación en Termux

**Rule:** Todo el código y dependencias debe instalarse por medio de `composer install --prefer-dist --no-scripts` o los scripts definidos en `composer.json`. No se permite compilar desde fuente (C/ASM) en el entorno Termux.

**Reason:** El usuario objetivo ejecuta en Termux (Android, ARM 32-bit) sin toolchain de compilación. Cualquier dependencia que requiera compilación debe proveerse como binario precompilado.

**Applies to:** PRD, SPEC, TASKS, EXEC, REVIEW

### P-002: MUST — Respuestas HTMX devuelven HTML de región, never redirects 303

**Rule:** Cuando la respuesta es para HTMX, el cuerpo debe ser HTML de la región swap. Los redirects 303 solo están permitidos para navegación normal (usuario agente clásico). Para HTMX, usar `HX-Redirect` header o devolver HTML que incluya el redirect como contenido.

**Reason:** El comportamiento esperado por los tests y la receta de 8 pasos: "Respuestas HTMX devuelven HTML de la región, nunca redirects (303 solo para navegación normal; HX-Redirect para HTMX)".

**Applies to:** PRD, SPEC, TASKS, EXEC, REVIEW

### P-003: MUST — data-theme gobierna claro/oscuro, cero utilities dark:

**Rule:** El interruptor de tema se controla mediante el atributo `data-theme` en el elemento `<html>`. Está prohibido usar utilities CSS `dark:` (de Tailwind) o cualquier JS de terceros para cambiar tema. Solo cambios en `data-theme` provocan el cambio de estilo.

**Reason:** Consistencia visual y evitar CSS no utilizado. El proyecto usa daisyUI as-is, cuyo tema switch ya respeta `data-theme`.

**Applies to:** PRD, SPEC, TASKS, EXEC, REVIEW

### P-004: SHOULD — Assets vendorizados con SRI, nada de CDN en runtime

**Rule:** Todos los assets (JS, CSS) deben descargarse y almacenarse en `public/assets/` con atributos SRI (Subresource Integrity). En producción, nunca se deben cargar recursos de CDN en tiempo de ejecución.

**Reason:** Confiabilidad: si el CDN falla, la app sigue funcionando. También cumple el requisito del producto "Assets en public/assets/ vendorizados con SRI".

**Applies to:** PRD, SPEC, TASKS, EXEC, REVIEW

### P-005: SHOULD — Cada test paga su mantenimiento

**Rule:** Si un test fue escrito, debe mantenerse. El criterio: "si no te da miedo que falle, no lo escribas". Los tests deben cubrir: crea-OK, validación que rechaza, id inexistente que avisa.

**Reason:** La base de tests es la señal de calidad del proyecto. Cada nuevo test incrementa la superficie de mantenimiento.

**Applies to:** PRD, SPEC, TASKS, EXEC, REVIEW

### P-006: MAY — Migraciones con nombres secuenciales (004, 005, ...)

**Rule:** Las migraciones Phinx pueden renombrarse a numeración secuencial (`004_...`, `005_...`) en lugar de timestamps, para evitar saturación de int32. No es obligatorio, pero es la práctica recomendada del proyecto.

**Reason:** Los timestamps saturan int32 en bases grandes. La numeración secuencial es más predecible y más fácil de inspeccionar.

**Applies to:** PRD, SPEC, TASKS (migrations only)

## Reglas de Cambio

- Cambios a principios `MUST` requieren aprobación explícita del usuario.
- Los principios modificados no reescriben silenciosamente los artifacts SDD aprobados.
- Si un principio entra en conflicto con un requisito aprobado, se detiene y se pregunta cuál fuente actualizar.