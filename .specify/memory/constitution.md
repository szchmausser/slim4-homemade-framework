# Constitución de Slim 4 Homemade Framework

Este documento fija las reglas que ya son verdad en este repositorio. No describe aspiraciones: si
una regla no se puede verificar hoy, no va acá. Cuando la constitución y el código difieran, se
corrige el que esté equivocado —y se dice cuál— en el mismo cambio.

## Principios Fundamentales

### I. Termux primero: sin toolchain de compilación (MUST)

Todo el proyecto tiene que instalarse y correr en Termux sobre PHP de 32 bits sin compilar nada
desde C o ASM.

- Las dependencias se instalan con `composer install --prefer-dist --no-scripts`
  (`composer install:termux`) o por los scripts declarados en `composer.json`. Una dependencia que
  exija compilación no entra, aunque sea la solución idiomática.
- El piso de PHP es **8.4.1** y se declara en los tres lugares a la vez: `composer.json`,
  `composer.lock` y el runtime. Un desvío falla en la suite, no en la máquina del usuario.
- Nada de supuestos de 64 bits: sin `int64` propio, sin caches globales pesadas, sin features que
  asuman `PHP_INT_SIZE === 8`.
- PHPStan corre como PHAR dev-only para no sumar una dependencia de Composer ni exigir
  compilación. Esa decisión manda sobre la tentación de agregar extensiones de análisis.

**Rationale:** el usuario objetivo corre en Android ARM 32-bit sin toolchain. Cada regla de arriba
existe porque su violación se manifiesta como un clon que no instala, no como un bug que se nota.

**Cómo se verifica:** `RuntimeRequirementsTest` (manifiesto, lock y runtime alineados),
`composer check:termux`, y el flujo `composer verify`.

### II. Las respuestas HTMX son HTML de región (MUST)

Cuando el disparador es HTMX, el cuerpo de la respuesta es el HTML de la región que se intercambia.

- **Prohibido** contestarle un 303 a HTMX: el navegador no hace swap con un 3xx y el usuario ve una
  pantalla que no se movió. Para ordenar navegación completa se usa `HX-Redirect`; para avisar, los
  headers `HX-Trigger*`.
- El 303 queda reservado a la navegación clásica (formulario sin HTMX, redirect tras éxito).
- El flash viaja **dentro del swap** por `HX-Trigger-After-Swap` (no `HX-Trigger` a secas: el
  momento importa en un ARM de 32 bits). Las pantallas con redirect incorporan los avisos por
  `toast_items`.
- Un fallo de CSRF con HTMX devuelve 400 + `HX-Trigger`; sin HTMX, flash de error y 303 al login.
  Los dos caminos se mantienen juntos o se rompe uno.

**Rationale:** es el contrato que el shell y los tests ya asumen. Cuando se violó (el caso S7 del
demo de contactos), el 303 se comió la pantalla y el aviso.

**Cómo se verifica:** tests HTTP que afirman estado y cuerpo de la región; los tests de CSRF cubren
los dos transportes.

### III. El tema se gobierna con `data-theme` (MUST)

Claro y oscuro dependen de un único mecanismo: el atributo `data-theme` en `<html>`.

- El estado se aplica **antes del primer paint** en los tres layouts (`layouts/app.twig`,
  `layouts/auth.twig`, `errors/error.twig`). La página de error fue la excepción y dejó de serlo.
- **Prohibido `dark:`**: si aparece una utilidad `dark:`, es un error de sistema, no una variante
  estilística. Tampoco hay JS de terceros que cambie tema.
- Sin hex ni `oklch()` sueltos en plantillas: un valor nuevo entra como custom property en el
  `<style>` del layout.
- Los semánticos de daisyUI son fondos, no tintas: los iconos usan los tokens `--ink-*`.

**Rationale:** un solo interruptor es auditable. Cada mecanismo paralelo (clase, atributo, media
query) duplica la superficie de fallo y produce el bug clásico de la pantalla que sale en claro.

**Cómo se verifica:** `LayoutContractTest` (el mecanismo de sincronización de estado es el que se
prueba, no el efecto visual), `ViewClassIntegrityTest` para las clases usadas, y revisión de
`dark:` en el diff.

### IV. Assets vendorizados con SRI, cero CDN en runtime (MUST)

Todo JS y CSS se sirve desde `public/assets/`, con atributos de integridad, y viaja con el repo.

- Ningún recurso se carga de un CDN en tiempo de ejecución. Si el CDN falla, la app sigue andando.
- Sin paso de build, sin bundler, sin `@theme`. Tailwind corre con el browser build vendorizado.
- Una clase de daisyUI usada tiene que existir en el bundle vendorizado. Si el guard falla, se
  corrige la plantilla, **nunca** el guard.
- Un asset que cambia un byte rompe el SRI y deja la app sin JS sin error visible: los assets no se
  editan a mano ni se reformatean.

**Rationale:** confiabilidad y reproducibilidad en un entorno donde no hay red garantizada ni forma
de compilar. El SRI es lo que convierte "está en el repo" en "es el que esperábamos".

**Cómo se verifica:** `ViewClassIntegrityTest`, `LayoutContractTest`, y `composer docs:check` para
las menciones de CDN en la documentación.

### V. Toda regla del sistema entra con su guarda (MUST)

Una regla de la constitución que solo vive en un documento no existe. Entra con algo que falle en
la suite cuando se rompe.

- Cada mecanismo del sistema (esquema, contrato de layout, clases del bundle, piso de PHP, URLs con
  prefijo) tiene un test que verifica el **mecanismo**, no la impresión visual.
