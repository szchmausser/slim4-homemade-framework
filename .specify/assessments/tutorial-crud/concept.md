# Concept: recorrido corto para la primera entidad

- **Slug**: tutorial-crud
- **Created**: 2026-11-11
- **Recommended option**: B — Camino canónico con verificación por paso

## Options

### Option A — Camino mínimo, sin las partes densas

- **Sketch**: un recorrido que lleva al lector de un clon recién hecho a una entidad propia que se
  lista y se da de alta, evitando deliberadamente lo que más peso tiene en este framework: sin HTMX
  (navegación clásica por formularios), sin paginación, sin tests y sin avisos flotantes. Cada paso
  se comprueba recargando una página y viendo el resultado.
- **Appetite**: small (días). El solicitante quitó el largo y el tiempo de lectura como restricción,
  así que el presupuesto aquí mide esfuerzo de autoría, no tamaño del artefacto.
- **Trade-offs**: gana por velocidad, superficie mínima y casi nulo costo de envejecimiento; pierde
  por fidelidad — enseña un framework que este repositorio no usa. La convención vigente pide
  paginación del servidor y cero `get()` pelado, y la constitución ratificada exige que todo CRUD
  traiga al menos tres casos de test. Un recorrido sin tests enseña contra las reglas del propio
  proyecto, y el lector descubre el idioma real solo en su segundo intento.
- **Rabbit holes**: la omisión de HTMX obliga a reescribir el recorrido cuando el lector choque con
  el shell real; elegir un nombre de entidad que colisione con los demos (`tasks`, `contacts`);
  que «mínimo» derive en explicar el framework desde cero, o sea en la guía otra vez.

### Option B — Camino canónico con verificación por paso

- **Sketch**: un recorrido que lleva al lector desde un clon hasta una entidad propia funcionando, pasando primero por el arranque mínimo de la app y una sesión iniciada —sin la cual no puede ver ninguna ruta protegida—,
  escrita por él y no copiada de un demo, siguiendo el idioma que el repositorio ya usa: listado en
  la región de la página con intercambio HTMX, avisos que aparecen en el lugar correcto, paginación
  del servidor, protección de los formularios y una suite mínima de tests de la entidad. Explica cada pieza
  reutilizable en el momento en que aparece —para qué sirve, cuándo usarla y qué se evita con
  ella— y deja el patrón lo bastante explícito como para que el lector lo repita con su propia
  entidad. Cada paso
  es atómico y declara cómo comprobarlo: una ruta que responde, una fila que persiste, una
  validación que rechaza, un test que pasa.
- **Appetite**: medium (semanas). Es el único que se lleva bien con el pedido de pasos atómicos,
  bien definidos y detallados: la granularidad es el producto, no un efecto secundario.
- **Trade-offs**: gana por fidelidad al idioma del repo, por satisfacer la guarda de la constitución
  (toda regla con su verificación) y porque la comprobación por paso es, a la vez, la respuesta a
  «qué reemplaza al gate». Pierde por tamaño y por superficie de deriva: es el material que más
  puede envejecer sin que nada lo detecte. Además coincide temáticamente con el capítulo 16 en la
  parte de listas y paginación, aunque no en lo que el capítulo no tiene: el orden de creación.
- **Rabbit holes**: tres convenciones densas son las que más fácil se desbordan — el doble
  transporte de CSRF, la paginación reutilizable y el ciclo de avisos HTMX. También el riesgo de
  que «explicar cada paso» derive en un curso del framework (prohibido por los non-goals), y el de
  que la verificación por paso se convierta en escribir y mantener una suite propia del tutorial.

### Option C — No crear artefacto nuevo

- **Sketch**: dejar el camino como está y responder al problema con lo que ya existe: instalar un
  demo, leer su código y usar el capítulo 16 como referencia de la capa de presentación.
- **Appetite**: small (días), limitado a redactar una guía de entrada breve que ordene esas tres
  piezas existentes.
- **Trade-offs**: gana por costo cero de mantenimiento y por no agregar material sin verificación;
  pierde porque no resuelve el problema definido —no hay orden de creación, que es justo lo que
  falta— y deja al lector nuevo desarmando 24 archivos para reconstruirlo.
- **Rabbit holes**: que la «guía de entrada breve» crezca hasta ser el tutorial que se quiso evitar;
  que la numeración de una entidad nueva quede librada a la improvisación del lector.

## Recommendation

**B**, por tres razones atadas a los goals y no al gusto: es el único que entrega un orden de
creación —lo que ninguna pieza existente tiene—, es el único que puede hacer verificable cada paso
sin depender del gate, y es el único que respeta la guarda de la constitución en lugar de enseñar
contra ella.

Con dos advertencias que `decide` tiene que pesar y que no se resuelven aquí:

1. **La evidencia en contra sigue en pie.** No hay señal de demanda observada (`research.md`), y el
   material queda sin verificación automática. B no las elimina: las compensa con verificación por
   paso, y eso es una mitigación, no una prueba.
2. **B es el más caro de los tres** y el que más lento paga. Si la demanda resulta ser n=1 y el
   mantenimiento recae siempre en la misma persona, A es la opción honesta por costo, aun sabiendo
   que enseña un idioma que el repo no usa.

No recomiendo C: no resuelve el problema y su versión mínima es el primer paso de B.

## Out of Scope (for the recommended option)

- Construir el framework: instalación de PHP/Termux, Composer, contenedor de dependencias, middlewares, auth, assets. Las piezas reutilizables se explican para
  usarlas, no para modificarlas ni reimplementarlas. El arranque de la app hasta una sesión iniciada
  está **dentro**: es el estado desde el que se puede ver algo de lo que se construye.
- Despliegue, exposición pública y backup.
- La tabla avanzada en modo remoto y cualquier variante de paginación del cliente.
- El flujo de trabajo de SDD y sus comandos.
- Modificar `docs/guia-stack-php-termux.md`, su capítulo 16, el README o los demos.
- Entrar en el alcance de `docs:check`, `lint` o PHPStan.

## Assumptions to Validate

- Un nombre de entidad nuevo, distinto de `tasks` y `contacts`, es aceptable para el dueño del repo.
- El lector puede ejecutar Composer, las migraciones y el servidor de desarrollo; el recorrido no
  enseña a instalarlos.
- La verificación manual por paso, más tres tests de la entidad, alcanza como sustituto del gate.
  Si no alcanza, el concepto se cae y hay que volver a A o a C.
- El lector puede repetir el patrón con una segunda entidad sin volver a leer el recorrido
  completo. Si no puede, el material es un caso único y no un camino.
- El demo de tareas sigue estable: el recorrido lo usa como contraste, no como base.
- Las convenciones vigentes (constitución v1.0.0 y `DESIGN.md`) son la autoridad; un recorrido que
  las contradiga está mal, no es una alternativa válida.
