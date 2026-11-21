# Demos por convención: layout plano y nombres en inglés

Reescribir los tres demos para que sean el ejemplo canónico de las convenciones del framework:
modelo en `app/Models/<Entidad>.php`, controlador en `app/Http/Controllers/<Entidad>Controller.php`,
tabla derivada por Eloquent (sin `$table` redundante), rutas y vistas en inglés. El tutorial queda en
español a propósito: ahí el lector paga el precio de apartarse de la convención escribiendo `$table`.

## Specs

- **S1**: los tres demos (`tasks`, `contacts`, `notas`→`notes`) usan layout plano: `app/Models/<X>.php`
  y `app/Http/Controllers/<X>Controller.php`, no `app/<Demo>/…`.
- **S2**: nombres técnicos en inglés: `notes`, `Note`, `/notes`, `pages/notes/`, `tests/Notes/`,
  migración `910_create_notes_table.php` con clase `CreateNotesTable`.
- **S3**: la tabla se deriva de Eloquent; `$table` sólo se declara cuando la tabla se aparta de la
  convención. Hoy `Contact` y `Nota` lo declaran sin necesidad: se retira.
- **S4**: el tutorial sigue en español (`notas`, `titulo`, `cuerpo`, `/notas`) y lleva `$table = 'notas'`
  explícito como ejemplo del costo. Al no compartir tabla ni clase con `notes`, tutorial y demo dejan de
  ser incompatibles.
- **S5**: instalar, remover y el round-trip siguen funcionando: manifiesto, backups y rollback intactos.
- **S6**: documentación coherente: `AGENTS.md`, guía (cap. 23 y árboles), tutorial y comandos
  `demo:<nombre>:*` con los nombres nuevos.

## Tasks

- T1 — Mapa de referencias y supuestos del instalador (exploración).
- T2 — Demo `notes` (10 archivos): renombrar y aplanar. Cubre S1, S2, S3.
- T3 — Demo `tasks` (25 archivos): aplanar. Cubre S1, S3.
- T4 — Demo `contacts` (16 archivos): aplanar y retirar `$table`. Cubre S1, S3.
- T5 — Instalador y tests: `scripts/demo.php`, `DemoInstallerTest`, matriz de demos. Cubre S5.
- T6 — Documentación y tutorial. Cubre S4, S6.
- T7 — Gates, round-trip de los tres demos y verificación independiente.

## Log

- L1 (usuario): «necesito entonces, hacer respetar las convenciones, y no es que te ste pidiendo otro
  arnes, quiero reescribir los demos para que usen migraciones, rutas, modelos, controladores, vistas
  nombradas en inglés... asi como el tutorial... esto para que de forma transparente las cosas funcionen
  a traves de las convenciones de ser posible, y cuando no, sea el usuario quien de forma explicita,
  decida cambiarlas, pero pagando el precio, como tener que setear a mano las tablas, y demas cosas
  enconsecuencia...».
- L2 (usuario, alcance): los 3 demos, plano + inglés.
- L3 (usuario, tutorial): queda en español, como ejemplo de override.
- L4 (agente): inventario inicial — tasks 25 archivos, contacts 16, notas 10 (51). `demo.php` tiene un
  mapa `'<demo>' => ['tables' => [...]]`; `Contact` y `Nota` declaran `$table` redundante.
- L5 (agente, T2): demo `notas` reescrito como `notes`, de punta a punta y con layout plano. Los 10
  archivos se movieron con `git mv` (historial preservado): `app/Notas/Models/Nota.php` →
  `app/Models/Note.php` (sin `$table`: Eloquent deriva `notes`), `app/Notas/Http/NotaController.php` →
  `app/Http/Controllers/NoteController.php`, `routes/notas.php` → `routes/notes.php` (grupo `/notes`),
  `config/navigation/notas.php` → `config/navigation/notes.php` (`key` y `href` en inglés, etiqueta
  visible «Notas»), `database/migrations/notas/910_create_notas_table.php` →
  `database/migrations/notes/910_create_notes_table.php` (`CreateNotesTable`, tabla `notes`, banda 910
  intacta), `database/seeds/notas/NotaSeeder.php` → `database/seeds/notes/NoteSeeder.php` (el
  `require_once` sigue a tres niveles), `resources/views/pages/notas/*` → `pages/notes/*` y
  `tests/Notas/Http/NotaTest.php` → `tests/Notes/Http/NoteTest.php`. Ids de modal y del panel en inglés
  (`note-create-modal`, `note-edit-modal`, `note-delete-<id>`, `#notes-panel`) y claves de contexto
  `notes`/`note`. Las columnas `titulo`/`cuerpo` y los textos visibles siguen en castellano: S2 enumera
  qué pasa a inglés y S4 deja el idioma del dominio en manos de cada app.
- L6 (agente, T2): instalador y referencias. `scripts/demo.php` mapea `notes` (tabla `notes`, migración
  `database/migrations/notes`, seeder `NoteSeeder`); `composer.json` publica `demo:notes:install` y
  `demo:notes:remove`; la matriz `demo-round-trip` de CI renombra la fila y lista los destinos planos;
  `DemoInstallerTest` usa `notes`, `app/Models/Note.php`, `routes/notes.php`, `notes.json` y `withNotes`;
  `MakeMigrationTest` mueve la banda y el caso «clase que sólo existe en un scaffold» a
  `CreateNotesTable`/`910_create_notes_table.php`.
- L7 (agente, T2): documentación. Tutorial: la presentación del demo y el cierre del capítulo 14 dicen
  que `notes` es inglés + plano y que ya no comparte tabla ni clase con la entidad del recorrido, así que
  **convive**; se corrigió también «El aviso del scaffold» (el choque `CreateNotasTable` ya no existe) y
  la banda 910. Guía: `notes` en la lista de demos, en la banda y en el snippet de `composer.json` (ese
  fence no lo cubre `docs:sync`: `composer.json` no lleva barra y `CodeBlocks` no lo resuelve). `AGENTS.md`,
  `README.md` y `DESIGN.md` con los nombres nuevos.
- L8 (agente, T2): verificación observada: lint OK; docs sync/check OK (55 copias); phpstan OK, también
  con el demo instalado como corre CI; suite base OK (215 tests) y con el demo instalado OK (227 tests,
  con el test del demo y el de numeración); install/remove real sobre
  `storage/cache/notes-probe.sqlite` (10 archivos, `NoteSeeder: 12 notas demo.`, remove sin residuos).
- L9 (agente, T2, para las otras unidades): queda pendiente `database/seeds/DatabaseSeeder.php`, que sigue
  mirando `database/seeds/notas/NotaSeeder.php` y por eso `seed:run -s DatabaseSeeder` ya no siembra el
  demo (el install sí: Phinx corre `NoteSeeder` por clase). Está fuera de las superficies de T2. Siguen
  también `app/Tasks` y `app/Contacts` (T3/T4) y el supuesto `app/Tasks` de `scripts/phpstan.php`.
  Referencias viejas a `notas` que quedan a propósito: los registros históricos
  `odd/tasks/{framework-comments,tutorial-walkthrough,tutorial-walkthrough-findings,view-organization}.md`
  y la copia del `DatabaseSeeder` en la guía (misma razón que el ítem pendiente de arriba).
