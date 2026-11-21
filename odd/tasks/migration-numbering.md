# Numeración contigua de migraciones

Renumerar el espacio de migraciones (base + scaffolds) a una secuencia sin huecos desde 1, y
mecanizar la asignación del número para que las futuras cumplan la misma regla.

## Specs

- **S1**: «quiero que las migraciones empiecen con n_migracion, donde la de creacion de tabla users,
  deberia empezar en 1_create_users_table.... hasta n_migraciones posteriores».
- **S2**: «esto incluye renombrar las actuales y actualizar sus referencias en el tutoriual».
- **S3**: «y tambien las que seran creadas posteriormente, respeten esta numeracion tambien» →
  la asignación deja de ser manual: `composer make:migration <Nombre>` calcula el siguiente libre.
- **S4**: padding de 3 dígitos (`001_`), no `1_`. Motivo: Phinx ordena por `(int)` del prefijo, pero
  `ls`, `git` y los árboles de la guía ordenan lexicográficamente, y `10_` cae antes de `2_`; el
  proyecto ya documenta la regla de 3 dígitos en `docs/guia-stack-php-termux.md`. Es la única
  desviación literal de S1 y es reversible con un rename.
- **S5**: el renumerado preserva las dependencias del esquema: índice de `login_attempts` después de
  `login_attempts`, `add_status`/`drop_done` después de `create_tasks_table`.
- **S6**: la app usa el rango bajo contiguo desde 001; los demos viven en una **banda reservada**
  (`tasks` 900-909, `notas` 910-919, `contacts` 920-929) para que el crecimiento de la app no deje
  huecos en `ls database/migrations/`. Sin banda, la migración del tutorial volvería a abrir un salto.
- **S7**: el número asignado nunca choca con un scaffold aunque el demo no esté instalado: el espacio
  de versiones es `database/migrations/**` ∪ `framework/scaffolds/*/database/migrations/**`.

## Tasks

- T1 — Test guardián de numeración (`tests/Support/MigrationNumberingTest.php`): RED con los huecos
  actuales. Cubre S1, S5, S6, S7.
- T2 — Renombrar las 10 migraciones (5 base, 5 scaffolds) a 001-010. Cubre S1, S5.
- T3 — Actualizar `DemoInstallerTest` a las nuevas versiones; suite en verde. Cubre S1.
- T4 — `composer make:migration` + `scripts/make-migration.php` + tests. Cubre S3, S4, S7.
- T5 — Referencias: tutorial, guía, docblocks de los 3 modelos de scaffold, `AGENTS.md`,
  `composer docs:sync`. Cubre S2.
- T6 — Gates (`lint`, `docs:check`, `phpstan`, `test`), `phinx migrate` sobre base scratch y
  verificación independiente.

## Log

- L1 (usuario): «pregunto... hice el paso 1 y estoy por abordar el paso 2, pero si bien mencionas que
  la numeracion es secuencial, estoy notando que no esta siendo secuencial, hay saltos, mira esta
  salida del terminal: [...] 002_create_users_table.php / 003_create_remember_tokens_table.php /
  006_login_attempts.php / 009_add_login_attempts_index.php /
  010_add_rotated_at_to_remember_tokens.php».
- L2 (usuario): «pero aprovecha a renimbrar tambien las migraciones actuales y futuras del tutorial
  para que tengan un nombrado incremental y en orden tambien».
- L3 (usuario): «quiero que las migraciones empiecen con n_migracion, donde la de creacion de tabla
  users, deberia empezar en 1_create_users_table.... hasta n_migraciones posteriores, esto incluye
  renombrar las actuales y actualizar sus referencias en el tutoriual, y tambien las que seran creadas
  posteriormente, respeten esta numeracion tambien».
- L4 (usuario, decisión previa): comando `make:migration` en lugar de gate en `lint`.
- L5 (agente): mapeo aplicado. Base: 001 users, 002 remember_tokens, 003 login_attempts,
  004 add_login_attempts_index, 005 add_rotated_at. `tasks`: 900 create, 901 add_status, 902 drop_done.
  `notas`: 910 create. `contacts`: 920 create.
- L6 (agente): costo conocido y aceptado — `phinxlog` de cualquier clon con el esquema viejo queda
  desalineado; la recuperación es `migrate:fresh` + seed. Se documenta en la guía.
- L7 (agente): L6 estaba incompleta. `migrate:fresh` no alcanza si el clon tiene el demo instalado
  antes de la renumeración: la copia vieja (`database/migrations/<demo>/001_…`, `004_…`, `005_…`)
  declara versiones que la app ya usa, así que `phinx migrate` y `scripts/migrate-fresh.php` abortan
  con `Duplicate migration` y `demo:tasks:remove` ya no la reconoce. El orden es borrar esa copia y su
  manifiesto (`storage/demo-state/<demo>.json`) primero, y recién después recrear la base migrando y
  sembrando el admin. Queda documentado en `docs/guia-stack-php-termux.md`, cap. 11.
- L8 (verificación independiente): tres defectos reales que las pruebas del autor no veían. (1) el
  guardián contaba dos veces la misma migración cuando un demo estaba instalado, y rompía el flujo
  «instalar demo + `composer test`» que exige `AGENTS.md`; (2) `MigrationUpgradeTest` del scaffold
  migraba a la versión 4, que ya no es de tasks; (3) referencias vivas a los números viejos en
  `README.md`, en un paso del tutorial y en cuatro comentarios de migración. Corregidos y
  reverificados: suite base 207/1494, con tasks instalado 258/1772, `lint`/`docs:check`/`phpstan` verdes.
- L9 (agente): L7 se excedía al mandar borrar el manifiesto. Sin manifiesto, `demo:tasks:install`
  aborta con «ya tiene archivos instalados» y hace falta `-- --force`; conservándolo, actualiza los
  archivos sin más. La guía quedó con el manifiesto intacto y `--force` sólo como salida de emergencia.
- L10 (agente): el test de copias instaladas es diagnóstico, no guardián. En la suite normal el que
  aborta primero es `tests/bootstrap.php`, que migra todo y muere con «Duplicate migration»; el test
  sólo da el mensaje legible cuando se corre sin ese bootstrap. Queda dicho en su comentario para no
  venderlo como cobertura que no da.
