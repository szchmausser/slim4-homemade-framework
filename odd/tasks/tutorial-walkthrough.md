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

- **T1** — Revisar el diff pendiente y correr `composer verify` (S1, S5). Hecho.
- **T2** — Commitear los cambios pendientes en dos unidades revisables (S1, S5). Hecho:
  `c04037e` (componente compartido) y `e05e11e` (reestructuración del tutorial).
- **T3** — Preparar un entorno limpio de recorrido (S3). Hecho: worktree
  `slim4-homemade-framework-walkthrough`, servidor en `127.0.0.1:8090`, sesión sembrada.
- **T4** — Seguir los capítulos 1 a 5 (S3, S6). Hecho; hallazgos F1 y F2.
- **T5** — Seguir los capítulos 6 a 10, con la pantalla a la vista (S3, S6, S7). Hecho; F3 a F6,
  F9 y F17.
- **T6** — Seguir los capítulos 11 a 14 (S3, S6). Hecho; F10 a F16.
- **T7** — Corregir el tutorial con los hallazgos (S3). Hecho: 17 hallazgos, **16 corregidos**, 1
  documentado sin tocar el tutorial (`1 notas`, que es del demo: arreglarlo exige tocar el demo y las
  dos vistas a la vez).
- **T8** — Comparar el resultado escrito a mano contra `composer demo:notas:install` (S4). Hecho: el
  diff contra `framework/scaffolds/notas/` queda en nombres de namespace, orden de métodos y
  comentarios.

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
- **L3**: corrección de L2 — existe la skill `ego-browser` y su CLI. El navegador estaba cerrado. Con
  el navegador abierto, la progresión se observó con capturas por capítulo en el espacio
  `tutorial CRUD walkthrough` (id 1).
- **L4**: resultado del recorrido — el tutorial es seguible de punta a punta; los 17 hallazgos están
  en `odd/tasks/tutorial-walkthrough-findings.md`. Dos de ellos rompían el recorrido en silencio (F4,
  el `only` que vacía el CSRF; F12, el `+` que descarta el título del fixture) y uno dejaba la suite en
  rojo (F10).
- **L5**: el resultado escrito a mano equivale al demo, salvo namespace, orden de métodos y
  comentarios; el diff se comprobó archivo por archivo.
- **L6**: ninguno de los 16 hallazgos corregidos exigió cambiar un demo instalable
  (`git diff e05e11e..HEAD -- framework/scaffolds/` está vacío). En cada discrepancia el demo era el
  correcto y la guía la que se había desviado. Los únicos cambios en demos son los de `c04037e`, la
  estandarización del encabezado que ya estaba pendiente.
- **L7**: límite de esta pasada — la guía corregida **no se volvió a recorrer entera** desde un clon
  limpio. Se reejecutó y observó cada comprobación tocada (6.6, 7.3, 7.4, 7.5, 10.2, 11, 12.2, 12.3,
  13.2 y el test del instalador), pero no el libro completo de nuevo. La receta `curl` de 7.5 se validó
  con un POST equivalente desde la página (422, modal abierto, error, cuerpo conservado), no con el
  bloque de shell tal como está escrito. El capítulo 14 no se ejercitó con una segunda entidad real.
