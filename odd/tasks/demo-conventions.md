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
- **S4** (corregida en L14): el tutorial sigue en español (`notas`, `titulo`, `cuerpo`, `/notas`) y **no**
  declara `$table`, porque la entidad del recorrido cae justo en la convención de Eloquent
  (`Nota` → `notas`, igual que `Note` → `notes`): declararlo sería enseñar una regla equivocada. El costo
  de apartarse de la convención lo explica el propio tutorial con `Ciudad` → `ciudads` (el pluralizador
  es inglés) y lo ejercita `composer make:model`, que escribe `$table` sólo cuando la tabla pedida difiere
  de la inferida. Al no compartir tabla ni clase con `notes`, tutorial y demo conviven.
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
- L10 (agente, T3/T4): `tasks` y `contacts` aplanados de punta a punta. Cuatro archivos movidos (sin
  commit): `app/Tasks/Models/Task.php` → `app/Models/Task.php` (namespace `App\Models`),
  `app/Tasks/Http/TaskController.php` → `app/Http/Controllers/TaskController.php`, y lo mismo con
  `app/Contacts/{Models,Http}`. Dentro del scaffold la ruta relativa es la del destino
  (`framework/scaffolds/tasks/app/Models/Task.php`), así que el instalador no cambió: sigue copiando
  archivo por archivo. Rutas: `/tareas` → `/tasks` y `/contactos` → `/contacts`, incluido el endpoint de
  datos (`/contactos/datos` → `/contacts/data`), los `url()`/`Url::path()`/`redirect()` y los `hx-*`; el
  sidebar ya se activa con `'tasks'` (antes `'tareas'`) y el `href`/`key` de `config/navigation/` están en
  inglés. `Contact` perdió `protected $table = 'contacts'`: Eloquent lo deriva de la clase, como `Note` y
  `Task`. Las migraciones siguen en `database/migrations/<demo>/` (bandas 900/920): es la única excepción
  al layout plano. Se conservaron a propósito los `id` de HTML (`#tareas-panel`, `#lista-tareas`,
  `#alta-tarea`), las etiquetas visibles («Tareas», «Contactos», `label: 'tareas'` del paginador) y las
  columnas `title`/`status`/`completed_at`/`name`/`email`/`phone`: S2 fija qué pasa a inglés y los `id`
  no están en esa lista; cambiarlos obligaba a tocar aserciones que no son de ruta.
- L11 (agente, T5): infraestructura. `scripts/phpstan.php` detectaba el demo instalado por `app/Tasks`,
  que con layout plano no existe nunca: con el demo instalado sumaba igual
  `framework/scaffolds/tasks/app` y la clase quedaba duplicada. Ahora mira `app/Models/Task.php` (la ruta
  que analiza `app/`), así que instalado no analiza el scaffold y desinstalado sí. No se agregó
  `contacts` al análisis: fuera del alcance de esta unidad y el archivo no lo listaba antes.
  `removeEmptyParents()` protege además `app/Models`, `app/Http`, `app/Http/Controllers` y
  `resources/views/pages`: sin eso, quitar la última demo borraba los destinos por convención que ahora
  usa el layout plano. La matriz `demo-round-trip` lista los destinos planos de `tasks` y `contacts`
  (y corrige `resources/views/{tasks,contacts}` por `resources/views/pages/...`, que además hoy no
  existen) y «Assert no demo residue» los cubre archivo por archivo. `DemoInstallerTest` suma tres casos:
  install plano de cada demo y remove que conserva los tres directorios compartidos. En `tests/Support`
  las rutas viejas pasaron a `/tasks`/`/contacts` (fixtures de `UrlTest`, `PaginationTest`,
  `ComponentContractTest`, `ControllerViewDefaultsTest`) sin cambiar lo que cada test demuestra.
