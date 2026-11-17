# Recorrido verificable del tutorial CRUD

Validar `tutorial-crud.md` siguiéndolo literalmente en un clon limpio, capítulo por capítulo, y
corregir cada error u omisión que impida llegar al estado que produce `composer demo:notas:install`.

## Specs

- **S1**: «se busca estandarizar el uso de componentes de los demos de contacts y notas ya que usan
  la misma base del layout». Los dos demos comparten un único encabezado de página
  (`components/ui/page-header.twig`), con icono opcional, título, subtítulo y bloque `actions`.
- **S2**: «se ha actualizado el tutorial para hacer una progresion didactica sin introducir conceptos
  de forma que abrumen al usuario que sigue la guia». Cada capítulo introduce una sola pieza nueva y
  la comprobación se limita a lo que ese capítulo construyó.
- **S3**: «asegurate de seguirlo de forma progresiva y haz las correcciones que consideres necesarias
  debido a errores u omisiones en la guia». Toda instrucción del tutorial se ejecuta tal como está
  escrita; si falla o falta, el tutorial se corrige.
- **S4**: «la idea es asegurarnos que la guia es seguible y que el usuario pueda llegar al punto de
  crear a mano el proyecto de la misma forma como si lo hubiera instalado». El resultado de escribir
  a mano el tutorial equivale funcionalmente al demo instalado, salvo los nombres de namespace
  (`App\Notas\…` en el demo, `App\Models\Nota` y `App\Http\Controllers\NotaController` en el
  recorrido) que el propio tutorial declara.
- **S5**: «considero conveniente que hagas commit para guardar los cambios». Los cambios pendientes
  se commitean antes de empezar la revisión.
- **S6**: «empezar a hacer la revision paso a paso, sin tomar atajos e identificar en que puntos la
  guia necesita ajustes». Sin saltar capítulos ni dar por buena una comprobación no observada.
- **S7**: «usa ego browser para que visualices la progresion a medida que sigues el tutorial». Se
  observa la pantalla en cada capítulo que cambie la UI.

## Tasks

- **T1** — Revisar el diff pendiente y correr `composer verify` (S1, S5). Hecho en `main`.
- **T2** — Commitear los cambios pendientes en unidades revisables: componente compartido y
  reestructuración del tutorial (S1, S5).
- **T3** — Preparar un entorno limpio de recorrido, sin `.git` y sin base previa, y arrancarlo (S3).
- **T4** — Seguir los capítulos 1 a 5 y registrar hallazgos (S3, S6).
- **T5** — Seguir los capítulos 6 a 10, observando la pantalla en cada uno (S3, S6, S7).
- **T6** — Seguir los capítulos 11 a 14, incluida la paginación y la repetición del patrón (S3, S6).
- **T7** — Corregir el tutorial con los hallazgos y volver a verificar las comprobaciones tocadas (S3).
- **T8** — Comparar el resultado escrito a mano contra `composer demo:notas:install` (S4).

## Log

- **L1**: «fijate los cambios que se han incorporado en la documentacion y los demos, se busca
  estandarizar el uso de componentes de los demos de contacts y notas ya que usan la misma base del
  layout, y se ha actualizado el tutorial para hacer una progresion didactica sin introducir
  conceptos de forma que abrumen al usuario que sigue la guia... asegurate de seguirlo de forma
  progresiva y haz las correcciones que consideres necesarias debido a errores u omisiones en la
  guia, la idea es asegurarnos que la guia es seguible y que el usuario pueda llegar al punto de
  crear a mano el proyecto de la misma forma como si lo hubiera instalado... considero conveniente
  que hagas commit para guardar los cambios y empezar a hacer la revision paso a paso, sin tomar
  atajos e identificar en que puntos la guia necesita ajustes... usa ego browser para que visualices
  la progresion a medida que sigues el tutorial...»
- **L2**: hallazgo de entorno — en esta sesión no hay herramienta de navegador (ni MCP `gsd-browser`
  cargado ni CLI `ego`). La progresión visual se comprueba por HTTP contra el servidor de desarrollo
  y por captura del HTML renderizado, no por automatización de navegador.
