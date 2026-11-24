# Primitivos de formato por locale (fechas y cantidades)

Objetivo: que el framework resuelva el formato de fechas y cantidades según el locale activo, con datos
de locale en código y una API de «kinds», para que ninguna vista ni controlador escriba un patrón ni un
separador. Base `3001a95`. Entrega: `single-pr`.

## Specs

- **S1**: «si bien ahora no hay usos que lo justifiquen, la idea es preparar los patrones para la
  regionalizacion de fechas y cantidades, ya que es muy facil que surgan diversos patrones regados por la
  app si no definimos esos primitivos, ya el usuario a nivel de la app decidira si quiere mostrar fechas
  con hora o sin hora, cantidades con 1 o 3 decimales, pero el patron para que el decida, ya estaria
  implementado, y evitariamos que cada agente de IA cree su propia solucion al vuelo si definimos el
  patron a seguir desde el principio».
- **S2 (división de responsabilidades)**: el framework posee el primitivo (separadores y orden según
  idioma) y los datos de locale; la app elige **qué** mostrar y con cuántos decimales. El patrón no es
  traducible y no vive en `lang/`: un traductor editando un catálogo no puede romper el formato.
- **S3 (API)**: `Framework\I18n\Format` con dos métodos estáticos:
  `number(int|float $value, int $decimals = 0): string` y
  `date(\DateTimeInterface|string $value, string $kind = 'numeric'): string`.
  Kinds admitidos: `numeric`, `numeric_time`, `time`. Sin `ext-intl`, sin dependencias nuevas; el motor es
  `number_format()` y `DateTimeInterface::format()`.
- **S4 (datos y fallback)**: tabla de datos de locale en código para `es` y `en`. Un locale sin entrada
  cae al locale base de la app (`APP_LOCALE`) y, si ese tampoco tiene entrada, a `es`, sin lanzar.
  Agregar un idioma nuevo con formato propio es agregar una entrada a esa tabla; queda documentado en la
  guía.
- **S5 (Twig)**: dos filtros en la extensión de i18n existente: `|num` y `|localdate`. Uso:
  `{{ pagination.total|num }}`, `{{ task.completed_at|localdate('numeric_time') }}`.
- **S6 (superficies)**: la paginación formatea sus tres contadores con `|num`; el demo de tareas deja de
  usar `|date('d/m/Y H:i')` y usa `|localdate('numeric_time')`. Ninguna vista escribe patrones ni
  separadores.
- **S7 (docs)**: la guía deja de afirmar que fechas y cantidades no son por locale (§15.3.2, dos
  lugares); describe el primitivo, qué queda fuera (nombres de mes, plurales, moneda, zonas horarias) y
  cómo se agrega un locale. `AGENTS.md` suma la regla de uso. El tutorial, una línea en 6.7 que apunte al
  primitivo sin enseñarlo.
- **S8 (ciclo horario)**: «deberias tener la primitiva para manejar a 12h y 24h, estableciendo por
  defecto el de 12h, y que el usuario pueda cambiar el por defecto si es que le gusta o conviene mas usar
  el formato 24h». Implementación: `LocaleData` declara `hour_cycle` (`'12'`|`'24'`) por locale y el
  **valor por defecto del campo es `'12'`** cuando el locale no lo declara; `es` declara `'24'` porque es
  su uso natural y forzarlo a 12 haría que el español muestre «3:04 PM». La app cambia el ciclo global con
  `LocaleData::useHourCycle('24')` (o `'12'`) una vez en el bootstrap; un valor inválido lanza. Los
  patrones de hora se derivan del ciclo (`12` → `g:i A`, `24` → `H:i`) en lugar de duplicarse por kind.

## Tasks

- **T1** — Primitivo y superficies (S2-S6): `Format` + tabla de datos, `Trans::locale()` si hace falta para
  leer el locale activo, filtros en `TranslationExtension`, paginación y fecha de tareas, y
  `tests/Support/FormatTest.php` con números y fechas en `es`/`en`, decimales, negativos, fallback de
  locale sin datos, los dos filtros y la paginación con `1234`. Ruta: worker. Commit: `f68986c`