- L12 (agente, T3/T4/T5 parcial): documentación. `AGENTS.md` ya no dice que los demos agrupan en
  `app/<Demo>/`: explica el layout plano y la excepción de la migración en subdirectorio (banda de
  versiones). `README.md`, `DESIGN.md` y la guía con los destinos, rutas y el endpoint `/contacts/data`;
  en la guía se actualizaron las rutas declaradas en la prosa de los bloques copiados (si no,
  `docs:check` falla) y `composer docs:sync` reescribió las copias. `docs/tutorial-crud.md` no menciona
  esos paths, así que no se tocó. Verificación observada: lint OK; docs sync/check OK (55 copias);
  phpstan OK sin demos y con los dos instalados (la prueba de la detección); suite base OK (218 tests) y
  con los dos demos instalados OK (295 tests); install/remove real sobre `storage/cache/flat-probe.sqlite`
  (25 y 16 archivos, `TaskSeeder: 100 tareas demo.` / `ContactSeeder: 100 contactos demo.`, remove sin
  residuos y sin borrar `app/Models`, `app/Http/Controllers` ni `resources/views/pages`).
- L13 (agente, para la próxima unidad): siguen pendientes `database/seeds/DatabaseSeeder.php` (L9) y las
  referencias viejas que quedan en archivos fuera de estas superficies: `config/container.php:102`,
  `tests/bootstrap.php:38`, `tests/Http/BasePathTest.php:15`, `tests/Http/PaginationTest.php:71-75`
  (`/app/contactos/datos`) y los ejemplos de docblock de `framework/Http/Url.php:36,50`. Son comentarios y
  fixtures, no comportamiento.
- L14 (agente, corrección de la spec): **S4 estaba mal**. Decía que el tutorial lleva `$table = 'notas'`
  explícito como ejemplo del costo. Es falso: `Nota` → `notas` **es** la derivación de Eloquent
  (`Str::snake(Str::pluralStudly('Nota'))`, la misma función que usa `Model::getTable()`), así que
  `app/Models/Nota.php` del tutorial no declara `$table` (`docs/tutorial-crud.md:299`) y `MakeModelTest`
  fija que el generador sólo lo escribe cuando la tabla se aparta de la inferida. Si S4 pedía declararlo,
  mandaba a enseñar una regla equivocada (el mismo `$table` redundante que S3 retira de los demos).
  Verificado: `Str::snake(Str::pluralStudly('Nota'))` → `notas` y `...('Note')` → `notes`; el caso que
  cuesta el override sigue siendo `Ciudad` → `ciudads`, que el tutorial ya explica. S4 queda reescrita para
  decir lo que pasa: el tutorial no declara `$table` y el costo se enseña con `Ciudad`.
  **Historial**: L13 y la S4 original asumían que el tutorial era el «ejemplo del costo»; el ejemplo del
  costo es `Ciudad`, no `Nota`.
- L15 (agente, hallazgos de la verificación): referencias viejas al día. `PRODUCT.md` con `/tasks`,
  `/contacts` y `/contacts/data`; `docs/tutorial-de-uso-spec-kit.md` con
  `app/Http/Controllers/ContactController.php`; `config/container.php` y `tests/bootstrap.php` con
  `/tasks` en sus comentarios (la guía los copia, así que `composer docs:sync` reescribió las copias de los
  capítulos correspondientes); `framework/Http/Url.php` con `/tasks` y `//app/tasks` en los docblocks de
  ejemplo; `tests/Http/BasePathTest.php` y `tests/Http/PaginationTest.php` con `/tasks` y
  `/app/contacts/data`, fixtures que ahora imitan las rutas vigentes sin cambiar lo que cada test
  demuestra. `docs/guia-stack-php-termux.md` lista `notes` en el árbol de scaffolds (decía «Hay tres
  demos» y mostraba dos). Verificación: `composer docs:sync`/`check` OK, `composer lint` OK,
  `composer phpstan` OK, `php vendor/bin/phpunit --filter 'BasePath|Pagination'` OK y la suite completa OK;
  el grep de barrido (`/tareas|/contactos|App\\Tasks|App\\Contacts|App\\Notas`) sobre `README.md`,
  `AGENTS.md`, `PRODUCT.md`, `DESIGN.md`, `docs`, `specs`, `config`, `tests`, `framework`, `app` y `scripts`
  no devuelve nada. Fuera de esa lista quedan referencias históricas (`.ai/`, `odd/`, `.gsd/`,
  `.impeccable/`) y una viva que esta unidad no puede tocar: el comentario de `.env.example:34`.
