# Problem Definition: primera entidad propia sin recorrido corto

- **Slug**: tutorial-crud
- **Created**: 2026-11-11
- **Inputs used**: intake.md + research.md

## Problem Statement

Quien adopta este framework no tiene un recorrido corto y ordenado que lo lleve de «cloné el repo»
a «mi propia entidad funciona». El material disponible está escrito para otra cosa: la guía
construye el framework (7179 líneas, 23 capítulos, con un único recorrido end-to-end de 1212 líneas
atado a la entidad `tasks`) y los demos entregan código ya terminado que hay que leer y desarmar
(24 y 15 archivos). Ninguno de los dos responde la pregunta del que llega nuevo: en qué orden mínimo
se agrega una entidad propia, y cómo saber después de cada paso que sigue funcionando.

El lector objetivo parte de un **clon del repo**, no de una aplicación ya en marcha — [source: decisión del solicitante, 2026-11-11].

## Affected Users & Stakeholders

- **Users**: «Desarrollador PHP que llega al repo» — persona declarada en `.ai/steering/product.md:11` («punto de partida rápido y mantenible»). Afectado: el punto de partida existe, el camino corto no.
- **Users (secundario)**: «Operador Termux» — `.ai/steering/product.md:12`. Afectado de forma indirecta: cualquier recorrido que ignore las restricciones de 32 bits y la ausencia de toolchain lo deja en un callejón.
- **Users (externos)**: `[NEEDS CLARIFICATION: research.md no encontró issues, tickets, telemetría ni encuestas; no se puede afirmar que alguien además del solicitante esté afectado.]`
- **Stakeholders**: dueño del repo — decide y financia; pidió el artefacto en esta sesión y fijó el alcance (fuera de `docs:check`).
- **Stakeholders**: mantenedor del gate (`composer verify`) — interés en que ningún material nuevo envejezca sin aviso, según la medición de `scripts/docs-check.php:11`.

## Goals

- Que una persona nueva llegue a una entidad propia funcionando siguiendo un orden explícito de pasos, sin tener que leer la guía completa ni los scaffolds completos.
- Que el recorrido sea ejecutable: después de cada paso, el lector pueda comprobar por sí mismo que la aplicación sigue respondiendo y que el paso hizo lo que decía.
- Que el material sea reconociblemente distinto del capítulo 16, porque convive con él y no puede modificarlo; si no agrega un camino nuevo, no resuelve el problema.
- Que el camino respete las restricciones vigentes del proyecto (Termux ARM 32-bit sin toolchain, assets con SRI, tema por `data-theme`).
- Que el recorrido sea **transferible**: al terminarlo, el lector pueda repetirlo con su propia entidad y no sólo haber llegado a una entidad de ejemplo — [source: decisión del solicitante, 2026-11-11].
- Que el recorrido **explique las piezas reutilizables** que el framework ya ofrece, en el momento en que aparecen: para qué sirven, cuándo usarlas y qué se evita al usarlas — [source: decisión del solicitante, 2026-11-11].

## Non-Goals

- No es documentación viva ni parte del funcionamiento de la app: queda fuera de `docs:check` y, en consecuencia, fuera de `docs/`, `.ai/` y el README — [source: decisión del solicitante, 2026-11-11].
- No reemplaza **ni modifica** `docs/guia-stack-php-termux.md` (incluido su capítulo 16) ni los demos instalables — [source: decisión del solicitante, 2026-11-11].
- No enseña a construir el framework (Termux, Composer, DI, middlewares, auth, assets): eso ya está en la guía. Las piezas reutilizables se explican **para usarlas**, no para modificarlas ni para reimplementarlas.
- No propone arquitectura nueva ni cambia decisiones del sistema visual documentadas en `DESIGN.md`.
- No es un curso del flujo SDD: eso es `docs/tutorial-de-uso-spec-kit.md`.
- No cubre despliegue ni exposición pública; hay material propio para eso.

## Success Metrics

- Una persona nueva llega a su primera entidad funcionando sin abrir `framework/scaffolds/` ni recorrer la guía entera (baseline: unknown; es una señal cualitativa y se declara como tal).
- Cada paso deja un estado observable y comprobable por el lector: la ruta responde, la fila persiste, la validación rechaza (baseline: unknown; el capítulo 16 no declara puntos de control por paso).
- El material no rompe `composer verify` al agregarse (baseline: el gate está en verde hoy).
- Cantidad de pasos y tiempo hasta la primera entidad propia (baseline: unknown; sin esto no hay forma de comparar contra el capítulo 16).
- El lector repite el patrón con una segunda entidad propia y puede nombrar las piezas reutilizables que usó (baseline: unknown; señal cualitativa, declarada como tal).

## Cost of Inaction

Quien llega nuevo elige entre tres caminos peores: leer 1212 líneas monolíticas de un capítulo con
una sola subsección, instalar un demo y desarmar 24 archivos para reconstruir el orden, o improvisar
sobre el esqueleto. Ninguno de los tres es el recorrido mínimo que busca. El costo no está medido
—`research.md` no encontró señal de uso— así que esto es una inferencia desde la estructura del
material existente, no una medición.

## Open Questions

- [NEEDS CLARIFICATION: ¿Hay evidencia de que la ausencia de este recorrido trabe a alguien? La única señal registrada es el pedido en sesión (n=1).]
- [NEEDS CLARIFICATION: ¿Qué cuenta como «funcionando» al final: una ruta que responde, una fila persistida, un test en verde? Es la definición que hace medible el primer goal.]
- [NEEDS CLARIFICATION: ¿La comprobación de cada paso incluye tests del proyecto o alcanza con una verificación manual del lector?]
- [NEEDS CLARIFICATION: ¿Qué reemplaza a `docs:check` para detectar que el código demostrativo envejeció? Sin un mecanismo, la vigencia del material queda sin métrica.]
