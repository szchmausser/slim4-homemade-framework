# Feature: framework-comments

Reescribir los comentarios de `framework/` (registro arquitectónico) y de
`framework/scaffolds/*` (registro didáctico), agregando los que faltan y quitando
los que no deberían estar. Solo comentarios: el comportamiento no cambia.
Rama `docs/framework-comments`.

## Specs

S1. Dos registros, un mismo repo. Framework: "comentarios desde el punto de vista
de arquitectura del software, tecnicos, pero no cripticos"; Demos: "con todo
didactico e instruccional, a medida de enseñar al nuevo usuario como crear sus
entidades usando como referencia los demos".

S2. Qué debe decir un comentario de framework: "que es o que hace el metodo
internamente y el motivo de su existencia o donde encaja o colabora respecto a
otras partes de la aplicacion"; debe "ilustrar como funciona el framework y
porque esta hecho como esta hecho".

S3. Qué debe decir un comentario de demo: "ponga en contexto al usuario de que
hace cada cosa", apoyado en el demo como referencia para crear entidades nuevas.

S4. Quitar lo que no corresponde: "hay comentarios muy verbosos y
descontextualizados o fuera de lugar". Se elimina de los archivos: IDs de spec
(`(S13)`, `(S7)`), historia de decisiones ("igual que hacía a mano", "antes de
existir este helper"), notas de revisión ("merece relectura", "verificado en el
vendor"), referencias a conteos de tests y a nombres de tests como autoridad
dentro de `framework/`.

S5. "agrega comentarios donde deberia haberlos y no los hay y remuevelos donde
hay y no deberia haberlos". Agregar: docblock de clase y de método público en
todo `framework/`; encabezado didáctico en cada archivo de demo. Remover: narración
interna sin valor para quien usa el framework.

S6. Registro: "Español neutro/profesional", sin voseo (`tienes`, `ajusta`, `mira`;
nunca `tenés`, `ajustá`, `mirá`). Decisión del usuario en esta sesión.

S7. Criterio uniforme: "Reescribir con el criterio nuevo" — todo comentario de un
archivo tocado queda con la misma voz y estructura, aunque el anterior ya cumpliera.

S8. Solo comentarios. El flujo de tokens sin `T_COMMENT`/`T_DOC_COMMENT` debe ser
idéntico al de `HEAD` en cada archivo modificado. Ninguna línea de código cambia.

S9. Copy sincronizada: tras tocar archivos que la guía copia, `composer docs:sync`
y `composer docs:check` verde.

S10. Invariantes del repo intactos: URLs por helper, migraciones como única fuente
del esquema, scaffold como fuente del demo (no se edita la copia instalada),
sin CDNs, SRI sin cambios.

## Tasks

- T1 (inline) Rama, este documento, espejo Engram y contrato de estilo. Commits: `docs:tarea`.
- T2 (inline) Ejemplos canónicos: `framework/Http/Url.php`, `framework/Http/Pagination.php` (voz de framework) y `framework/scaffolds/notas/**` (voz de demo). S1-S7. Commit: `docs(framework): voz canonica`.
- T3 (writer) `framework/Auth/**`, `framework/Support/**`, `framework/Validation/**`, `framework/Docs/**`. S1, S2, S4-S8.
- T4 (writer) `framework/Http/Controller.php`, `CsrfTokens.php`, `Flash.php`, `Middleware/**`. S1, S2, S4-S8. (Excluye `Url.php` y `Pagination.php`: son T2.)
- T5 (writer) `framework/scaffolds/tasks/**`. S1, S3-S8.
- T6 (writer) `framework/scaffolds/contacts/**`. S1, S3-S8.
- T7 (inline) `composer docs:sync` + `composer docs:check` + `composer verify` + chequeo de token-stream + reinstalar demo notas. S8-S10.
- T8 (inline) `tutorial-crud.md`: sincronizar solo los bloques que presentan el archivo
  terminado (`config/navigation/notas.php`, `components/ui/page-header-title.twig`,
  `pages/notas/_form.twig`). S4, S5.
- T9 (inline) Retirar el guard de solo-comentarios: era instrumento de verificación de
  esta sesión, no una herramienta del repo.
- T10 (inline) Tests de usuario por entidad: tutorial a `tests/Notas/Http/NotaTest.php` con
  `namespace Tests\Notas\Http`, convención en `AGENTS.md`. S5.

## Log

- L1 (usuario): "quiero que analices el codebase del proyecto y agregues comentarios
  acordes, es decir, este proyecto es un framework que se usara para desarrollar
  otras aplicaciones, y quiero documentar mejor los metodos o funciones ya que hay
  comentarios muy verbosos y descontextualizados o fuera de lugar... hay dos puntos
  de vista, los archivos del framework, deben tener comentarios desde el punto de
  vista de arquitectura del software, tecnicos, pero no cripticos, tienen que ser
  comentarios que expliquen que es o que hace el metodo internamente y el motivo de
  su existencia o donde encaja o colabora respecto a otras partes de la aplicacion...
  tienen que ser comentarios que ilustren como funciona el framework y porque esta
  hecho como esta hecho... ahora, en el caso de los demos instalables, deben ser con
  todo didactico e instruccional, a medida de enseñar al nuevo usuario como crear sus
  entidades usando como referencia los demos, de manera que al leerlo, el comentario
  ponga en contexto al usuario de que hace cada cosa... realiza una revision de los
  comentarios actuales y mejoralos de la forma y con los enfoques que te he pedido."
- L2 (usuario): "tambien agrega comentarios donde deberia haberlos y no los hay y
  remuevelos donde hay y no deberia haberlos".
- L3 (decisiones del usuario): alcance = framework núcleo + los 3 demos completos;
  registro = español neutro/profesional; criterio = reescritura uniforme.
- L4 (evidencia): `docs/guia-stack-php-termux.md` copia 25 archivos de `framework/`
  dentro de fences verificados; `scripts/docs-check.php:112` compara solo `docs/`.
  `docs-check.php:37` rechaza "64-bit PHP", "jsdelivr", "unpkg" en comentarios de
  `framework/{Auth,Http,Validation,Docs}`.
- L5 (evidencia): demo notas instalado en el working tree (sin trackear). Fuente =
  `framework/scaffolds/notas/**`; se reinstala tras editar el scaffold.
- L6 (cierre, T7): `composer verify` verde — lint, `docs:check` (25 bloques
  sincronizados), PHPStan nivel 5 sin errores, PHPUnit 208 tests / 1525 aserciones,
  `migrate:fresh` sobre SQLite temporal. Guard de solo-comentarios contra el commit
  base 288cd97: 57/57 archivos PHP con flujo de tokens idéntico; ninguna línea de
  código cambió en todo el feature. Demo notas reinstalado desde el scaffold.
- L7 (commits): ec83e0a (T3 auth/validación/soporte), 1260169 (T2+T4 http),
  7d5b4b0 y 42a897e (T5 tasks: datos/rutas y vistas/tests), 3781f0e (T6 contacts),
  f2b2a69 (T2 notas), 88d0c06 (T7 guía sincronizada). Rama `docs/framework-comments`.
- L8 (hallazgo, T8): `tutorial-crud.md` no copia archivos: muestra el estado de cada
  paso. De 90 bloques, 10 declaran archivo: 3 presentan el archivo terminado
  (sincronizados), 3 son provisionales por diseño (closure de la ruta, HTML estático,
  `<ul>` a mano) y 4 declaran archivos que crea el lector
  (`app/Models/Nota.php`, `app/Http/Controllers/NotaController.php`,
  `tests/Http/NotaTest.php`) que no existen en el repo. Por eso NO se suma al gate:
  `CodeBlocks` los trataría como copias y fallaría en rutas que son destino del
  lector. La entrada de navegación del tutorial se deja en sus claves mínimas: los
  campos `summary`/`why` son texto propio del demo y copiarlos enseñaría lo contrario.
- L9 (hallazgo abierto, fuera de alcance): el tutorial construye la entidad en la
  estructura plana de la app base (`App\Models`, `Tests\Http`) y el demo notas en una
  carpeta por entidad (`App\Notas\Models`, `Tests\Notas\Http`). El `why` de la
  navegación lo presenta como "la referencia para comparar": esa equivalencia no se
  cumple y requiere una decisión editorial aparte.
- L10 (T9): el guard de solo-comentarios y sus scratch se eliminaron. La evidencia
  queda en L6; el repo no gana una herramienta nueva.
- L11 (decisión del usuario, T10): no se mueve la suite a `framework/Tests`. El corte
  `tests/Http` vs `tests/Support` es por técnica, no por propiedad: `AuthTest` prueba
  `App\Http\Controllers\Auth\AuthController`, `SchemaIntegrityTest` prueba las
  migraciones de la app y `UrlTest`/`EnvTest`/`FlashTest`/`ValidatorTest` (que sí son
  framework) viven en `tests/Support`. Mover la suite crearía la dependencia
  `Framework -> App` que `AGENTS.md` prohíbe, y el namespace `Framework\Tests` exige
  `framework/Tests/` con mayúscula para no romper PSR-4 en Termux/Linux. Se resuelve
  solo lo barato: los tests de usuario van a `tests/<Entidad>/Http/` con
  `namespace Tests\<Entidad>\Http` (tutorial actualizado) y la convención queda en el
  mapa de `AGENTS.md`. El harness (`tests/bootstrap.php`, `tests/Support/AppFactory`,
  `HttpTestCase`) no se mueve: construye la app del usuario y conoce `App\Models\User`.
  Sube el costo de la alternativa: la guía copia 7 archivos de `tests/` en fences y
  enumera cada test en `guia:7583-7606`; eso no lo arregla `docs:sync`.
- L12 (decisión del usuario, T10): la convención sugerida para entidades nuevas es la plana
  —`app/Models` y `app/Http/Controllers`, que es lo que enseña el tutorial—. Los demos son
  la excepción: agrupan por entidad en `app/<Demo>/`, igual que sus tests. Se documenta el
  corte en el mapa de `AGENTS.md` y en el párrafo de comparación del tutorial, y el `why`
  del demo deja de prometer equivalencia de ubicaciones.
