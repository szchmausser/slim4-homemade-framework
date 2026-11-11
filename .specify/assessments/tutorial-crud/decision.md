# Decision: recorrido corto para la primera entidad

- **Slug**: tutorial-crud
- **Decided**: 2026-11-11
- **Verdict**: go
- **Artifacts reviewed**: intake.md | research.md | problem.md | concept.md

## Scorecard

| Criterion | Rating | Justification |
|-----------|--------|---------------|
| Problem validity | strong | El hueco está medido, no supuesto: en el capítulo 16 `Task.php` y `migrations/tasks` aparecen 0 veces y el resto del backend una sola vez de pasada; es decir, no existe material con orden de creación en ningún lugar del repo. Y el producto declara como promesa «punto de partida rápido y mantenible» (`.ai/steering/product.md:11`). |
| Evidence strength | adequate | Los hechos estructurales son medidos y citados (1212 líneas de capítulo monolítico, 4097 líneas de scaffolds, alcances de los tres gates). La demanda, en cambio, es `unknown`: no hay issues, tickets ni telemetría, y la única señal es el pedido en sesión (n=1). Se acepta el riesgo porque el costo de equivocarse es un documento que nadie usa — sin impacto en runtime, en esquema ni en la suite. |
| Value vs. inaction | adequate | Sin artefacto, el lector nuevo sigue eligiendo entre 1212 líneas monolíticas, desarmar 24 archivos o improvisar. El valor es real pero acotado: no hay medición de cuántas personas llegan a ese punto, y el costo de inacción es una inferencia desde la estructura del material. |
| Feasibility / appetite | strong | Un autor, sin dependencias nuevas, sin código de producción y sin tocar el esquema; `medium` (semanas) es alcanzable porque el solicitante quitó largo y tiempo de lectura como restricciones. Los tres rabbit holes (CSRF en dos transportes, paginación reutilizable, ciclo de avisos HTMX) son convenciones ya documentadas, no investigación abierta. |
| Strategic fit | strong | Se alinea con la promesa del producto y con la constitución v1.0.0: el recorrido canónico incluye tests y verificación por paso, así que no enseña contra el principio V. No toca el sistema visual ni la arquitectura. |
| Risk posture | adequate | El riesgo principal está identificado y tiene mitigación concreta: fuera de `docs:check`, `lint` y PHPStan, la deriva no se detecta, y la única defensa es la verificación por paso más los tres tests de la entidad. El riesgo secundario —duplicar temáticamente el capítulo 16— queda acotado porque ese capítulo no documenta creación. Riesgo residual: si el demo cambia, el contraste envejece en silencio. |

## Verdict & Rationale

**`go`.** La validez del problema es la más fuerte de todo el assessment porque no descansa en
opinión: el repositorio no tiene, en ninguna forma, un orden de creación de entidad, y eso se puede
contar. La opción B es la única que llena ese hueco sin contradecir lo que el proyecto ya exige.

**Por qué no `needs-clarification`:** el único criterio por debajo de `adequate` es `evidence
strength`, y su debilidad viene de una incógnita que **no es respondible con los medios
disponibles** — no hay issues ni telemetría que consultar, así que devolverla a `research` sería un
bucle sin salida. La regla del pipeline existe para frenar construcción caras sobre entusiasmo; acá
el techo de pérdida es un documento que nadie lee, sin tocar código de producción ni esquema. Con
dano acotado y reversible, el desconocimiento sobre la demanda no justifica detener.

**El `unknown` que queda, declarado y no maquillado:** si alguien además del solicitante necesita
esto, y con qué frecuencia. No hay dato. Si en el futuro aparece señal de uso, este veredicto se
revisa; mientras tanto, el artefacto se sostiene por su valor de camino y por su costo bajo, no por
demanda demostrada.

**El riesgo que `specify` tiene que convertir en requisito, no en buena intención:** sin gate, la
vigencia del código demostrativo depende de que cada paso traiga su propia comprobación. Si eso se
cae, el concepto pierde su única defensa.

## If go — Handoff to `/speckit.specify`

- **Problem**: quien adopta el framework no tiene un recorrido corto y ordenado de «cloné el repo» a «mi entidad funciona»; el material existente construye el framework o entrega código terminado, y ninguno muestra el orden de creación.
- **Chosen approach**: Opción B — camino canónico con verificación por paso: el lector escribe su propia entidad (no la copia de un demo) siguiendo el idioma del repositorio (intercambio HTMX, avisos en su lugar, paginación del servidor, formularios protegidos) con comprobación explícita en cada paso, más la suite mínima de la entidad. Explica cada pieza reutilizable cuando aparece y deja el patrón explícito como para repetirlo con una entidad propia.
- **In scope / out of scope**: dentro: la creación de una entidad propia de punta a punta, con sus comprobaciones y tests, en un artefacto nuevo fuera de `docs/`. Fuera: construir el framework (Termux, Composer, contenedor de dependencias, middlewares, auth, assets), despliegue y exposición pública, la tabla avanzada en modo remoto, el flujo SDD, y cualquier modificación a `docs/guia-stack-php-termux.md`, al README o a los demos. Borde explícito: explicar una pieza reutilizable para usarla está **dentro**; construirla, modificarla o reimplementarla, fuera.
- **Success metrics**: el lector llega a su entidad funcionando sin abrir `framework/scaffolds/` ni recorrer la guía entera; cada paso deja un estado observable (ruta que responde, fila persistida, validación que rechaza, test que pasa); el material no rompe `composer verify` al agregarse; el lector repite el patrón con una segunda entidad propia y nombra las piezas reutilizables que usó.
- **Carried-forward open questions**:
  - [NEEDS CLARIFICATION: nombre de la entidad de ejemplo, libre de colisión con `tasks` y `contacts`.]
  - [NEEDS CLARIFICATION: definición exacta de «funcionando» al final del recorrido: ruta que responde, fila persistida o test en verde — es lo que hace medible el primer goal.]
  - [NEEDS CLARIFICATION: si los tests se escriben dentro de los pasos o en un cierre aparte.]
  - [NEEDS CLARIFICATION: qué comprobación concreta reemplaza a `docs:check` en cada paso, y cómo se evita que esa comprobación sea prosa aspiracional.]
  - [Aceptado como supuesto, no bloqueante: demanda observada. No hay dato disponible; el veredicto no depende de ella.]