- Cuando una guarda falla, se corrige el código o la plantilla. Nunca se afloja el guard, y nunca se
  agrega una baseline de PHPStan: el alcance amplio y la baseline vacía son la señal.
- PHPStan nivel 5 sin baseline. Un error nuevo se arregla en el código.
- **SHOULD:** cada test paga su mantenimiento. El criterio es "si no te da miedo que falle, no lo
  escribas". Un CRUD nuevo trae, como mínimo, tres casos: crea-OK, validación que rechaza, e id
  inexistente que avisa con un mensaje claro (nunca 404 mudo ni 500 silencioso).

**Rationale:** este repo ya pagó el costo de la alternativa: 27 de 56 copias de la guía estaban
viejas y nadie lo veía. Las guardas son lo que hace que la constitución envejezca bien.

**Cómo se verifica:** `composer verify` sobre `tests/Support/*` y los tests de cada dominio.

## Restricciones Técnicas

**Stack fijado.** PHP >= 8.4.1 · Slim 4 · PHP-DI · Eloquent standalone (`illuminate/database`) ·
Phinx · Twig · HTMX · Alpine.js · daisyUI 5 vendorizado · SQLite · respect/validation · Monolog ·
PHPUnit · PHPStan (PHAR). Un framework PHP distinto de Slim 4, un ORM distinto de Eloquent o un
bundler de CSS están fuera de alcance por definición, no por preferencia.

**Propiedad del código.** `framework/` es infraestructura reusable (`Auth`, `Http`, `Validation`,
`Docs`). `app/` es dominio del usuario. `framework/scaffolds/` son demos instalables: la app base no
referencia nada de un demo, y `demo:*:remove` tiene que dejar el repo como estaba (archivos, tablas
y filas de `phinxlog`). `app/Support/` es para helpers propios de la app.

**Esquema.** Migraciones Phinx con nombres **secuenciales** (`004_...`), no timestamps: la
numeración es predecible y evita saturar int32. Los números de demos removidos no se reutilizan.
Las seeds son idempotentes (toleran re-ejecución) y el orden vive en un único seeder. `Framework\Auth`
espera las tablas base `users`, `remember_tokens` y `login_attempts`: si se extrae el framework a
otra app, se replican las migraciones o el contrato.

**URLs.** Toda URL de vista, acción HTMX, asset, redirect y `HX-Push-Url` sale por `url()` en
plantillas o `Framework\Http\Url::path()` en PHP. Un literal raíz-absoluto nuevo deja esa URL fuera
de `APP_BASE_PATH`; es la única regla que hay que recordar al agregar vistas.

**Documentación.** Una copia de un archivo del repo en `docs/` se declara con la ruta en la línea
de arriba y no se edita a mano: `composer docs:sync` la reescribe y `composer docs:check` falla si
difiere. Un fragmento se marca con `excerpt`. `DESIGN.md` describe lo que el código hace; si
divergen, manda el código y se corrige el documento.

**Fuera de alcance.** Registro público, reseteo por email, JWT/OAuth, roles, CDN en runtime,
servidor de base de datos externo, toolchain de compilación.

## Flujo de Desarrollo

**Un solo gate.** `composer verify` encadena lint + docs-check + PHPStan + suite + migración fresh
sobre SQLite temporal. Nada se mergea en rojo, y "casi verde" no es un estado.

**Verificación de migraciones.** Un cambio de esquema se prueba con
`vendor/bin/phinx rollback -t 0 && composer migrate`: la migración tiene que bajar y volver a subir.

**Spec-Driven Development.** Las features pasan por constitución → `specify` → `clarify` → `plan` →
`tasks` → `analyze` → `implement` → `converge`, con artefactos en `specs/NNN-slug/`. Los artefactos
no se editan para que el código pase: si el código se desvía, se corrige el código o se enmienda el
artefacto, explicitando cuál de los dos era el equivocado. Los arreglos puntuales usan el flujo de
bug (`.specify/bugs/<slug>/`) en vez del ciclo completo.

**Cambios a esta constitución.** Un `MUST` solo cambia con aprobación explícita del usuario. Un
principio modificado no reescribe en silencio un artefacto ya aprobado. Si un principio choca con
un requisito aprobado, se detiene el trabajo y se pregunta cuál de las dos fuentes se actualiza.

**Documentación y código viajan juntos.** Un cambio de comportamiento actualiza su documentación en
el mismo commit; `docs:check` es parte del gate, no un extra.

## Governance

Esta constitución reemplaza cualquier práctica en conflicto. Su cumplimiento se revisa en cada
cambio, y el gate automático es `composer verify`.

- **Enmienda:** PR que modifique solo `.specify/memory/constitution.md`, con el Sync Impact Report
  completado y las reglas nuevas respaldadas por evidencia del repo. Un principio nuevo sin guarda
  verificable no entra.
- **Versionado semántico:** MAJOR si se elimina o redefine un principio de forma incompatible;
  MINOR si se agrega un principio o se amplía materialmente una sección; PATCH si es aclaración,
  redacción o corrección no semántica.
- **Fechas** en ISO `YYYY-MM-DD`. `Ratified` es la fecha de adopción original y no se mueve;
  `Last Amended` es la de la última enmienda.
- **Revisión:** toda revisión de código verifica cumplimiento de los cinco principios. Una
  excepción se justifica en el PR y, si es recurrente, se convierte en enmienda en lugar de
  convertirse en costumbre.

**Version**: 1.0.0 | **Ratified**: 2026-11-11 | **Last Amended**: 2026-11-11
