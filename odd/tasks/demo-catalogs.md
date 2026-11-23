# Catálogos de traducción por demo

Objetivo: sacar del catálogo base las claves que solo existen por los demos y hacer que cada demo traiga
su propio archivo de catálogo, instalado y quitado con el resto de sus archivos. Base `87a704f`.
Estrategia de entrega: `single-pr` (el layout de catálogos y sus tests son atómicos).

## Specs

- **S1**: «lo que yo quiero es que el framework base, tenga su archivo de traduccion, pero que al instalar
  demo, cada demo traiga su propio archivo de traduccion, asi , que si no hay demos instalados, el
  proyecto se mantiene limpio, y esas claves de traduccion de los demos, solo existan cuando se instala
  cada demo.»
- **S2 (layout)**: el catálogo base sigue siendo `lang/<locale>.php` (cromo y app). Cada demo aporta
  `lang/<locale>/<demo>.php` desde su scaffold (`framework/scaffolds/<demo>/`), con destino relativo
  idéntico, para que lo copie y lo borre el instalador actual sin código nuevo.
- **S3 (loader)**: `Framework\I18n\TranslatorFactory` carga el archivo base del locale y, además, todos
  los `lang/<locale>/*.php`. `availableLocales()` y `localeNames()` siguen leyendo solo el archivo base:
  el selector del pie no cambia y un locale sin catálogo base no existe.
- **S4 (sin demos)**: con los tres demos desinstalados, los catálogos base no contienen ninguna clave de
  demo: `tasks.*`, `contacts.*`, `notes.*`, `nav.tasks`, `nav.contacts`, `nav.notes`.
- **S5 (home)**: las claves `home.*` quedan en el catálogo base. La portada es del esqueleto y sus tarjetas
  describen demos, pero no son la interfaz de un demo instalado.
- **S6 (tests)**: la referencia byte a byte de los textos de demo pasa a leer los archivos del scaffold;
  la paridad es/en se verifica por archivo; se agrega un caso que falla si una clave de demo aparece en
  los catálogos base, y otro que verifica instalar → claves resueltas, quitar → claves ausentes.
- **S7 (docs)**: actualizar la invariante de `AGENTS.md` (el layout deja de ser plano), la guía
  `docs/guia-stack-php-termux.md` §15.3 y la prosa que afirma «217 claves cada uno», y el tutorial
  (`docs/tutorial-crud.md`: cap. 4, 6.7 y 16), donde hoy se dice que las claves «ya están».

## Tasks

- **T1** — Loader y scaffolds (S1-S5): `addResource` para `lang/<locale>/*.php`; mover las 101 claves de
  demo desde `lang/es.php` y `lang/en.php` a `lang/{es,en}/<demo>.php` dentro de cada scaffold
  (`tasks`, `contacts`, `notes`); `lang` a la allowlist `$keep` del remover. Ruta: worker. Commit:
  `9ceb8dd`
- **T2** — Tests (S4, S6): `tests/Support/I18nDemosTest.php` con la referencia en los scaffolds;
  `tests/Support/I18nTest.php` paridad por archivo; `tests/Support/DemoInstallerTest.php` con los
  archivos nuevos; casos de «sin demos» y de instalación/remoción de claves. Ruta: worker. Commit:
  `9ceb8dd`
- **T3** — Documentación (S7): `AGENTS.md`, guía §15.3 y conteos, tutorial 4/6.7/16. Ruta: worker.
  Commit: `1bf6526`
- **T4** — Verificación: cerrado. `composer verify` verde (277 tests / 5291 assertions, PHPStan sin
  errores, docs OK, migrate:fresh OK). Round-trips de los tres demos, cada uno en su corrida: tasks 332
  tests, contacts 307, notes 292, todos con `remove` limpio y sin `lang/<locale>/` al final. Grep de
  claves de demo en los base: sin salida. Con `notes` instalado el traductor resuelve 218 claves; sin
  demos, 117. Ruta: padre. Commit: `9ceb8dd` (código y tests) y `1bf6526` (documentación).

## Log

- **L1**: «ahora he notado algo... y es que la carpeta "lang" tiene los archivos de traduccion, sin
  embargo, esto deberia hacerse de forma instruida, es decir, los tutoriales deberian instalar sus claves
  de traduccion solo cuando el demo que lo requiere este instalado, no antes... o en la practica, tendremos
  una lista de traducciones en el repositorio que no los usa nadie, solo cuando ek demo que lo usa lo
  instale... no te parece correcto este enfoque? si no estariamos ensuciando el repositorio con
  traducciones que nadie usa, y peor, que aparecen en aplicaciones y que conviven con la traduccion del
  sistema que el usuario creara usando este framework lo cual esta mal»
- **L2**: «lo que yo quiero es que el framework base, tenga su archivo de traduccion, pero que al instalar
  demo, cada demo traiga su propio archivo de traduccion, asi , que si no hay demos instalados, el
  proyecto se mantiene limpio, y esas claves de traduccion de los demos, solo existan cuando se instala
  cada demo.»
- **L3**: medición que motiva el cambio: 217 claves por catálogo; 101 son de demo (`tasks.` 28,
  `contacts.` 46, `notes.` 24, `nav.*` 3) y 116 del cromo. Los bloques son contiguos (`es` 187-288,
  `en` 144-243). El instalador es copia/borrado con manifiesto sha256: no hay merge, y el layout por
  archivo encaja sin tocar `scripts/demo.php`.
- **L4**: decisiones. (a) Se descarta fusionar arrays dentro de `lang/<locale>.php`: rompe el manifiesto
  por archivo, exige política de conflicto y borrado quirúrgico de claves. (b) El escaneo del loader es
  por request a propósito, así que instalar o quitar el demo se ve en la petición siguiente sin tocar
  cachés. (c) `home.*` se queda en el base (S5). (d) Paridad es/en pasa a ser por archivo, no por el
  conjunto global. (e) La portada no se deja en blanco ni muestra claves crudas: un demo no instalado se
  anuncia con `home.demo_available` («Demo %name%: no instalado») y su comando de instalación, sin pedir
  `summary`/`why`; con el demo puesto, la ficha usa sus metadatos. La clave nueva vive en el catálogo base
  porque la portada es del esqueleto.
- **L5**: el cambio toca un archivo que la guía copia entero (`resources/views/pages/home.twig`), así que
  el gate de docs exige `composer docs:sync`; se sincronizó la copia y no se tocó prosa fuera de §15.3.
  Riesgo aceptado: el test de paridad por archivo escribe un fixture temporal en `lang/<locale>/` y lo
  borra en `finally`, comprobando antes y después que no exista.