- **T2** — Documentación (S7): guía §15.3.2 (los dos lugares), `AGENTS.md`, tutorial 6.7. Ruta: worker.
  Commit: `5bce60e`
- **T3** — Verificación: cerrado. `composer verify` verde (309 tests / 5328 assertions, PHPStan sin
  errores, docs OK, migrate:fresh OK); round-trips tasks 364 tests y notes 324, ambos con `remove` limpio.
  Verificación independiente (`gentle-ai-verify`) sobre copia en scratch: siete items, seis OK y un
  CONCERN de cobertura, ya cerrado con cuatro casos más en `FormatTest` (paginación en `en` con `1,234`,
  mensaje completo de la excepción, rama `APP_LOCALE=pt` → `es`, y que los filtros no marcan la salida
  como segura). Total final: 312 tests / 5337 assertions sin demos. Ruta: padre + verificador. Commit:
  `f68986c` (código y tests) y `5bce60e` (documentación).
- **L5**: S8 cerrado en `99391f8`. Forma de la tabla: `es` declara `hour_cycle => '24'`, `en` **omite** el
  campo para que el default documentado (`'12'`) sea real y testeable por la API pública, sin locales
  especulativos. Precedencia: override de la app > campo del locale > `'12'`. Patrones derivados
  (`12` → `g:i A`, `24` → `H:i`). Tests: 40 en `FormatTest` (317 / 5352 sin demos; 372 con tasks; 332 con
  notes).
- **L6**: límite conocido y aceptado: los valores de `hour_cycle` de la tabla son datos de código y solo
  `useHourCycle()` valida su entrada; un typo en una entrada futura (p. ej. `'25'`) fallaría al renderizar
  esa fecha en lugar de normalizarse. Se acepta porque la tabla tiene dos entradas y el fallo es inmediato
  y visible; si crece, se valida al resolver el ciclo.
- **T4** — Ciclo horario (S8): `hour_cycle` por locale con `'12'` como default del campo, `es` en `'24'`,
  `LocaleData::useHourCycle()` para forzar el ciclo global, patrones de hora derivados del ciclo, tests de
  los dos ciclos en los dos idiomas, del override, del valor inválido y del default. La guía documenta el
  knob. Ruta: worker. Commit: `99391f8`

## Log

- **L1**: «anteriormente me dijiste que la regionalizacion no estaba aplicada a numeros y fechas... como
  podemos resolverlo de una forma eficiente, sin sobreingenieria y manteniendonos en lo posible aplicando
  DRY, YAGNI y KISS?»
- **L2**: «estamos intentando meter manos dond no deberiamos? o es correcto que lo hagamos? si lo
  hicieramos? esta es la mejor implementacion posible? o hay alternativas de implementacion mejores? quien
  se encarga de formatear numeros/cantidades y fechas? frontend? backend? ambos?»
- **L3**: estado medido antes del cambio: `number_format` no aparece en el repo; los tres contadores de la
  paginación salen crudos; hay **una** fecha visible
  (`framework/scaffolds/tasks/resources/views/pages/tasks/_list.twig:95`, `|date('d/m/Y H:i')`); la guía
  declara la carencia en `docs/guia-stack-php-termux.md:4415` y `:4451` por `ext-intl` no garantizada en
  Termux.
- **L4**: decisiones. (a) Se descarta poner los patrones en `lang/`: un separador no es texto y una
  edición de traducción no debe poder romper el formato (S2). (b) Se descarta `ext-intl` y el doble camino
  «ICU si existe»: dos comportamientos para la misma salida. (c) La API expone **kinds**, no patrones
  libres, para que la app elija la forma y el framework el idioma. (d) Formatea el backend lo que se
  renderiza en el servidor; los valores de formulario y las APIs quedan crudos (ISO y `.`). (e) El
  fallback de un locale sin datos es el locale base, sin lanzar.
