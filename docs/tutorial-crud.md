# Tu primera entidad en este framework

Recorrido para agregar tu propia entidad a este proyecto, desde un clon recién hecho hasta tenerla
funcionando con tests. Está escrito para seguirse de arriba abajo, en orden, sin saltear pasos.

Al terminar tendrás una entidad `notas` que lista, crea, valida y responde por identificador, y
una suite mínima que reemplaza las comprobaciones que fuiste haciendo a mano.

## Prerrequisitos

Necesitas:

- PHP 8.4.1 o superior con `pdo_sqlite`, `mbstring` y `sqlite3`.
- Composer 2.
- Un navegador.
- `curl` (opcional). Todas las comprobaciones se pueden hacer desde el navegador; `curl` sólo sirve
  si prefieres ver los cuerpos crudos.

**Lo que este recorrido no enseña**: instalar PHP, Composer ni Termux; el contenedor de dependencias;
los middlewares; la autenticación; los assets y el sistema visual; el despliegue. Todo eso está en
`docs/guia-stack-php-termux.md`. Aquí se da por hecho que el framework ya arranca.

## Cómo leer cada paso

Cada paso tiene la misma forma, y conviene respetarla:

- **Objetivo**: la acción concreta.
- **Estado de partida**: qué tiene que estar hecho. Si falta, el paso dice cómo volver.
- **Acción**: el archivo y el código, o el comando.
- **Archivo**: cuando el cambio es un fragmento, indica la ruta y el método o bloque exacto.
- **Antes/Después**: cuando hay que insertar o sustituir, muestra las líneas vecinas para que sepas
  dónde va el cambio. Las líneas `// …` y `{# … #}` marcan código que no cambia.
- **Comprobación**: qué ejecutar y **qué debes ver**, escrito antes de que lo veas.
- **Falla si**: la señal concreta de que el paso no quedó.
- **Qué no prueba**: el límite de esa comprobación.
- **Pieza**: cuando aparece algo reutilizable, qué resuelve, cuándo usarlo, qué evita y cómo se haría
  sin él.

Si una comprobación no da lo esperado, no sigas: el paso siguiente asume que este quedó bien.

## Índice

1. [Arrancar la app hasta tener sesión](#1-arrancar-la-app-hasta-tener-sesión)
2. [La migración de `notas`](#2-la-migración-de-notas)
3. [El modelo `Nota`](#3-el-modelo-nota)
4. [Registrar la entidad en las rutas y en el menú](#4-registrar-la-entidad-en-las-rutas-y-en-el-menú)
5. [La primera ruta: JSON verificable](#5-la-primera-ruta-json-verificable)
6. [La pantalla del listado: del HTML crudo al shell](#6-la-pantalla-del-listado-del-html-crudo-al-shell)
7. [El alta: del formulario crudo al modal del proyecto](#7-el-alta-del-formulario-crudo-al-modal-del-proyecto)
8. [Las acciones por fila: del icono mudo al botón accesible](#8-las-acciones-por-fila-del-icono-mudo-al-botón-accesible)
9. [Editar en el mismo modal](#9-editar-en-el-mismo-modal)
10. [Borrar: del confirm del navegador al diálogo del proyecto](#10-borrar-del-confirm-del-navegador-al-diálogo-del-proyecto)
11. [Tests que reemplazan lo que hacías a mano](#11-tests-que-reemplazan-lo-que-hacías-a-mano)
12. [Extraer la lista a un componente](#12-extraer-la-lista-a-un-componente)
13. [Paginación del servidor](#13-paginación-del-servidor)
14. [Repetir el patrón con otra entidad](#14-repetir-el-patrón-con-otra-entidad)

Cada pieza de UI aparece dos veces: primero en su versión básica —HTML crudo, `<dialog>` nativo,
`confirm()` del navegador, iconos sin nombre— y después sustituida por el componente del proyecto, con
lo que se gana y lo que se evita. Los pasos 1 a 11 dejan la entidad completa y probada; el 12 extrae
la lista a un componente reusable, el 13 hace que la lista siga siendo usable cuando
crece, y el 14 te deja el patrón para otra entidad tuya.

**¿Quieres ver el resultado antes de empezar?** El mismo recorrido existe como demo instalable:
`composer demo:notas:install` (se quita con `composer demo:notas:remove`). Sirve para comparar, no para
aprender: el demo te da el código hecho, el recorrido te hace escribirlo. La diferencia concreta es de
nombres —el demo vive en `App\Notas\…` porque tiene que ser removible; el recorrido usa
`App\Models\Nota` y `App\Http\Controllers\NotaController`, que es donde va una entidad tuya—. El
comportamiento y las vistas son los mismos.

`remove` tiene una salvedad: si editaste alguno de los archivos del demo, aborta y pide
`composer demo:notas:remove -- --force` —que respalda y borra— o `--no-files` —que toca sólo la base—.
El manifiesto en `storage/demo-state/` distingue lo que editaste de lo que sólo quedó viejo porque el
scaffold avanzó.

Míralo **antes** de empezar, en un clon limpio: el demo y tu `notas` son la misma entidad —misma tabla,
misma clase de migración— y no pueden estar instalados a la vez. Si ya seguiste el recorrido, cópialo
para comparar en lugar de instalarlo encima.

## 1. Arrancar la app hasta tener sesión

**Objetivo**: pasar de un clon sin configurar a una app respondiendo, con sesión iniciada.

**Estado de partida**: el repositorio clonado, terminal en la raíz del proyecto.

El clon no arranca: falta `.env`, faltan las dependencias y falta la base. Antes de escribir la primera
línea de la entidad, el proyecto tiene que quedar en un estado comprobable —una app que responde, un
esquema aplicado y una sesión abierta—, porque desde el paso 2 cada comprobación se hace con la app
corriendo y el usuario adentro.

**Acción**:

```bash
cp .env.example .env
composer install:termux
composer dump-autoload -o
composer migrate
```

Después siembra el administrador. Las credenciales van en `.env` —está en `.gitignore`, así que no se
commitea—: descomenta las dos líneas que el archivo ya trae y complétalas.

```dotenv
ADMIN_EMAIL=dev@ejemplo.com
ADMIN_PASSWORD=una-clave-de-8-o-mas
```
Luego ejecuta la migracion en tu terminal, dentro del directorio del proyecto con:

```bash
vendor/bin/phinx seed:run -s DatabaseSeeder
```

Y levanta el servidor de desarrollo:

```bash
php -S 127.0.0.1:8080 -t public
```

`127.0.0.1` y no `0.0.0.0`: con `0.0.0.0` el servidor escucha en todas las interfaces y la app sale a
la red local, donde el administrador que acabas de sembrar tiene acceso a todo. Si necesitas verla
desde otro dispositivo, hay un túnel con TLS para eso; el binding abierto no es el atajo.

**Comprobación**: abre `http://localhost:8080/login` en el navegador. Tienes que ver el formulario de
acceso. Entra con el email y la contraseña que sembraste: después del login el navegador te lleva a la
portada y en el pie del sidebar aparece tu usuario, con tu email debajo.

**Falla si**:

- `phinx seed:run` aborta pidiendo `ADMIN_EMAIL` y `ADMIN_PASSWORD`: no están en `.env` —revisa que las
  dos líneas no sigan empezando con `#`— o la contraseña tiene menos de 8 caracteres.
- El login vuelve al formulario con el aviso «Credenciales inválidas.»: el email o la contraseña no
  coinciden con el admin que sembraste. El mensaje es genérico a propósito —no dice cuál de los dos
  falló, para no revelar qué cuentas existen—, así que revisa los dos.
- `php -S` arranca pero el navegador da 404: estás abriendo la ruta sin `-t public`, o el servidor
  corre en otro directorio.

**Qué no prueba**: que el resto de la app funcione. Prueba que hay conexión a la base, que las
migraciones corrieron y que la sesión se crea. No prueba nada de tu entidad, que todavía no existe.

> Ya existe un administrador con acceso a todo. Si vas a exponer esta app fuera de tu máquina, ese
> usuario es un problema pendiente, no parte del recorrido.

**Qué ganaste**: la app arranca, la base tiene el esquema aplicado y entras con tu usuario. Y mira el
costo: nada de eso sabe que vas a crear notas. No hay tabla, ni modelo, ni ruta, así que la entidad
empieza en el paso siguiente.

![El formulario de acceso](tutorial-crud-images/01-login.png)

![La portada con el usuario en el pie del sidebar](tutorial-crud-images/01-portada.png)

---

## 2. La migración de `notas`

**Objetivo**: crear la tabla. Sin tabla, el modelo no puede guardar nada.

**Estado de partida**: el paso 1 hecho; `composer migrate` corre sin errores.

La app responde, pero no tiene dónde guardar una nota: la primera escritura va a terminar en `no such
table`. En este proyecto el esquema se escribe en migraciones y no a mano en la base, así que el primer
archivo de la entidad es el que crea la tabla y el que permite deshacerla.

**Acción**: mira qué números de migración están libres:

```bash
ls database/migrations/
```

Vas a ver archivos como `001_create_users_table.php` y `002_create_remember_tokens_table.php`. La
convención del proyecto es **numeración secuencial desde `001` y sin huecos**, no timestamps: los
demos ocupan una banda aparte desde `900`, así que el rango bajo es de la app. El número no lo eliges
a mano, lo asigna el comando del proyecto:

```bash
composer make:migration CreateNotasTable
```

Eso deja `006_create_notas_table.php` con el esqueleto. El aviso que imprime es esperado y no bloquea
nada: el scaffold del demo de notas declara la misma clase, así que ese demo no puede instalarse
mientras exista tu migración. Complétalo:

```php
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateNotasTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('notas')
            ->addColumn('titulo', 'string', ['limit' => 120])
            ->addColumn('cuerpo', 'text', ['null' => true])
            ->addTimestamps()
            ->create();
    }
}
```

```bash
vendor/bin/phinx migrate
```

**Phinx tiene un generador** y conviene conocerlo: `vendor/bin/phinx create CreateNotasTable` escribe
`AAAAMMDDhhmmss_create_notas_table.php` —el timestamp del momento, nunca `006_`— con el esqueleto y el
`change()` vacío. En este proyecto no lo uses: en PHP de 32 bits ese prefijo de catorce dígitos satura
el entero, así que todas las migraciones colapsan a la misma versión. `composer make:migration` existe
justo para eso: pone el `NNN_` del siguiente número libre y el nombre en snake case.

> **Los demos viven en una banda reservada.** `tasks` ocupa `900-909`, `notas` `910-919` y `contacts`
> `920-929`, y esos archivos están en `framework/scaffolds/*/database/migrations/<demo>/` aunque no los
> veas en `database/migrations/`: el instalador del demo los copia con esa versión. La app numera desde
> `001` sin entrar nunca en esa banda, así que `composer make:migration` no puede darte un número que
> choque con un scaffold, esté el demo instalado o no.

El archivo son veinte líneas: el comando te deja el número y el nombre bien puestos —el que Phinx toma
como versión— y vos completás el `change()`.

**Comprobación**: ejecuta `vendor/bin/phinx status` (o vuelve a `composer migrate`). Tienes que ver tu
migración listada como aplicada, sin pendientes.

**Falla si**:

- Phinx dice que ya existe una migración con ese número: borra el archivo que acabas de crear y vuelve
  a correr `composer make:migration`; quédate con el número que asigna. Nunca reutilices uno.
- El error menciona `notas` ya existente: la tabla quedó de un intento anterior. `vendor/bin/phinx
  rollback -t 0` la baja, y vuelves a subir.

**Qué no prueba**: que el modelo use esta tabla ni que los campos alcancen. La migración sólo prueba
que el esquema se puede crear y revertir.

> **`titulo` acepta nulos en la tabla**: la obligatoriedad la impone el validador (paso 7).

**Qué ganaste**: la tabla `notas` existe, con sus columnas y su marcha atrás —`phinx rollback` la baja
sin dejar restos—. Lo que todavía no existe es una forma de leerla desde PHP; escribir SQL a mano en cada
controlador es justo lo que el proyecto evita.

**La marcha atrás, en concreto**: `phinx rollback` deshace la última migración aplicada, `-t 0` las
deshace todas y `-t <versión>` baja hasta esa. No hace falta escribir nada extra porque el `change()`
sólo usa operaciones que Phinx sabe invertir: `create()` vuelve a `drop()`, `addColumn` a
`removeColumn`, `addIndex` a `removeIndex`; el `-x` muestra el SQL sin ejecutarlo. Cuando la migración
hace algo que no tiene inversa —SQL directo con `execute()`, por ejemplo—, al revertir Phinx lanza
`IrreversibleMigrationException` («Cannot reverse a … command»), y ahí se escribe a mano con `up()` y
`down()`, que es el estilo al que estás acostumbrado en Laravel. En este repo tenés los dos: casi todas
las migraciones usan `change()`, y `902_drop_done_from_tasks.php` usa `up()`/`down()` porque el backfill
y el `DROP COLUMN` no se pueden adivinar.

**Pieza — migraciones Phinx con numeración secuencial**: resuelven el versionado del esquema sin
depender de un ORM que lo adivine. Usalas siempre que cambie el esquema. Sin esta pieza tendrías que aplicar SQL
a mano y acordarte del orden; el proyecto ya tiene el mecanismo, sólo hay que escribir el archivo.

---

## 3. El modelo `Nota`

**Objetivo**: poder leer y escribir la tabla desde PHP sin SQL escrito a mano.

**Estado de partida**: paso 2 hecho, la tabla existe.

Con la tabla creada, el problema pasa a ser desde dónde escribirla. Un controlador que arma la sentencia
a mano repite el nombre de la tabla, el orden de las columnas y el escapado en cada acción; el proyecto
usa una clase por tabla, y esa clase queda como el único lugar que conoce `notas`.

**Acción**: crea `app/Models/Nota.php`:

```php excerpt
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $titulo
 * @property string|null $cuerpo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static Nota|null find(int|string $id)
 * @method static Nota create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<Nota> orderByDesc(string $column)
 */
class Nota extends Model
{
    protected $table = 'notas';

    protected $fillable = ['titulo', 'cuerpo'];
}
```

**Comprobación**:

```bash
composer lint
```

Esperado: un archivo **más** que antes de crear el modelo. Corre `composer lint` **antes** de crearlo,
anota el total que imprime y compara: después de este archivo, `composer lint` tiene que reportar uno
más.

**Falla si**:

- El lint reporta un error de sintaxis: revisa llaves y `;`.
- El total de archivos no subió respecto de la corrida anterior: el archivo no está donde el lint
  mira. Anota el total antes de crearlo y compara.

**Qué no prueba**: que el modelo conecte con la tabla. El lint sólo verifica sintaxis; la prueba real
llega en el paso 7, cuando creas la primera nota. Si el modelo apunta a una tabla inexistente, el
error aparece ahí, con `no such table: notas`.

> El modelo sí se puede comprobar desde la terminal: `php -r 'require "bootstrap/app.php"; echo
> App\Models\Nota::query()->count(), PHP_EOL;'` imprime `0` sin avisos.

**Qué ganaste**: `Nota::query()` alcanza la tabla y el resto de los pasos puede leer y escribir sin SQL
escrito a mano. El costo es que todavía nadie la usa: no hay ruta que la llame, así que el modelo existe
y no se ve en ninguna pantalla.

**Pieza — Eloquent standalone**: resuelve el acceso a datos con un modelo por tabla. Úsalo para
toda lectura y escritura; el proyecto no escribe SQL a mano en los controladores. Evita el SQL
disperso por las vistas y los controladores. Sin él tendrías que preparar sentencias y mapear filas a
mano en cada operación.

---

## 4. Registrar la entidad en las rutas y en el menú

**Objetivo**: que el framework sepa que existe `/notas`, comprobarlo, y que la app ofrezca el camino
para llegar sin escribir la URL a mano.

**Estado de partida**: pasos 1 a 3 hechos.

### 4.1 Un archivo de rutas por entidad

El modelo ya sabe leer la tabla, pero la app no tiene por dónde pedirla: `/notas` no existe para el
framework. Este paso se lo dice, en un archivo propio en lugar de tocar el arranque de la app, para que
agregar una entidad no obligue a editar infraestructura compartida.

El archivo tiene una sola idea: un grupo bajo `/notas` con la protección aplicada al grupo, y adentro una
ruta que devuelve un texto fijo. Con eso alcanza para saber si el registro funciona.

**Acción**: el archivo de rutas de tu entidad, `routes/notas.php`:

```php excerpt
<?php

declare(strict_types=1);

use Framework\Http\Middleware\RequireAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/notas', function (RouteCollectorProxy $route) {
        $route->get('', function (Request $request, Response $response): Response {
            $response->getBody()->write('notas: ruta registrada');

            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        });
    })->add(RequireAuthMiddleware::class);
};
```

**Y nada más.** No hay que registrar el archivo en ningún lado: el arranque de la app carga `web.php`
primero y después el resto de `routes/*.php` por orden alfabético. Dejar el archivo en `routes/` es todo
lo que hace falta, y por eso agregar una entidad no toca `bootstrap/app.php`.

La ruta atiende un **closure**, no un controlador. Es un andamio: sirve para comprobar el registro sin
escribir todavía la clase, y en el paso 5 lo reemplaza `NotaController`. El `POST` no se registra hasta
el paso 7, cuando exista la acción `store` que lo atiende: una ruta sin acción es una promesa que el
framework no puede cumplir.

**Comprobación**: dos visitas a `http://localhost:8080/notas`, y ninguna debería darte un error.

1. **Sin sesión** —una ventana privada, o cierra sesión antes—. Tienes que volver al login: una
   **redirección 303** a `/login`.
2. **Con sesión**. Tienes que ver el texto, sin HTML alrededor:

   ```
   notas: ruta registrada
   ```

El 303 prueba que la ruta existe y que la protección está puesta: una ruta inexistente devuelve 404, no
redirige. El 200 con el texto prueba que la petición llegó hasta tu closure.

**Falla si**:

- Ves la **página 404 del framework**: el archivo no está directamente en `routes/` o no termina en
  `.php`.
- Ves un **500**: el archivo no devuelve el closure. El arranque hace `(require $archivo)($app)`, así
  que sin `return function (App $app) { … };` PHP intenta llamar al valor que devuelve el `require` y
  muere con `Value of type int is not callable`.
- Ves el login en vez del texto aunque hayas iniciado sesión: la sesión se perdió. Vuelve al paso 1.
- Llegas al texto por otra URL: el patrón del `group('/notas')` no coincide con la que estás abriendo.

**Qué no prueba**: que la ruta haga algo útil. Prueba el registro y la protección; el controlador llega
en el paso 5.

**Convenciones que este paso deja claras**:

- **El nombre del archivo no importa**: un `routes/mi-entidad.php` responde igual.
- **La ubicación sí importa.** Sólo el primer nivel de `routes/`: un archivo en `routes/sub/` nunca se
  carga, y su ruta da 404 sin ningún aviso.
- **El orden de carga es alfabético**, con `web.php` forzado primero. Importa sólo si dos archivos
  declaran la misma ruta y método.
- **Cada archivo devuelve un closure** que recibe la app: `return function (App $app) { … };`. Sin ese
  `return` no hay registro silencioso: el arranque hace `(require $archivo)($app)` y la app responde
  500, no vacío.

**Qué ganaste**: `/notas` existe, responde un texto que sólo puede salir de esta ruta y exige sesión, así
que el registro quedó comprobado sin haber escrito el controlador. Falta lo demás: no hay pantalla, ni
datos, ni un camino para llegar sin teclear la URL.

**Pieza — un archivo de rutas por entidad, cargado por convención**: resuelve la separación entre «la
ruta existe» y «el arranque de la app». Cada entidad tiene su archivo y el arranque los levanta solo.
Evita meter rutas de dominio dentro de `bootstrap/app.php`, que es arranque del framework y no tuyo.
Sin esta pieza tendrías todas las rutas del proyecto en un archivo que crece sin límite.

**Pieza — `RequireAuthMiddleware`**: resuelve la puerta de entrada a las rutas privadas. Se aplica al
grupo, no ruta por ruta, para que no se te olvide ninguna. Evita el clásico «me olvidé de proteger el
POST». Sin él, `curl` podría crear filas sin sesión.

![La ruta responde el texto plano, sin shell](tutorial-crud-images/04-1-ruta-texto.png)

---

### 4.2 El menú: llegar sin escribir la URL

Con la ruta respondiendo, llegas a `/notas` escribiendo la URL a mano. Eso sirve para probar y no para
usar: quien entra a la app no tiene por dónde descubrir la pantalla. El menú lateral es la mejora que
ahorra ese paso, y se arma por convención, igual que las rutas: cada módulo deja su entrada en
`config/navigation/`.

**Acción**: primero el directorio, que no viene en el clon:

```bash
mkdir -p config/navigation
```

La carpeta no existe en el clon: creala. La entrada se versiona.

Ahora el archivo, `config/navigation/notas.php`:

```php excerpt
<?php

declare(strict_types=1);

/**
 * Entrada de la entidad en el menú lateral.
 *
 * El menú se arma leyendo `config/navigation/`: no hay una lista central donde
 * registrar la entidad, así que este archivo es toda la integración. `key` es lo
 * que el sidebar compara con el `active` de la página y `position` ordena las
 * entradas entre sí.
 */
return [
    'label'    => 'Notas',
    'href'     => '/notas',
    'icon'     => 'list',
    'key'      => 'notas',
    'position' => 30,
];
```

`key` es lo que el sidebar compara con el `active` de la página para marcarla; `position` ordena las
entradas (Inicio es 0; Tareas 10, Contactos 20, Notas 30).

**Comprobación**: con sesión iniciada, recarga cualquier página. En el menú lateral aparece «Notas»
debajo de «Inicio», y al tocarlo llegas a `/notas`, que responde el texto de prueba del paso anterior.

Si tu ventana es angosta —por debajo del breakpoint `lg`— el sidebar está colapsado detrás del botón
☰ del header: ábrelo y ahí está la entrada. Las capturas están a ancho de escritorio justamente para
que el menú se vea.

**Falla si**:

- No aparece: falta el directorio `config/navigation/`, el archivo no termina en `.php`, o le falta
  alguna de las claves `label`, `href` o `key`.
- Aparece y al tocarlo da 404: `href` no coincide con el grupo de tu archivo de rutas.

**Qué no prueba**: que la pantalla exista. Probaste que la ruta responde (4.1) y que hay un camino
hasta ella (4.2); lo que se ve al llegar es el paso 6.

**Convenciones que este paso deja claras**:

- **El nombre del archivo no importa**: la entrada se identifica por su `key`.
- **La `key` sí importa**: es el valor que la página declara como `active` para que el sidebar la marque.
- **`position` ordena**, no el orden alfabético ni el nombre del archivo.

**Qué ganaste**: la pantalla tiene puerta: el menú la ofrece en cualquier página y se arma solo, sin
editar `sidebar.twig`. Lo que sigue sin existir es la pantalla misma: el capítulo 5 deja el controlador,
y el 6 la vista.

**Pieza — convención de navegación**: cada módulo declara su entrada en `config/navigation/<módulo>.php`
y el contenedor las descubre. Evita editar `resources/views/layouts/parts/sidebar.twig` y
`config/container.php` para cada entidad. Agregar una
entidad es dejar su archivo; quitarla es borrarlo.

Las dos capturas, antes y después de crear el archivo:

![Antes: el menú lateral sólo tiene «Inicio»](tutorial-crud-images/04-2-menu-sin-notas.png)

![Después: «Notas» aparece debajo de «Inicio»](tutorial-crud-images/04-2-menu-con-notas.png)

---

## 5. La primera ruta: JSON verificable

**Objetivo**: comprobar que tu ruta responde algo, antes de involucrar vistas y formularios.

**Estado de partida**: paso 4 hecho; `/notas` responde el texto de prueba `notas: ruta registrada`.

Ese texto prueba una sola cosa: que la ruta está registrada. No dice si el request llega, si el contenedor
construye la clase ni si la respuesta sale bien formada. Este paso cambia el closure por un controlador y
devuelve un JSON que se compara cadena contra cadena, antes de que una vista meta HTML en el medio.

La recompensa es de diagnóstico: si el JSON sale, el cableado está bien y cualquier problema que aparezca
después es de la vista; si no sale, no tiene sentido tocar Twig todavía. Esa distinción es la que te
ahorra buscar en el lugar equivocado.

**Acción**: ahora el controlador. Crea `app/Http/Controllers/NotaController.php` con una sola acción:

```php excerpt
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Framework\Http\Controller;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NotaController extends Controller
{
    public function index(Request $request, Response $response): Response
    {
        $cuerpo = ['status' => 'ok', 'recurso' => 'notas'];

        $response->getBody()->write((string) json_encode($cuerpo, JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

Y en `routes/notas.php`, el closure del paso 4 se reemplaza por la acción. El archivo queda así, y el
`use` del controlador es lo único nuevo:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\NotaController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/notas', function (RouteCollectorProxy $route) {
        $route->get('', [NotaController::class, 'index']);
    })->add(RequireAuthMiddleware::class);
};
```

**Convenciones que este paso deja claras**:

- **El namespace es la ruta del archivo.** El `composer.json` mapea `App\` a `app/`, así que
  `App\Http\Controllers\NotaController` sólo puede vivir en `app/Http/Controllers/NotaController.php`.
  Si mueves el archivo, mueves el namespace.
- **El nombre del archivo es el nombre de la clase**, mayúscula incluida. En Linux y en Termux el
  sistema de archivos distingue mayúsculas: `notacontroller.php` no carga.
- **Los subdirectorios sí funcionan aquí**, al revés que en `routes/`: `App\Http\Controllers\Auth\AuthController`
  vive en `app/Http/Controllers/Auth/AuthController.php`. El namespace acompaña la profundidad.
- **La ruta apunta a la clase, no al archivo**: `[NotaController::class, 'index']` es un método de
  instancia, y el contenedor construye la clase en cada petición.

**Comprobación**: con sesión iniciada, abre `http://localhost:8080/notas` en el navegador. Tienes que
ver exactamente:

```json
{"status":"ok","recurso":"notas"}
```

**Falla si**:

- Ves `{"status":"ok","recurso":"notas"}` pero el login te lo pidió de nuevo: normal, la ruta está
  protegida; inicia sesión y vuelve.
- Ves HTML: el navegador está mostrando otra ruta; revisa la URL.
- Ves un error de PHP: el `namespace` o el nombre de la clase no coinciden con la ruta del archivo.

**Qué no prueba**: nada sobre datos ni sobre interfaz. Prueba el camino completo request → ruta →
controlador → respuesta, que es lo que se rompe primero cuando algo está mal cableado.

> Esta primera comprobación es JSON a propósito: no hay HTML que interpretar, así que la comparación
> es cadena contra cadena. Cuando en el paso 6 entre una vista, la comprobación pasa a ser visual y
> menos precisa.

**Qué ganaste**: el camino request → ruta → controlador → respuesta funciona, y lo puedes verificar sin
interpretar nada. El costo es que no hay pantalla ni datos: el controlador devuelve una constante. Lo que
sigue es darle una vista.

![El JSON exacto, comparado cadena contra cadena](tutorial-crud-images/05-json.png)

---

## 6. La pantalla del listado: del HTML crudo al shell

**Objetivo**: llegar a la pantalla del listado entendiendo qué aporta cada pieza, empezando por lo
básico y sin mezclar capas.

**Estado de partida**: paso 5 hecho; `/notas` devuelve el JSON de prueba y el menú ya muestra la
entrada. Ahora `index()` deja de devolver JSON.

### 6.1 Primero, un HTML mínimo desde el controlador

El JSON se compara de un vistazo, pero no es una pantalla: no tiene `<head>`, ni tema, ni menú, y no hay
dónde poner una lista. `index()` tiene que devolver HTML. Escríbelo a mano dentro del método para ver qué
hace falta, antes de que Twig se haga cargo.

**Acción**: todavía no crees una vista ni toques el modelo. Haz que `index()` devuelva HTML directo:

```php
    public function index(Request $request, Response $response): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Notas</title></head>
<body>
    <h1>Notas</h1>
    <p>Aquí va a vivir el listado.</p>
</body>
</html>
HTML;

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
```

No agregues `use App\Models\Nota;` todavía: en este paso no hay consulta ni plantilla.

**Comprobación**: abre `http://localhost:8080/notas`. Ves una página blanca con el título y el
párrafo, **sin sidebar, sin header y sin tema**. Es HTML del controlador, no del proyecto.

**Qué no prueba**: Twig, el modelo ni el shell. Prueba que la ruta, el controlador y la respuesta HTTP
se hablan.

**Por qué este paso existe**: si el HTML directo falla, el problema está en la ruta o en el
controlador. Si mezclas Twig y Eloquent desde el principio, un error de cualquiera de las tres capas se
ve igual y no sabes dónde mirar.

**Qué ganaste**: `index()` devuelve una página y, si no se ve, el problema está en el controlador o en la
ruta. El costo salta a la vista: hay HTML adentro de un método PHP, con su `<head>` y su `<title>`, y
cada línea nueva lo vuelve más difícil de leer.

![HTML del controlador: sin sidebar, header ni tema](tutorial-crud-images/06-1-html-crudo.png)

### 6.2 Mover ese HTML a una plantilla Twig

Ese HTML vive donde no debería: mezcla la respuesta con la presentación y no hay dónde poner un bucle sin
retorcer el heredoc. Twig resuelve las dos cosas a la vez —un archivo para la vista y el escapado de los
datos que van a entrar después—, y por eso el contenido se mueve tal cual, sin cambios.

**Acción**: crea `resources/views/pages/notas/index.twig` con el mismo contenido, todavía estático:

```twig excerpt
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Notas</title></head>
<body>
    <h1>Notas</h1>
    <p>Aquí va a vivir el listado.</p>
</body>
</html>
```

Y cambia el cuerpo de `index()` para renderizarla:

```php
    public function index(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'pages/notas/index.twig');
    }
```

**Comprobación**: la página se ve **idéntica** a la de 6.1. Cambió dónde vive el HTML, no lo que
devuelve.

**Qué ganaste**: la vista está en un archivo Twig y el controlador ya no concatena HTML. Twig se va a
encargar del escapado cuando entren los datos, pero eso todavía no ocurre.

**Falla si**:

- Ves una página en blanco o un 500: la ruta de la plantilla no coincide con
  `resources/views/pages/notas/index.twig`.
- Ves el HTML dos veces: quedó el `write()` anterior y además el `render()`.

**Pieza — `Controller::render()`**: resuelve la plantilla, le inyecta los datos comunes (CSRF, usuario,
flash) y devuelve una respuesta PSR-7. Úsalo en toda acción que devuelva HTML; evita repetir el
`withHeader('Content-Type', ...)` y el manejo de la vista.

![La misma página desde Twig: cambia dónde vive el HTML](tutorial-crud-images/06-2-twig.png)

### 6.3 Ahora sí, las notas desde el modelo

La vista ya se renderiza, pero el controlador no consulta nada: el `<h1>` y el párrafo son fijos. Este
paso conecta las tres piezas que hasta aquí estaban sueltas —modelo, controlador y plantilla— y es el
primero que puede fallar por datos y no por cableado.

**Archivo**: `app/Http/Controllers/NotaController.php`.

**Acción**: suma el import del modelo, junto a los demás `use`, y pásale la colección a la vista:

```php
use App\Models\Nota;
```

**Archivo**: el mismo controlador, método `index()`.

```php
    public function index(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'pages/notas/index.twig', [
            'notas' => Nota::orderByDesc('id')->get(),
        ]);
    }
```

**Archivo**: `resources/views/pages/notas/index.twig`, el archivo estático de 6.2.

**Antes**: el párrafo que estaba debajo del título.

```twig
    <h1>Notas</h1>
    <p>Aquí va a vivir el listado.</p>
```

**Después**: reemplaza ese párrafo por la lista.

```twig
    <h1>Notas</h1>
    <ul>
        {% for item in notas %}
            <li>{{ item.titulo }}</li>
        {% else %}
            <li>No hay notas todavía.</li>
        {% endfor %}
    </ul>
```

**Comprobación**: con la tabla vacía ves «No hay notas todavía.». Todavía no hay seed de `notas`: inserta
una fila a mano —un `INSERT` puntual contra `database/database.sqlite`, o el modelo que acabas de
crear— y aparece su título. El escapado de Twig convierte `<` y `&` en entidades: no uses `|raw` para
datos de usuario.

**Qué no prueba**: el shell ni los componentes visuales. Prueba el recorrido
controlador → modelo → plantilla.

**Falla si**:

- `Class "App\Http\Controllers\Nota" not found`: falta el `use App\Models\Nota;`.
- La lista sale vacía aunque haya filas: no estás pasando la clave `notas` o el nombre de la variable
  no coincide con el `{% for %}`.
- Aparece HTML crudo del título: alguien agregó `|raw`; quitalo.

**Qué ganaste**: la pantalla muestra lo que hay en la tabla y, si está vacía, lo dice. Lo que se ve, sin
embargo, es el HTML suelto de 6.1: sin tema, sin menú y sin el usuario del paso 1, como una página de
otro sitio. El shell es el paso siguiente.

**Pieza — Eloquent**: `Nota::orderByDesc('id')->get()` devuelve una colección de modelos. El modelo
sabe a qué tabla mapear y qué columnas exponer; la vista solo recibe objetos y no consulta la base.

![La lista con una nota, ya escapada por Twig](tutorial-crud-images/06-3-lista.png)

### 6.4 Ahora, el shell del proyecto

Una página suelta no se distingue de una app: le falta el sidebar, el header y el tema, y todo eso ya
existe en una plantilla del proyecto. Heredar de ella es también la forma de no repetir los assets ni el
marcado del shell en cada pantalla que agregues.

**Acción**: envuelve la misma vista en el layout. No cambies el contenido: el `<h1>`, la lista y el
texto del vacío quedan como están. Lo único que aparece son los bloques.

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Notas{% endblock %}

{% block content %}
    <h1>Notas</h1>
    <ul>
        {% for item in notas %}
            <li>{{ item.titulo }}</li>
        {% else %}
            <li>No hay notas todavía.</li>
        {% endfor %}
    </ul>
{% endblock %}
```

**Comprobación**: recarga `/notas`. Ahora está el sidebar —con tu usuario al pie, como en el paso 1—, el
header y el tema. **El `<body>`, el `<head>` y los assets ya no son tuyos.** El contenido se sigue viendo
como en 6.3: un título y una lista sin estilos propios. No tiene que haber tarjeta, ni filas con borde, ni
espaciado entre los ítems; si ves una tarjeta, estás mirando el paso siguiente.

**Falla si**:

- Ves la página sin sidebar: falta el `{% extends %}` o la plantilla no es `layouts/app.twig`.
- Ves el contenido dos veces: quedó el `<!DOCTYPE html>` de 6.3 además de los bloques.
- El título de la pestaña no cambia por página: falta el `{% block title %}`.

**Qué no prueba**: que el contenido quede legible. Heredar el tema no estiliza tu marcado: eso es el paso
siguiente.

**Qué ganaste**: la pantalla es del proyecto —sidebar con tu usuario, header, tema, assets— y tuyo es sólo
el bloque `content`. Y mira el costo: título y lista quedaron en el mismo bloque, sin nada que puedas
reemplazar por separado.

**Pieza — `layouts/app.twig`**: resuelve el armado del shell completo (sidebar, header, avisos, tema) una sola vez. Úsalo en toda página tuya y escribe sólo el bloque `content`. Evita
rearmar el shell en cada pantalla y quedar desincronizado del resto. Sin él, cada vista repetiría el
header, y el tema se rompería en la que te olvidaras.

![La misma página de 6.3 dentro del shell: sin tarjeta, sin filas con borde, sin estilos propios](tutorial-crud-images/06-4-shell-sin-estilos.png)

### 6.5 El diseño de la vista

La página ya vive en el shell, pero el contenido sigue siendo marcado pelado: un `h1` y una lista. El
proyecto tiene un idioma visual —tarjeta, espaciado, filas con borde— y aplicarlo es un paso aparte, para
que se vea qué aportó el shell y qué aporta el diseño.

**Acción**: envuelve el contenido en la tarjeta del proyecto y dale clases a la lista y a la fila.

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Notas{% endblock %}

{% block content %}
    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-4">
            <h1 class="text-2xl font-bold">Notas</h1>
            <ul class="space-y-2">
                {% for item in notas %}
                    <li class="rounded-box border border-base-300 bg-base-100 p-3">
                        <span class="font-semibold">{{ item.titulo }}</span>
                    </li>
                {% else %}
                    <li class="opacity-60">No hay notas todavía.</li>
                {% endfor %}
            </ul>
        </div>
    </section>
{% endblock %}
```

**Qué hace cada clase**: `card` y `card-body` dan la tarjeta y su relleno; `space-y-2` separa las filas;
`rounded-box`, `border` y `p-3` hacen la fila; `font-semibold` pesa el título y `opacity-60` apaga el
vacío. Son clases de daisyUI y de Tailwind, que en este proyecto corre en el navegador.

**Comprobación**: la misma pantalla de 6.4, ahora con la tarjeta del proyecto y filas separadas y
legibles. Nada de lo que ves es CSS tuyo: todo viene de las clases.

**Falla si**:

- Todo se ve sin bordes ni espaciado: Tailwind no arrancó, o las clases faltan en el marcado.
- La tarjeta no aparece: falta `card`, o el contenido quedó fuera del `card-body`.

**Qué no prueba**: que la fila sea reutilizable. Todavía es marcado de esta pantalla; el capítulo 12 la
pasa a un componente.

**Qué ganaste**: la vista se ve como el resto de la app y el marcado dice qué es cada cosa. Lo que sigue
es separar el panel del resto de la página para poder refrescarlo solo.

![La vista con el diseño del proyecto: tarjeta, filas y vacío](tutorial-crud-images/06-5-estilos.png)

### 6.6 El panel a su propio archivo

El panel es la región que más adelante se va a refrescar sola. Sepáralo del resto de la página ahora,
todavía con el `<ul>` a mano: la lista funciona y no hace falta tocar Twig para tenerla ordenada.

**Acción**: mueve el `<ul>` completo de 6.5 a un parcial nuevo,
`resources/views/pages/notas/_panel.twig`, sin cambiarle nada. El parcial se envuelve a sí mismo:

```twig excerpt
<div id="notas-panel">
    <ul class="space-y-2">
        {% for item in notas %}
            <li class="rounded-box border border-base-300 bg-base-100 p-3">
                <span class="font-semibold">{{ item.titulo }}</span>
            </li>
        {% else %}
            <li class="opacity-60">No hay notas todavía.</li>
        {% endfor %}
    </ul>
</div>
```

**Archivo**: `resources/views/pages/notas/index.twig`, bloque `content`.

**Antes**:

```twig
            <h1 class="text-2xl font-bold">Notas</h1>
            <ul class="space-y-2">
                {# … el bucle completo … #}
            </ul>
        </div>
```

**Después**: cambia el `<ul>` por el include, **sin** envolverlo.

```twig
            <h1 class="text-2xl font-bold">Notas</h1>
            {% include 'pages/notas/_panel.twig' %}
        </div>
```

**Comprobación**: la lista se ve **idéntica** a la de 6.5, con y sin notas.

**Por qué el `div` va adentro**: cuando más adelante el panel se intercambie con HTMX y `outerHTML`,
la respuesta **reemplaza** al elemento apuntado. Si el `div` viviera en `index.twig`, el primer swap
se lo llevaría junto con su id, y todo lo que apunte a `#notas-panel` quedaría apuntando a algo que ya
no existe.

**Falla si**:

- La lista desaparece: el include no está o apunta a otro nombre.
- Ves dos listas: quedó el `<ul>` original y además el include.

**Qué ganaste**: el panel es ahora una unidad que se puede reemplazar sin tocar el resto de la
página. Los pasos siguientes cambian su interior y `index.twig` no se entera.

> El `<ul>` y el `{% for %}` se quedan a la vista hasta el capítulo 12. Recién ahí, con la entidad ya
> probada, la lista se extrae al componente del proyecto.

![La pantalla no cambia: la lista vive ahora en su propio parcial](tutorial-crud-images/06-5-estilos.png)

### 6.7 El encabezado con la acción

La pantalla ya lista, pero no ofrece nada: para crear una nota todavía no hay ninguna acción, porque el
alta llega en 7.2. Necesita un encabezado que diga qué es y ofrezca la acción principal. Escribámoslo a
mano primero, para ver exactamente qué contiene.

**Archivo**: `resources/views/pages/notas/index.twig`, bloque `content`.

**Antes**: después de 6.6, el `<h1>` sigue en `index.twig` y el panel se incluye debajo.

```twig
        <div class="card-body gap-4">
            <h1 class="text-2xl font-bold">Notas</h1>
            {% include 'pages/notas/_panel.twig' %}
        </div>
```

El botón va en la esquina: es la acción principal y el ojo la busca ahí. Por eso el título y el botón
comparten una fila —título a la izquierda, acción a la derecha— y debajo queda la fila del listado.

**Después**: reemplaza ese `<h1>` por el encabezado con la acción; el include del panel queda al final,
dentro del mismo `card-body`.

```twig
        <div class="card-body gap-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold">
                        <span class="opacity-60" aria-hidden="true">
                            {% include 'components/ui/icon.twig' with {name: 'list', class: 'h-5 w-5'} %}
                        </span>
                        Notas
                    </h1>
                    <p class="mt-1 text-sm opacity-70">Tus notas, con alta y acciones por fila.</p>
                </div>
                <button class="btn btn-primary gap-2" data-modal-open="nota-create-modal">
                    Agregar nota
                </button>
            </div>

            <h2 class="text-lg font-semibold">Listado</h2>

            {% include 'pages/notas/_panel.twig' %}
        </div>
```

**Archivo**: `app/Http/Controllers/NotaController.php`, método `index()`.

**Antes**:

```php
    public function index(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'pages/notas/index.twig', [
            'notas' => Nota::orderByDesc('id')->get(),
        ]);
    }
```

**Después**: el arreglo de datos suma dos cosas que **lee el shell, no tu vista**:

- `active`: el sidebar lo compara con la clave `key` de `config/navigation/notas.php` para marcar la
  entrada. Sin él, la página funciona igual pero el menú no resalta «Notas».
- `breadcrumbs`: el header arma la miga con `{label, href?, icon?, current?}`. Su fallback es un
  único «Inicio» marcado como actual, así que sin esta clave el encabezado dice que estás en la
  portada aunque estés en `/notas`. Con dos migas pinta la ruta y marca la última con
  `aria-current="page"`.

```php
    public function index(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'pages/notas/index.twig', [
            'notas'       => Nota::orderByDesc('id')->get(),
            'active'      => 'notas',
            'breadcrumbs' => $this->breadcrumbs('Notas', 'list'),
        ]);
    }
```

Ese `data-modal-open` lo resuelve el shell: **ninguna vista escribe JavaScript para abrir un diálogo**. En
7.5 el botón pasa a `components/ui/button.twig`, con la misma delegación.

**Comprobación**: aparecen el título, el botón «Agregar nota» a la derecha y la lista vacía. El botón
todavía no abre nada: el modal es el paso 7.

**Falla si**:

- El botón aparece sin estilo: la clase `btn` no existe en el bundle, o el layout no está cargando los assets.
- El header sigue diciendo «Inicio»: falta `breadcrumbs`, o la última miga no lleva `current` y el
  encabezado la pinta como enlace.

**Qué no prueba**: que el botón haga algo, ni que la fila tenga acciones. Eso son los pasos 7 y 8.

**Qué ganaste**: la pantalla ya dice qué es y ofrece la acción. Y mira el costo: el
encabezado quedó con nueve líneas de markup propio adentro de `index.twig`, más que el resto de la
vista, y ninguna habla de notas. Eso tiene arreglo, y es el paso siguiente.

![El encabezado con su acción y el listado](tutorial-crud-images/06-7-encabezado.png)

### 6.8 El encabezado a su componente

Fijate en lo que acabas de escribir. Esas líneas no tienen nada que ver con las notas: son la forma del
encabezado —el icono, el título, el subtítulo, la fila que alinea la acción—. Si mañana otra pantalla
necesita lo mismo, las copiarías; a la segunda copia una se vería distinta, y a la tercera ya no sabrías
cuál es la buena. Es el mismo caso del panel en 6.6, y el proyecto ya lo tiene resuelto:
`components/ui/page-header.twig`. Usémoslo: la vista deja de describir la forma y pasa a declarar el
contenido.

**Archivo**: `resources/views/pages/notas/index.twig`, bloque `content`.

**Antes**: el armazón de 6.7, con el botón todavía crudo.

```twig
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold">
                        <span class="opacity-60" aria-hidden="true">
                            {% include 'components/ui/icon.twig' with {name: 'list', class: 'h-5 w-5'} %}
                        </span>
                        Notas
                    </h1>
                    <p class="mt-1 text-sm opacity-70">Tus notas, con alta y acciones por fila.</p>
                </div>
                <button class="btn btn-primary gap-2" data-modal-open="nota-create-modal">
                    Agregar nota
                </button>
            </div>
```

**Después**: el mismo contenido en `components/ui/page-header.twig`. El `<div>`, el `<h1>` y el
subtítulo desaparecen de tu vista; queda el hueco.

```twig
            {% embed 'components/ui/page-header.twig' with {
                icon: 'list',
                title: 'Notas',
                subtitle: 'Tus notas, con alta y acciones por fila.'
            } %}
                {% block actions %}
                    <button class="btn btn-primary gap-2" data-modal-open="nota-create-modal">
                        Agregar nota
                    </button>
                {% endblock %}
            {% endembed %}
```

**Qué hace cada pieza**:

- `title` y `subtitle`: los mismos textos de antes. Se escapan solos. `subtitle` es opcional: si no lo
  pasas, no queda un `<p>` vacío.
- `icon`: opcional; el svg sale del registro compartido y un nombre inexistente no pinta nada.
- `{% block actions %}`: el único hueco. Ahí va el control de la esquina, sea un botón, dos o un
  formulario entero. Es el mismo hueco que `body` en el modal y `row` en la lista. Se pinta **una sola
  vez** y no recibe variables: lo que uses, se lo pasas tú.

**Comprobación**: el encabezado se ve **idéntico** al de 6.7 —título con icono, subtítulo y botón a la
derecha— y el botón sigue sin abrir nada, porque el modal llega en el paso 7.

**Falla si**:

- El título sale vacío: `title` no está llegando o la prop se llama distinto.
- Queda una línea en blanco bajo el subtítulo: pasaste `subtitle: ''`; omítelo en vez de pasarlo vacío.
- El botón cae debajo del título: el `{% block actions %}` quedó fuera del `embed`.
- El icono desaparece: el nombre no está en `components/ui/icon.twig`.

**Qué ganaste**: el armazón del encabezado ya no vive en tu vista. Si mañana cambia el espaciado, el
tamaño del título o la posición del icono, cambia una vez y todas las pantallas que lo usan se enteran.

**Pieza — `components/ui/page-header.twig`**: resuelve la disposición del encabezado: el título a la
izquierda, el hueco de las acciones a la derecha. No repite el markup del título, lo incluye. Evita que
cada vista invente su variante —una con icono, otra sin, una con la acción a la derecha y otra sin
acción— y que el encabezado deje de ser reconocible entre pantallas. El botón cambia en 7.5 sin tocar el
encabezado.

![La pantalla no cambia: el encabezado viene del componente](tutorial-crud-images/06-7-encabezado.png)

### 6.9 La botonera a su componente

El encabezado ya es un componente, pero el control de la esquina sigue siendo un botón suelto dentro
del hueco. Con uno alcanza. Con dos —«Exportar» y «Agregar»— cada control se vuelve hijo directo del
`justify-between` del encabezado, que reparte el espacio entre todos: el primero queda flotando en el
medio de la pantalla, lejos de la esquina. La fila que falta también es un componente, y el proyecto
ya lo tiene. Usémoslo.

**Archivo**: `resources/views/pages/notas/index.twig`, dentro del `{% block actions %}`.

**Antes**: el botón suelto, como quedó en 6.7.

```twig
                {% block actions %}
                    <button class="btn btn-primary gap-2" data-modal-open="nota-create-modal">
                        Agregar nota
                    </button>
                {% endblock %}
```

Con un solo botón esto funciona. Con dos, no: cada control suelto se vuelve hijo directo del
`justify-between` del encabezado, que reparte el espacio entre todos —el título a la izquierda, el
primero en el medio y el último a la derecha—. La botonera es la fila que falta.

**Después**: el botón adentro de `components/ui/action-bar.twig`, que es dueña del `flex`, el `wrap` y
la separación.

```twig
                {% block actions %}
                    {% embed 'components/ui/action-bar.twig' %}
                        {% block controls %}
                            <button class="btn btn-primary gap-2" data-modal-open="nota-create-modal">
                                Agregar nota
                            </button>
                        {% endblock %}
                    {% endembed %}
                {% endblock %}
```

**Qué hace cada pieza**:

- El bloque se llama `controls`, no `actions`: queda adentro del bloque `actions` del encabezado, y dos
  bloques con el mismo nombre se leen mal.
- El componente **no** ordena: la fila es suya, el orden es tuyo. El criterio del proyecto es secundario
  primero y primario al final, pegado al borde: es donde el ojo lo busca y donde el pulgar lo alcanza en
  el móvil.
- `extra_class` es opcional, para alinear o separar distinto en un caso concreto.

**Comprobación**: el encabezado se ve **idéntico** al de 6.8 y el botón sigue sin abrir nada.

**Falla si**:

- Dos controles se reparten por la pantalla, uno en el medio: faltó la botonera.
- El control se estira a todo el ancho de la esquina: la fila quedó fuera del `embed`.

**Cuando aparezca el segundo control**, los dos van adentro de la botonera y en este orden:

```twig
{% embed 'components/ui/action-bar.twig' %}
    {% block controls %}
        {% include 'components/ui/button.twig' with {
            label: 'Volver',
            variant: 'ghost',
            icon: 'arrow-left',
            href: url('/')
        } only %}
        {% include 'components/ui/button.twig' with {
            label: 'Agregar nota',
            icon: 'plus',
            data_modal_open: 'nota-create-modal'
        } only %}
    {% endblock %}
{% endembed %}
```

Cuidado con el primero: en un listado, «Volver» duplica el sidebar y el botón del navegador, y gana su
lugar recién en una pantalla de detalle. Los segundos controles que sí lo justifican son «Exportar»,
«Filtros» o «Actualizar».

**Qué ganaste**: la botonera es dueña del `flex`, el `wrap` y la separación; tu vista pone los controles
y su orden. El día que aparezca el segundo botón no hay espacios que ajustar ni clases que recordar.

**Pieza — `components/ui/action-bar.twig`**: resuelve la fila de controles del encabezado o de una
tarjeta, con su separación y su wrap. Úsala siempre que la esquina lleve más de un control; sin ella,
cada pantalla elige su `gap` y su alineación.

![La pantalla no cambia: la botonera agrupa el control de la esquina](tutorial-crud-images/06-7-encabezado.png)

### 6.10 Anatomía del encabezado

Usaste el encabezado completo sin ver cómo está armado, y vale la pena mirarlo: la forma en que se
compone es la que vas a repetir cuando hagas tus propios compuestos. La idea es simple —un wrapper que
pone la disposición y dos piezas que aportan el contenido— y se ve mejor en el código que en la
explicación.

`resources/views/components/ui/page-header-title.twig` es la mitad izquierda:

```twig
{# Título de página: icono opcional, `title` y `subtitle` opcional.
   Props: icon, title, subtitle.

   Es la mitad izquierda de `components/ui/page-header.twig`, que lo incluye.
   Se puede usar suelto cuando una pantalla necesita el título sin la fila de
   acciones: el encabezado de una tarjeta, un estado vacío, una portada.

   `icon` y `subtitle` no dejan huecos: sin icono el `h1` pierde el `flex` que
   lo alinea con el svg, y sin subtítulo no se emite el `p`. `title` y
   `subtitle` se escapan solos. #}
<div>
    <h1 class="{% if icon|default(false) %}flex items-center gap-2 {% endif %}text-2xl font-bold">
        {% if icon|default(false) %}
            <span class="opacity-60" aria-hidden="true">
                {% include 'components/ui/icon.twig' with {name: icon, class: 'h-5 w-5'} %}
            </span>
        {% endif %}
        {{ title }}
    </h1>
    {% if subtitle|default(false) %}
        <p class="mt-1 text-sm opacity-70">{{ subtitle }}</p>
    {% endif %}
</div>
```

Y `resources/views/components/ui/page-header.twig` es el wrapper: no repite ese markup, lo incluye, y
deja el hueco para la botonera.

```twig
<div class="flex flex-wrap items-start justify-between gap-3">
    {% include 'components/ui/page-header-title.twig' with {
        icon: icon|default(null),
        title: title,
        subtitle: subtitle|default(null)
    } only %}
    {% block actions %}{% endblock %}
</div>
```

**Por qué la botonera se anida en tu vista**: `action-bar` no puede envolver `actions`, porque el bloque
se rellena en tu `index.twig`.

**Qué te deja**: tres contratos en vez de un bloque de markup. El título se puede usar solo —el
encabezado de una tarjeta, un estado vacío— y la botonera también, sobre una tabla o un listado. Cuando
hagas tu propio compuesto, la regla es la misma: el wrapper pone la disposición, los hijos aportan el
contenido.

![La pantalla no cambia: la anatomía explica cómo se compone](tutorial-crud-images/06-7-encabezado.png)

---

## 7. El alta: del formulario crudo al modal del proyecto

**Objetivo**: crear notas desde un diálogo, entendiendo qué aporta cada componente en el camino.

**Estado de partida**: paso 6 hecho; el botón «Agregar nota» existe y no abre nada.

### 7.1 Un diálogo nativo con campos a mano

La pantalla lista, pero no hay forma de crear una: el botón «Agregar nota» no abre
nada, porque no existe la acción que reciba el formulario. Este paso agrega el alta completa —diálogo,
campos y `store()`— en su versión mínima, para que funcione antes de embellecerla.

El diálogo se declara al final del bloque, cerrado, y lo abre el botón del encabezado por su `id`: así el
formulario no ocupa lugar en la página hasta que alguien lo pide. Es la forma más barata de tener alta sin
salir del listado, y por eso el proyecto la conserva.

**Archivo**: `resources/views/pages/notas/index.twig`, bloque `content`, después de `</section>` y antes de
`{% endblock %}`.

**Acción**: agrega un `<dialog>` nativo con el formulario a mano.

> El modal va **adentro** de `{% block content %}`. Si lo pones después de `{% endblock %}`, Twig lo
> ignora sin error: el archivo se ve bien y el botón no abre nada. Es el error más silencioso de este
> paso.

```twig
<dialog id="nota-create-modal" class="modal">
    <div class="modal-box">
        <h3 class="text-lg font-bold">Nueva nota</h3>
        <form method="post" action="{{ url('/notas') }}" class="space-y-4">
            <input type="hidden" name="csrf_name" value="{{ csrf_name }}">
            <input type="hidden" name="csrf_value" value="{{ csrf_value }}">

            <label class="label" for="titulo">Título</label>
            <input id="titulo" name="titulo" class="input w-full" required maxlength="120">

            <label class="label" for="cuerpo">Cuerpo</label>
            <input id="cuerpo" name="cuerpo" class="input w-full" maxlength="2000">

            <div class="modal-action">
                <button type="button" class="btn btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear nota</button>
            </div>
        </form>
    </div>
</dialog>
```

**Comprobación**: toca «Agregar nota»: se abre el diálogo nativo, con sus dos campos y los dos botones.
«Cancelar» lo cierra. Guarda con un título: **no pasa nada todavía** —el formulario apunta a `/notas`
con `POST`, y esa ruta no existe hasta el paso siguiente—.

**Qué no prueba**: que la nota se guarde. Este paso sólo monta el diálogo y su formulario.

![El diálogo nativo abierto, con los campos a mano](tutorial-crud-images/07-1-dialogo-nativo.png)

### 7.2 La acción que guarda

El formulario ya tiene a dónde apuntar, pero del otro lado no hay nada: el `POST /notas` no está
registrado y el controlador no sabe recibirlo. Este paso cierra el circuito —ruta, validación, guardado y
aviso— y es el primero en el que crear una nota funciona de punta a punta.

**Archivo**: `app/Http/Controllers/NotaController.php`, método `store()`.

Vas a necesitar dos `use` más arriba del archivo, junto a los que ya están:

```php
use Framework\Http\Flash;
use Framework\Validation\Validator;
```

```php
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $data['cuerpo'] = trim((string) ($data['cuerpo'] ?? ''));

        $validator = Validator::make($data, [
            'titulo' => 'required|max:120',
            'cuerpo' => 'max:2000',
        ]);

        if ($validator->fails()) {
            return $this->render($request, $response, 'pages/notas/index.twig', [
                'notas'  => Nota::orderByDesc('id')->get(),
                'active' => 'notas',
            ])->withStatus(422);
        }

        Nota::create([
            'titulo' => (string) $data['titulo'],
            'cuerpo' => $data['cuerpo'] !== '' ? $data['cuerpo'] : null,
        ]);

        Flash::set('success', 'Nota creada.');

        return $this->redirect($response, '/notas');
    }
```

**Archivo**: `routes/notas.php`, dentro del `group('/notas')`.

**Antes**:

```php
        $route->get('', [NotaController::class, 'index']);
```

**Después**: agrega el `post` debajo del `get`; las dos rutas comparten el grupo y el middleware de
sesión.

```php
        $route->get('', [NotaController::class, 'index']);
        $route->post('', [NotaController::class, 'store']);
```

Se registra aquí, con su acción, y no en el paso 4: una ruta sin acción es una promesa que el framework
no puede cumplir —y el error aparece recién cuando alguien la visita—.

**Archivo**: el mismo controlador, dentro de la clase.

**Por qué redirige en vez de devolver la página**: el `POST` mutó; si respondiera la página, refrescar
reenviaría el alta y crearías dos. El 303 manda al navegador a `/notas` con un `GET`.

**Comprobación**: guarda con un título. Vuelves a `/notas`, la nota aparece en la lista y el aviso
«Nota creada.» sale abajo a la derecha. El `cuerpo` es opcional: si lo dejas vacío, se guarda como
`null`, no como cadena vacía.

**Qué no prueba**: que el error quede donde corresponde. Si el POST falla la validación, este formulario
repinta la página y **pierde lo tipeado y el modal abierto**: eso es el paso 7.6.

**Qué ganaste**: el alta funciona de punta a punta: el diálogo abre, el servidor valida, la nota aparece
en la lista y el aviso sale abajo a la derecha. Y mira el costo: dos `label` y dos `input` escritos a
mano, sin marca de opcional, sin un lugar donde el error caiga al lado del campo y con el par de CSRF
repetido en cada formulario.

![Después de guardar: la nota en la lista y el aviso de éxito](tutorial-crud-images/07-2-guardada.png)

### 7.3 Sustituir los campos a mano por el del proyecto

El formulario se envía, pero no se puede rechazar bien: no hay dónde poner el error y, cuando lo haya, no
estará pegado al campo que lo causó. El componente del proyecto resuelve el campo entero —label, marca de
opcional, mensaje y atributos de accesibilidad—, y ese contrato es el que 7.6 necesita para mostrar un
rechazo.

**Archivo**: `resources/views/pages/notas/index.twig`, dentro del `<form>` del diálogo de 7.1.

**Antes**: los dos pares `label` + `input` y los dos `hidden` de CSRF.

```twig
            <label class="label" for="titulo">Título</label>
            <input id="titulo" name="titulo" class="input w-full" maxlength="120">

            <label class="label" for="cuerpo">Cuerpo</label>
            <input id="cuerpo" name="cuerpo" class="input w-full" maxlength="2000">

            <input type="hidden" name="csrf_name" value="{{ csrf_name }}">
            <input type="hidden" name="csrf_value" value="{{ csrf_value }}">
```

**Después**: cambia cada par `label` + `input` por el componente, que trae label, error asociado y
atributos de accesibilidad.

```twig
{% set values = form_values|default(null) %}

{% include 'components/form/field.twig' with {
    name: 'titulo',
    label: 'Título',
    value: values ? values.titulo : '',
    required: true,
    maxlength: 120,
    error: errors.titulo|default(null)
} only %}

{% include 'components/form/field.twig' with {
    name: 'cuerpo',
    label: 'Cuerpo',
    value: values ? values.cuerpo : '',
    optional: true,
    maxlength: 2000,
    error: errors.cuerpo|default(null)
} only %}
```

`values` es lo que el usuario tipeó cuando el render viene de un rechazo; si no vino, cae a vacío. Con
`|default('')` sobre cada campo, un título vacío tipeado a propósito volvería al valor guardado, y
el usuario vería el dato viejo en el campo que acaba de borrar.

**Archivo**: el mismo `index.twig`, dentro del `<form>`, donde estaban los dos `hidden`.

**Antes**:

```twig
            <input type="hidden" name="csrf_name" value="{{ csrf_name }}">
            <input type="hidden" name="csrf_value" value="{{ csrf_value }}">
```

**Después**: reemplazalos por el partial que emite los dos campos.

```twig
            {% include 'components/form/csrf.twig' %}
```

**Comprobación**: los campos quedan en el mismo lugar y el input mide lo mismo, pero el label pasa a
`label.fieldset-legend` (12px semibold en vez de 16px normal) y el HTML ahora es un `fieldset` con su
label asociado por `for`/`id`. El campo opcional dice «(opcional)» y se mantienen `required` y
`maxlength`. Mira el HTML con el inspector: `label for` coincide con el `id` del input.

> Los atributos `aria-invalid` y `aria-describedby` que agrega el componente **recién se ven cuando hay
> error**, y eso llega en el paso 7.6, cuando el controlador empieza a devolver `errors`. En este paso
> el formulario todavía no tiene cómo producirlos.

**Qué ganaste**: el campo como una unidad con su label, su marca de opcional y el lugar donde irá el
mensaje. Eso antes no existía: tenías un `label` y un `input` sueltos que se podían desincronizar.

**Pieza — `components/form/field.twig`**: resuelve el campo con su label, su mensaje y sus atributos
de accesibilidad. Úsalo en vez de `<input>` sueltos. Evita el error que no queda pegado al campo que lo
causó. Con `error`, el input sale con `aria-invalid` y `aria-describedby` apuntando al mensaje.

**Pieza — `components/form/csrf.twig`**: resuelve los dos campos ocultos del token. Va **dentro** de todo
`<form>` que mute datos. Evita el rechazo con redirect al login que no explica nada.

![Los campos del componente: label asociado y «(opcional)»](tutorial-crud-images/07-2-campos.png)

### 7.4 Extraer el formulario a un parcial

El formulario ya está bien armado, pero vive adentro del `<dialog>` y del bloque `content`: no hay manera
de usarlo en un segundo lugar sin copiarlo. El capítulo 9 necesita el mismo formulario para editar, con
otro `action` y otros textos; separarlo ahora cuesta menos que unificar dos copias después.

**Acción**: mueve el formulario que hoy vive dentro del `<dialog>` a
`resources/views/pages/notas/_form.twig`:

```twig excerpt
{#
  Formulario de una nota: lo comparten el alta y la edición.

  Lo incluyen los dos modales de `index.twig`, dentro de `components/ui/modal.twig`,
  y hace `POST` a la `action` que recibe. En un rechazo, el controlador
  re-renderiza el listado con `errors`, `form_values` y el modal abierto.

  Para tu entidad: copia el parcial y cambia los campos y sus `maxlength`.
#}

{# `form_values` gana sobre la nota guardada: en un rechazo se repinta lo que el
   usuario escribió, no lo que hay en la base. #}
{% set values = form_values|default(nota|default(null)) %}

{# El envío deshabilita el botón: un doble clic sobre un formulario POST plano
   crea dos filas. Si el servidor rechaza, la página vuelve y el botón se
   renderiza habilitado otra vez. #}
<form method="post" action="{{ action }}" class="space-y-4"
      onsubmit="this.querySelector('[type=submit]').disabled = true">
    {% include 'components/form/csrf.twig' %}

    {# El mensaje llega desde `Validator` con la clave del campo: aquí sólo se
       pasa, y el campo es quien lo ata al input. #}
    {% include 'components/form/field.twig' with {
        name: 'titulo',
        label: 'Título',
        value: values ? values.titulo : '',
        required: true,
        maxlength: 120,
        error: errors.titulo|default(null)
    } only %}

    {% include 'components/form/field.twig' with {
        name: 'cuerpo',
        label: 'Cuerpo',
        value: values ? values.cuerpo : '',
        optional: true,
        maxlength: 2000,
        error: errors.cuerpo|default(null)
    } only %}

    <div class="modal-action">
        {% include 'components/ui/button.twig' with {
            label: 'Cancelar',
            variant: 'ghost',
            type: 'button',
            icon: 'x',
            data_modal_close: true
        } only %}
        {% include 'components/ui/button.twig' with {
            label: submit_label|default('Guardar'),
            type: 'submit',
            icon: submit_icon|default('check')
        } only %}
    </div>
</form>
```

El parcial no sabe si el formulario es de alta o de edición: recibe `action`, `submit_label`,
`submit_icon` y, cuando corresponde, `nota`. `values` resuelve el orden: primero lo que el usuario
tipeó en un rechazo (`form_values`), después la nota guardada, y al final vacío.

El `onsubmit` deshabilita el botón de envío para evitar un doble POST por doble clic. El botón
«Cancelar» usa `data_modal_close`, que el shell traduce a `close()` sobre el `<dialog>` que lo contiene;
por eso funciona igual en el diálogo nativo de este paso y en el modal del proyecto del paso siguiente.
Los dos botones usan `components/ui/button.twig`, el mismo que vas a ver en 8.2: encapsula variante,
icono y cierre.

**Archivo**: `resources/views/pages/notas/index.twig`, dentro de `<dialog id="nota-create-modal">` y de
`<div class="modal-box">`.

**Antes**: el `<form> … </form>` inline de 7.1.

**Después**: reemplaza ese formulario por el include; el `<h3>` y el `<div class="modal-box">` se
conservan.

```twig
            <h3 class="text-lg font-bold">Nueva nota</h3>
            {% include 'pages/notas/_form.twig' with {
                action: url('/notas'),
                submit_label: 'Crear nota',
                submit_icon: 'plus',
                nota: null
            } %}
        </div>
    </dialog>
```

Sin `only` en ese `include`. `_form.twig` incluye `components/form/csrf.twig`, que necesita las cuatro
variables del token; `render()` las inyecta en el contexto de `index.twig`, y `only` corta justo ese
contexto. Con `only` los dos campos ocultos salen con `name=""` y el POST termina en un 303 al login
sin ningún mensaje.

`nota: null` parece un detalle, pero corta la herencia: cuando este mismo parcial se use en el modal de
edición, no queremos que el alta se abra con los datos de la nota que se está editando.

**Comprobación**: el botón «Agregar nota» sigue abriendo el diálogo; «Cancelar» lo cierra y crear una
nota sigue funcionando. Lo único que cambió es que el formulario ahora vive en un archivo reutilizable.

**Qué no prueba**: el modal del proyecto ni la reapertura con errores. El rechazo del servidor todavía
no vuelve a abrir nada; eso llega en 7.6.

**Falla si**:

- El diálogo aparece vacío: el `include` no está o el parcial no recibe `action`.
- El botón «Cancelar» no cierra: el `data_modal_close` quedó fuera del `<dialog>` o el parcial no se
  está renderizando adentro.
- El formulario se envía a la URL equivocada: `action` no se está pasando o quedó el `action` viejo.

**Qué ganaste**: el formulario es un archivo con contrato —`action`, etiqueta del botón, valores— y el
diálogo sólo lo incluye. El `<dialog>` y el botón que lo abre siguen a mano: 7.5 los reemplaza.

**Pieza — un formulario, dos operaciones**: resuelve el formulario una sola vez y evita que el alta y
la edición tengan campos o validaciones distintas. Úsalo cuando los campos son los mismos. Sin esto,
cada operación repite el formulario y una de las dos se olvida de un campo.

La pantalla es la de 7.3: el formulario se movió a un parcial y se ve igual.

### 7.5 Reemplazar el `<dialog>` crudo por el modal del proyecto

El diálogo nativo funciona, pero el proyecto ya tiene un modal con tema, telón, foco y cierre por
Escape. Y el botón del encabezado todavía es un `<button>` suelto: cada pantalla que necesite abrir ese
modal tendría que repetir sus clases.

**Archivo**: `resources/views/pages/notas/index.twig`, bloque `content`, después de `</section>` y antes de
`{% endblock %}`.

**Acción**: reemplaza todo el `<dialog> … </dialog>` por el componente:

```twig
    {% embed 'components/ui/modal.twig' with {
        id: 'nota-create-modal',
        title: 'Nueva nota',
        subtitle: 'Pon un título; el cuerpo es opcional.',
        open: false
    } %}
        {% block body %}
            {% include 'pages/notas/_form.twig' with {
                action: url('/notas'),
                submit_label: 'Crear nota',
                submit_icon: 'plus',
                nota: null
            } %}
        {% endblock %}
    {% endembed %}
```

**Archivo**: el mismo `index.twig`, dentro del `{% block actions %}` del encabezado.

**Antes**:

```twig
                <button class="btn btn-primary gap-2" data-modal-open="nota-create-modal">
                    Agregar nota
                </button>
```

**Después**: reemplaza el botón crudo por el botón del proyecto.

```twig
                {% include 'components/ui/button.twig' with {
                    label: 'Agregar nota',
                    icon: 'plus',
                    data_modal_open: 'nota-create-modal'
                } only %}
```

El componente suma al `<button>` suelto lo que le falta: variante, icono y nombre accesible. El foco,
Escape y el estilo quedan iguales para todos los botones.

**Comprobación**: el botón sigue abriendo el modal, ahora con el telón y el tema del proyecto. Escape,
el botón de cerrar y «Cancelar» lo cierran; crear una nota sigue igual.

**Qué no prueba**: que un rechazo del servidor lo vuelva a abrir. En este paso el modal siempre nace
cerrado (`open: false`).

**Falla si**:

- El botón no abre nada: `data_modal_open` no coincide con el `id` del modal.
- Ves el formulario fuera del modal: el `embed` no está dentro de `{% block content %}`.
- El modal abre con datos de otra nota: falta `nota: null` en el include del alta.

**Qué ganaste**: el modal es del proyecto —telón, foco, cierre con Escape y botón de cerrar— y tu vista
sólo declara el título, el subtítulo y el cuerpo. El costo se ve al rechazar: el modal nace siempre
cerrado, así que un 422 deja el error en una página sin contexto.

**Pieza — `components/ui/modal.twig`**: resuelve el `<dialog>`, el telón, el foco y el cierre. Úsalo
para todo modal del proyecto. Evita rearmar el mismo diálogo a mano en cada vista. Sin él,
cada pantalla inventa su forma de abrir y cerrar, y el foco se comporta distinto en cada una.

![El modal del proyecto, con telón, tema y botones del componente](tutorial-crud-images/07-4-modal.png)

### 7.6 Reabrir el modal con errores 422

Un formulario inválido no es una página nueva: es la misma página con el modal abierto, los errores y
lo que el usuario ya había tipeado. Si el servidor devuelve la página con el modal cerrado, el usuario
ve el error pero pierde el contexto.

**Archivo**: `app/Http/Controllers/NotaController.php`, método `store()`, dentro de
`if ($validator->fails())`.

**Antes**: el rechazo sólo devuelve la lista y el estado 422.

```php
        if ($validator->fails()) {
            return $this->render($request, $response, 'pages/notas/index.twig', [
                'notas'  => Nota::orderByDesc('id')->get(),
                'active' => 'notas',
            ])->withStatus(422);
        }
```

**Después**: agrega `errors`, `form_values` y `open_create_modal` al mismo render.

```php
        if ($validator->fails()) {
            return $this->render($request, $response, 'pages/notas/index.twig', [
                'notas'             => Nota::orderByDesc('id')->get(),
                'errors'            => $validator->errors(),
                'form_values'       => $data,
                'open_create_modal' => true,
                'active'            => 'notas',
            ])->withStatus(422);
        }
```

**Archivo**: `resources/views/pages/notas/index.twig`, dentro del `{% embed 'components/ui/modal.twig' %}`
del modal de alta.

**Antes**:

```twig
        open: false
```

**Después**: reemplaza esa línea por:

```twig
        open: open_create_modal|default(false)
```

Así el alta normal nace cerrada y el rechazo la abre. `form_values` hace que `_form.twig` repinte lo
tipeado; `errors` hace que cada campo muestre su mensaje.

**Comprobación**: el servidor ya sabe rechazar, pero la pantalla no lo deja llegar: el campo lleva
`required`, así que el navegador bloquea el envío con su propio globo y el POST nunca sale. Para ver el
422 hay que mandar el formulario sin pasar por esa validación, y para eso hace falta una sesión. El
bloque completo, de la nada al 422: los tres pasos van en el mismo shell porque comparten el archivo
de cookies.

```bash
J=/tmp/notas-cookies.txt
rm -f $J

# 1. iniciar sesión. El jar arranca vacío: sin este paso, todo lo que sigue responde 303 a /login.
curl -s -c $J http://127.0.0.1:8080/login > /tmp/login.html
csrf_name=$(grep -o 'name="csrf_name" value="[^"]*"' /tmp/login.html | head -1 | sed 's/.*value="//;s/"//')
csrf_value=$(grep -o 'name="csrf_value" value="[^"]*"' /tmp/login.html | head -1 | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -w 'login=%{http_code}' -b $J -c $J --data-urlencode "csrf_name=$csrf_name" --data-urlencode "csrf_value=$csrf_value" --data-urlencode "email=dev@ejemplo.com" --data-urlencode "password=una-clave-de-8-o-mas" http://127.0.0.1:8080/login; echo
# login=303. Un 200 acá significa que la sesión no se creó y el POST de abajo va a dar 303 otra vez.

# 2. con la sesión, el par CSRF del alta sale del propio listado
curl -s -c $J -b $J http://127.0.0.1:8080/notas > /tmp/notas.html
csrf_name=$(grep -o 'name="csrf_name" value="[^"]*"' /tmp/notas.html | head -1 | sed 's/.*value="//;s/"//')
csrf_value=$(grep -o 'name="csrf_value" value="[^"]*"' /tmp/notas.html | head -1 | sed 's/.*value="//;s/"//')

# 3. POST con el título vacío: tiene que responder 422 y traer el modal abierto
curl -s -o /tmp/resp.html -w 'post=%{http_code}' -b $J --data-urlencode "csrf_name=$csrf_name" --data-urlencode "csrf_value=$csrf_value" --data-urlencode "titulo=" --data-urlencode "cuerpo=cuerpo que debe sobrevivir" http://127.0.0.1:8080/notas; echo
grep -c 'nota-create-modal[^>]*open' /tmp/resp.html   # 1
grep -c 'Este campo es obligatorio.' /tmp/resp.html    # 1
grep -c 'cuerpo que debe sobrevivir' /tmp/resp.html    # 1
```

Los tres `grep -c` tienen que dar `1`: modal abierto, error junto al campo y lo tipeado conservado.
Desde el capítulo 11 el mismo caso queda como test y se corre con `vendor/bin/phpunit --filter NotaTest`.

El `required` se queda: es defensa en profundidad. El navegador ahorra el viaje; el servidor no confía
en él, y por eso la validación también vive en `store()`.

**Falla si**:

- El modal queda cerrado: falta `open_create_modal` o el modal no lo está leyendo.
- Los errores no aparecen: no estás pasando `errors` a `_form.twig`.
- Se borra lo tipeado: falta `form_values`.
- El error sale en el modal de edición: `nota` está llegando con datos en el alta; revisa el `nota: null`.

**Qué no prueba**: que dos pestañas abiertas no se pisen; no hay control de concurrencia.

**Qué ganaste**: un formulario rechazado vuelve con el modal abierto, el error junto al campo y lo que
habías escrito; el `required` del navegador queda como primera barrera en vez de única. Y mira el costo:
cada render de rechazo repite a mano la lista, el estado y las claves del modal. En 13.5 eso se
junta en un solo método.

**Pieza — `errors` + `form_values` + `open_*`**: el servidor es dueño del estado del modal. Úsalo en
todo formulario que pueda rechazarse. Evita que el usuario pierda lo escrito y que el error aparezca en
una pantalla sin contexto. Sin esto, el 422 se ve como una recarga cualquiera.

![El rechazo 422: modal abierto, error junto al campo y lo tipeado intacto](tutorial-crud-images/07-5-error-422.png)

---

## 8. Las acciones por fila: del icono mudo al botón accesible

**Objetivo**: que cada fila ofrezca editar y borrar, con nombre accesible.

**Estado de partida**: paso 7 hecho; hay al menos una nota en la lista.

### 8.1 Primero, el atajo que no se debe dejar

La fila muestra el título y nada más: no hay forma de llegar a una nota ni de quitarla desde la pantalla.
El atajo más rápido es un emoji en un enlace y otro en un botón, y este paso lo escribe a propósito, para
ver el resultado y su falla antes de arreglarla.

**Archivo**: `resources/views/pages/notas/_panel.twig`, dentro del `<li>` del `{% for %}`.

**Acción**: la fila pasa a ser flex —si no, las acciones caen debajo del título en vez de quedar a la
derecha— y se agregan los dos controles con emoji. El `<li>` queda así:

```twig
<li class="flex items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-3">
    <span class="font-semibold">{{ item.titulo }}</span>
    <div class="flex shrink-0 items-center gap-1">
        <a class="btn btn-ghost btn-sm" href="{{ url('/notas/' ~ item.id ~ '/edit') }}">✏️</a>
        <button class="btn btn-ghost btn-sm" data-modal-open="nota-delete-{{ item.id }}">🗑️</button>
    </div>
</li>
```

**Comprobación**: se ven dos iconos por fila. Y si abres el inspector o pasas el lector de pantalla,
los dos controles **no tienen nombre**: el enlace se anuncia como «✏️» y el botón como «🗑️». Para
alguien que no ve la pantalla, son dos botones indistinguibles.

**Qué ganaste**: un defecto visible. Los dos controles se ven, pero ninguno se puede anunciar; ahora
sabes señalarlo, y el paso siguiente arregla algo que ya no es una opinión.

**Qué no prueba**: nada del comportamiento: el lápiz y la papelera todavía no llevan a ninguna parte —
las rutas llegan en 8.2 y el borrado en el capítulo 10—; lo que falta acá es que alguien que no ve la
pantalla pueda usar la fila.

> El `data-modal-open` apunta a un diálogo que todavía no existe —llega en el capítulo 10—, y eso es a
> propósito: así el botón parece conectado y el único defecto que queda a la vista es que **no tiene
> nombre**.

![Los iconos con emoji: se ven, pero no se anuncian](tutorial-crud-images/08-1-emojis.png)

### 8.2 Ahora, el botón del proyecto

El atajo deja dos controles que existen y no se pueden usar: uno se anuncia como «✏️», el otro como
«🗑️», y el botón de borrar ni siquiera abre algo. Este paso los sustituye por el botón del proyecto, que
trae el icono del registro compartido y el nombre que anuncia el lector de pantalla, y registra las tres
rutas que faltan, para que el lápiz ya no apunte a una URL que no existe.

**Archivo**: `resources/views/pages/notas/_panel.twig`, dentro del `<li>` del `{% for %}`.

**Acción**: reemplaza los dos por el componente, con icono del registro compartido y nombre accesible:

```twig
                <div class="flex shrink-0 items-center gap-1">
                    {% include 'components/ui/button.twig' with {
                        label: '',
                        variant: 'ghost',
                        size: 'sm',
                        icon: 'pencil',
                        aria_label: 'Editar ' ~ item.titulo,
                        href: url('/notas/' ~ item.id ~ '/edit')
                    } only %}

                    {% include 'components/ui/button.twig' with {
                        label: '',
                        variant: 'ghost',
                        size: 'sm',
                        icon: 'trash',
                        aria_label: 'Borrar ' ~ item.titulo,
                        data_modal_open: 'nota-delete-' ~ item.id
                    } only %}
                </div>
```

El componente no recibe manejadores: `data_modal_open` le pide al shell que abra
ese `<dialog>` con `showModal()`. Así el botón conserva foco, Escape y el
tratamiento visual del proyecto.

**Archivo**: `routes/notas.php`, dentro del `group('/notas')`.

**Antes**: el archivo sólo tiene el listado y el alta.

```php
        $route->get('', [NotaController::class, 'index']);
        $route->post('', [NotaController::class, 'store']);
```

**Después**: agrega las tres rutas de edición y borrado; el archivo queda con cinco.

```php
        $route->get('', [NotaController::class, 'index']);
        $route->post('', [NotaController::class, 'store']);
        $route->get('/{id}/edit', [NotaController::class, 'edit']);
        $route->post('/{id}', [NotaController::class, 'update']);
        $route->post('/{id}/delete', [NotaController::class, 'destroy']);
```

`edit()` y `update()` se implementan en el paso 9; `destroy()` en el paso 10. Hasta entonces, tocar el
lápiz da error, y eso es lo esperado en este punto: la ruta existe y la acción no, así que el error
sale del contenedor y no del framework como un 404.

**Comprobación**: los iconos se ven igual, pero cada control ahora se anuncia como «Editar <título>» y
«Borrar <título>». El lápiz navega a `/notas/{id}/edit`; la papelera todavía no abre nada, porque el
diálogo llega en el paso 10.

**Qué ganaste**: nombre accesible, icono del registro compartido y el mismo tratamiento visual que el
resto de la app.

**Falla si**:

- Los botones aparecen como cuadrados vacíos: falta el `icon`, o el nombre no existe en el registro.
- El lápiz no navega: `href` no llega (con `href` el componente emite `<a>`).

![Los botones del proyecto, con nombre accesible](tutorial-crud-images/08-2-acciones.png)

---

## 9. Editar en el mismo modal

**Objetivo**: editar una nota reusando el formulario y el diálogo del alta.

**Estado de partida**: paso 8 hecho; el lápiz navega a `/notas/{id}/edit`, una ruta que existe pero
cuya acción todavía no.

### 9.1 Abrir el modal con la nota

El lápiz ya navega, pero del otro lado no hay nada: `/notas/{id}/edit` no tiene acción y un id inexistente
tampoco tiene respuesta. Este paso crea `edit()` y el segundo modal, y deja el guardado para 9.2 para
comprobar primero que los datos llegan enteros.

La edición no agrega campos: el formulario ya está escrito y lo que falta es un segundo modal que lo
envuelva con otro `action`. Por eso `index.twig` crece con un `embed` más, envuelto en un `if` que lo deja
existir sólo cuando hay `nota`.

**Archivo**: `app/Http/Controllers/NotaController.php`.

**Acción**: suma el import de la excepción y crea `edit()`:

```php
use Slim\Exception\HttpNotFoundException;
```

```php
    public function edit(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request, 'No existe una nota con ese identificador.');
        }

        return $this->render($request, $response, 'pages/notas/index.twig', [
            'notas'           => Nota::orderByDesc('id')->get(),
            'nota'            => $nota,
            'open_edit_modal' => true,
            'active'          => 'notas',
        ]);
    }
```

`edit()` no renderiza una pantalla nueva: reusa `index.twig`, le pasa la nota y le dice que abra el
modal de edición. Así el formulario, el modal y la lista ya están en la misma página.

**Archivo**: `resources/views/pages/notas/index.twig`, bloque `content`, después del `{% endembed %}` del
modal de alta.

Ahora agrega el segundo modal. Sólo se renderiza cuando hay `nota`:

```twig
    {% if nota is defined %}
        {% embed 'components/ui/modal.twig' with {
            id: 'nota-edit-modal',
            title: 'Editar nota',
            open: open_edit_modal|default(false)
        } %}
            {% block body %}
                {% include 'pages/notas/_form.twig' with {
                    action: url('/notas/' ~ nota.id),
                    submit_label: 'Guardar cambios',
                } %}
            {% endblock %}
        {% endembed %}
    {% endif %}
```

El `action` apunta a `/notas/{id}`; la ruta ya está registrada desde el paso 8, pero la acción `update()`
todavía no existe. Por eso en este paso el botón «Guardar cambios» **no guarda**. Primero comprobamos que
el modal abre con los datos correctos.

**Comprobación**: toca el lápiz de una nota. Esperado: se abre un modal con el título y el cuerpo ya
cargados. Un id que no existe (`/notas/99999/edit`) tiene que dar la página 404 del framework, no un
error de PHP.

**Qué no prueba**: guardar cambios. Eso es 9.2.

**Falla si**:

- El modal abre vacío: `nota` no está llegando, o `_form.twig` no está leyendo `nota`.
- El lápiz abre la página pero no el modal: falta `open_edit_modal` o el `id` no coincide.
- El 404 sale como 500: no estás lanzando `HttpNotFoundException` antes de tocar la nota.
- Ves el modal de edición en el alta: `nota` se está pasando sin querer; el alta debe llevar `nota: null`.

**Qué ganaste**: el modal abre con el título y el cuerpo guardados, y un id que no existe da la 404 del
framework en vez de un error de PHP. Falta lo obvio al primer clic: «Guardar cambios» no guarda, porque
`update()` todavía no existe. Eso es 9.2.

**Pieza — reusar `index.twig` para editar**: resuelve una sola página con dos modales y evita duplicar
la lista. Úsalo cuando la edición comparte la pantalla con el listado. Sin esto, el alta y la edición
tienen dos copias de la tabla y se desincronizan.

![El modal de edición con los datos de la nota](tutorial-crud-images/09-1-editar.png)

### 9.2 Guardar la edición

El modal muestra la nota correcta y el `action` ya apunta a `/notas/{id}`; del otro lado no hay nadie
escuchando. Este paso agrega `update()` y con él la otra mitad del contrato: validar, guardar y volver a
la lista con el aviso, o rechazar sin perder lo escrito.

**Archivo**: el mismo controlador, método `update()`.

**Acción**: agrega `update()`, que valida, guarda y vuelve a la lista:

```php
    public function update(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request, 'No existe una nota con ese identificador.');
        }

        $data = (array) $request->getParsedBody();
        $data['cuerpo'] = trim((string) ($data['cuerpo'] ?? ''));

        $validator = Validator::make($data, [
            'titulo' => 'required|max:120',
            'cuerpo' => 'max:2000',
        ]);

        if ($validator->fails()) {
            return $this->render($request, $response, 'pages/notas/index.twig', [
                'notas'           => Nota::orderByDesc('id')->get(),
                'errors'          => $validator->errors(),
                'form_values'     => $data,
                'nota'            => $nota,
                'open_edit_modal' => true,
                'active'          => 'notas',
            ])->withStatus(422);
        }

        $nota->fill([
            'titulo' => (string) $data['titulo'],
            'cuerpo' => $data['cuerpo'] !== '' ? $data['cuerpo'] : null,
        ])->save();

        Flash::set('success', 'Nota actualizada.');

        return $this->redirect($response, '/notas');
    }
```

La validación es la misma que en el alta, pero el rechazo conserva `nota` y `open_edit_modal`: sin esas
dos claves, el 422 renderiza la lista, el modal de edición no existe y lo tipeado termina en el modal
de alta. `form_values` repinta lo que el usuario escribió; `errors` muestra el mensaje en el campo.

**Comprobación**: abre el lápiz de una nota, cambia el título y guarda. Esperado: vuelves a la lista,
la fila muestra el título nuevo y aparece el aviso «Nota actualizada.». El rechazo se comprueba igual
que en 7.6 —el campo también lleva `required`, así que el navegador no deja enviarlo vacío—: con el
mismo POST por `curl` contra `/notas/{id}`, esperando 422, el modal de edición abierto, el error y el
cuerpo conservado.

**Falla si**:

- Guardar no cambia nada: el `action` del formulario no apunta a `/notas/{id}`, o el controlador no
  hace `save()`.
- El 422 abre el modal de alta: falta `nota` o `open_edit_modal` en el render de rechazo.
- Se pierde lo tipeado: falta `form_values`.
- El 404 sale como 500: no estás lanzando `HttpNotFoundException` antes de tocar la nota.

**Qué no prueba**: que dos personas editando a la vez no se pisen. No hay control de concurrencia, y
este recorrido no lo agrega.

**Qué ganaste**: editar funciona de punta a punta y reusa el formulario, el modal y las reglas del alta.
Y mira el costo: esas reglas quedaron escritas dos veces, en `store()` y en `update()`, con la misma
pareja de cadenas; el capítulo 13 unifica los renders, no las reglas.

**Pieza — reusar el modal para alta y edición**: resuelve el formulario una sola vez y evita dos
pantallas con reglas que se desincronizan. Úsalo cuando los campos son los mismos. Sin esto, el alta y
la edición validan distinto y una de las dos se olvida de algo.

![La fila actualizada y el aviso «Nota actualizada.»](tutorial-crud-images/09-2-actualizada.png)

---

## 10. Borrar: del confirm del navegador al diálogo del proyecto

**Objetivo**: que borrar pida confirmación, entendiendo por qué el atajo del navegador no alcanza.

**Estado de partida**: paso 9 hecho; la papelera no hace nada.

### 10.1 Primero, el atajo

La papelera existe desde 8.2 y no hace nada: no hay ruta que la atienda. Borrar es la única operación del
recorrido que no tiene vuelta atrás, así que este paso la conecta de punta a punta con la confirmación
más barata que hay —la del navegador— antes de reemplazarla por una que diga qué se borra.

**Archivo**: `resources/views/pages/notas/_panel.twig`, dentro del `<li>` del `{% for %}`, en el lugar de la
papelera del paso 8.

**Acción**: envuelve la papelera en un formulario con el `confirm` del navegador:

```twig
<form method="post" action="{{ url('/notas/' ~ item.id ~ '/delete') }}"
      class="inline" onsubmit="return confirm('¿Borrar esta nota?')">
    {% include 'components/form/csrf.twig' %}
    {% include 'components/ui/button.twig' with {
        label: '',
        variant: 'ghost',
        size: 'sm',
        icon: 'trash',
        aria_label: 'Borrar ' ~ item.titulo,
        type: 'submit'
    } only %}
</form>
```

**Archivo**: `app/Http/Controllers/NotaController.php`, método `destroy()`.

**Acción**: agrega `destroy()`:

```php
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request, 'No existe una nota con ese identificador.');
        }

        $nota->delete();

        Flash::set('success', 'Nota borrada.');

        return $this->redirect($response, '/notas');
    }
```

**Comprobación**: toca la papelera. Aparece el cartel del navegador «¿Borrar esta nota?». Si aceptas,
la fila desaparece y el aviso «Nota borrada.» sale abajo a la derecha.

**Qué ganaste**: borrar funciona de punta a punta, y sabes exactamente por qué no alcanza. **Y sin
embargo no se queda**: `confirm()` es un cartel del sistema, no se puede estilar, no respeta el tema, no
dice **qué** nota estás borrando y bloquea el hilo del navegador. Por eso el proyecto usa un diálogo.

Sin captura: el `confirm()` es un diálogo del sistema y no se puede fotografiar desde el navegador; su texto es «¿Borrar esta nota?».

### 10.2 Ahora, el diálogo de confirmación

El atajo dejó la papelera envuelta en un `<form>`, así que el botón perdió el `data_modal_open` que el
paso 8.2 le había puesto. Recuperarlo no es sólo quitar el formulario: el diálogo tiene que existir una
vez por fila, con el id que el botón nombra, y vivir fuera de la lista sin descolocarla. Ese es el trabajo
del paso.

**Archivo**: `resources/views/pages/notas/_panel.twig`, dentro del `{% for %}`, después del `</li>` de la
fila.

**Acción**: desenvolvé la papelera y pon el diálogo justo después del `</li>`. El botón no se va: deja
de estar envuelto por el `<form>` y recupera `data_modal_open`, que es lo que el shell usa para abrir
el diálogo. La fila queda así:

```twig
            <li class="flex items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-3">
                <span class="font-semibold">{{ item.titulo }}</span>
                <div class="flex shrink-0 items-center gap-1">
                    {# … el lápiz de 8.2 … #}

                    {% include 'components/ui/button.twig' with {
                        label: '',
                        variant: 'ghost',
                        size: 'sm',
                        icon: 'trash',
                        aria_label: 'Borrar ' ~ item.titulo,
                        data_modal_open: 'nota-delete-' ~ item.id
                    } only %}
                </div>
            </li>
```

Y después de la `</ul>`, todavía dentro de `#notas-panel`, el diálogo. Va aquí y no dentro del
`<li>` porque un `<dialog>` no es hijo válido de una `<ul>`, y porque un hijo más del `flex` de la
fila la descoloca. Recorre las notas otra vez porque este bloque se emite una sola vez:

```twig
    </ul>
    {% for item in notas %}
            {% embed 'components/ui/modal.twig' with {
                id: 'nota-delete-' ~ item.id,
                title: 'Borrar nota'
            } %}
                {% block body %}
                    <p class="py-3 opacity-70">¿Borrar «{{ item.titulo }}»? No se puede deshacer.</p>
                    <form method="post" action="{{ url('/notas/' ~ item.id ~ '/delete') }}"
                          onsubmit="this.querySelector('[type=submit]').disabled = true">
                        {% include 'components/form/csrf.twig' %}
                        <div class="modal-action">
                            {% include 'components/ui/button.twig' with {
                                label: 'Cancelar',
                                variant: 'ghost',
                                type: 'button',
                                icon: 'x',
                                data_modal_close: true
                            } only %}
                            {% include 'components/ui/button.twig' with {
                                label: 'Borrar',
                                variant: 'error',
                                type: 'submit',
                                icon: 'trash'
                            } only %}
                        </div>
                    </form>
                {% endblock %}
            {% endembed %}
            {% endfor %}
```

La papelera ya apunta a ese diálogo con `data_modal_open`; el shell llama a `showModal()` al tocarla.
Ese `id` lleva el número de fila: un id fijo abriría siempre el mismo diálogo, con el título de otra
nota.

**Comprobación**: toca la papelera de una nota. Esperado: se abre un diálogo que dice **qué nota** vas
a borrar —«¿Borrar «Comprar pan»? No se puede deshacer.»—, con «Cancelar» y «Borrar». «Cancelar» no
hace nada; «Borrar» elimina la fila y muestra el aviso.

**Qué ganaste**: el texto dice qué se borra, el diálogo respeta el tema y el foco, y cancelar es una
acción explícita en vez de cerrar un cartel del sistema.

**Falla si**:

- La papelera no abre nada: el `data_modal_open` apunta a un id que no existe (el id lleva el número de fila).
- Cancelar borra igual: el botón de cancelar tiene que ser `type: 'button'`; con `submit` el formulario
  se envía.
- El borrado te devuelve al login con «Tu sesión expiró»: falta el `{% include 'components/form/csrf.twig' %}`
  dentro del formulario del modal. Sin HTMX el rechazo es un 303 al login; el 400 con `HX-Trigger` es
  sólo para disparadores HTMX.

**Qué no prueba**: que el borrado sea reversible, ni que exista borrado lógico. Borra la fila.

**Pieza — confirmación en un diálogo antes de mutar**: resuelve el «¿estás seguro?» con el idioma del
proyecto. Úsala en toda acción destructiva. Evita el borrado por accidente y el cartel del sistema que
no dice qué se borra. Sin esto, la papelera borra al primer toque y no hay vuelta atrás.

> Un diálogo por fila es más markup. Con decenas de filas, un modal único reusado por JS vuelve a
tener sentido.

![El diálogo que dice qué nota se borra](tutorial-crud-images/10-2-borrar.png)

---

## 11. Tests que reemplazan lo que hacías a mano

**Objetivo**: convertir en automático lo que venías comprobando a mano.

**Estado de partida**: pasos 1 a 10 hechos y comprobados en pantalla.

Ya sabes que la entidad funciona porque la usaste. Lo que no sabes es cuándo vas a romperla: un cambio
en el controlador, una ruta que se muda, un campo que deja de validarse. Los tests que vienen fijan esos
contratos, y se escriben en tres tandas para que cada una tenga un objetivo claro.

### 11.1 El alta y su rechazo

Los dos primeros tests cubren el camino que más se toca: crear y ser rechazado. El segundo es el que
importa de verdad, porque una validación que no repinta el modal es una validación que el usuario pierde.

**Acción**: crea `tests/Notas/Http/NotaTest.php`, una carpeta por entidad, con el esqueleto y los dos tests del alta:

```php excerpt
<?php

declare(strict_types=1);

namespace Tests\Notas\Http;

use App\Models\Nota;
use Tests\Support\HttpTestCase;

final class NotaTest extends HttpTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
        Nota::query()->delete();
    }

    protected function tearDown(): void
    {
        Nota::query()->delete();
        parent::tearDown();
    }

    private function crear(array $datos = []): Nota
    {
        // `$datos` primero: con el default a la izquierda, el `+` de PHP
        // descarta el título que pasa cada test y todas las filas se llaman
        // «De prueba». El test igual pasa si sólo comprueba el estado final.
        return Nota::create($datos + ['titulo' => 'De prueba']);
    }
    public function test_crea_una_nota_y_la_muestra_en_la_lista(): void
    {
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => 'Primera nota',
            'cuerpo'     => 'Contenido',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/notas', $response->getHeaderLine('Location'));
        self::assertSame(1, Nota::query()->count());

        $lista = self::app()->handle(self::request('GET', '/notas'));
        self::assertStringContainsString('Primera nota', (string) $lista->getBody());
        self::assertStringContainsString('aria-current="page">Notas', (string) $lista->getBody());
    }

    public function test_el_alta_invalida_reabre_el_modal_con_lo_tipeado(): void
    {
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => '',
            'cuerpo'     => 'Texto que quiero conservar',
        ]));
        $html = (string) $response->getBody();

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, Nota::query()->count());
        self::assertStringContainsString('Este campo es obligatorio.', $html);
        self::assertStringContainsString('Texto que quiero conservar', $html);
        self::assertMatchesRegularExpression('/nota-create-modal[^>]*open/', $html);
    }
}
```

**Comprobación**:

```bash
vendor/bin/phpunit --filter NotaTest
```

Esperado: dos tests en verde.

**Falla si**:

- El test falla en `Location` (`/login` en vez de `/notas`): el token no está llegando, aunque el
  primer `assertSame(303, …)` pase igual. Sin HTMX el rechazo de CSRF es un 303 al login, no un 400.
  `HttpTestCase::token()` te da el par.
- El test del modal falla en el `assertMatchesRegularExpression`: el atributo `open` no está saliendo;
  revisa `open_create_modal`.
- El test del alta pasa pero el de la lista no encuentra el título: el `GET /notas` no está viendo la
  fila que se creó, así que el problema está en la vista y no en el test.

**Qué no prueba**: que la pantalla se vea bien. Prueba el contrato HTTP del alta: código, datos guardados
y mensaje.

![La pantalla no cambia: el test comprueba lo que ya viste](tutorial-crud-images/08-2-acciones.png)

### 11.2 Editar y borrar

Los dos siguientes cubren las acciones por fila. Son más cortos porque el camino ya está montado: cada
uno comprueba qué se guarda y adónde se vuelve.

**Acción**: agrega estos dos métodos dentro de la clase.

```php
    public function test_edita_una_nota(): void
    {
        $nota = $this->crear(['titulo' => 'Antes']);
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas/' . $nota->id, [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => 'Después',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('Después', (string) Nota::find($nota->id)?->titulo);
    }

    public function test_borra_una_nota_con_confirmacion(): void
    {
        $nota = $this->crear();
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas/' . $nota->id . '/delete', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertNull(Nota::find($nota->id));
    }
```

**Comprobación**: `vendor/bin/phpunit --filter NotaTest` — cuatro tests en verde.

**Falla si**:

- El de edición falla en `Nota::find($nota->id)`: el `update()` no está guardando, o el `action` del
  formulario no apunta a `/notas/{id}`.
- El de borrado deja la fila: `destroy()` no se ejecutó, o la ruta `/{id}/delete` no está registrada.

**Qué no prueba**: que la edición y el borrado se vean como los diseñaste. Eso lo cubre el navegador.

![La pantalla no cambia: los tests cubren las acciones ya construidas](tutorial-crud-images/08-2-acciones.png)

### 11.3 El 404 y el cierre

El último test no escribe nada: comprueba que un identificador inexistente recibe una página, no un error
de PHP. Es el que evita que un enlace viejo tumbe la app.

**Acción**: agrega el quinto método.

```php
    public function test_avisa_cuando_el_identificador_no_existe(): void
    {
        $response = self::app()->handle(self::request('GET', '/notas/99999/edit'));
        $html = (string) $response->getBody();

        self::assertSame(404, $response->getStatusCode());
        self::assertNotSame('', $html, 'Un 404 mudo no avisa nada.');
        self::assertLessThan(4096, strlen($html), 'Un 404 no debería gastar la página de depuración.');
    }
```

**Comprobación**:

```bash
vendor/bin/phpunit
```

Esperado: la suite completa en verde, con cinco tests de `NotaTest`. En 13.5 se suma el sexto, el del
422 con `HX-Push-Url`.

**Falla si**:

- La suite completa falla después: falta el `tearDown` que limpia `notas`.
- El 404 llega con cuerpo vacío: el framework no está renderizando su página de error.

**Qué no prueba**: que la pantalla se vea bien, ni que los diálogos abran. Prueba el contrato HTTP:
qué código, qué guarda y qué mensaje. La comparación visual ya la hiciste paso a paso.

> **Checkpoint**: aquí tienes la entidad completa —crear, editar, borrar con confirmación y tests—. Si
> esto no funciona, lo que sigue no lo arregla.

**Pieza — `Tests\Support\HttpTestCase`**: resuelve el arranque de la app, la sesión inyectada, el
request con cuerpo de formulario y los tokens (`token()`). Úsalo como base de todos tus tests HTTP.
Evita tests que arman el entorno a mano y se rompen cuando cambia el bootstrap.

![La pantalla no cambia: el 404 y la suite cierran el capítulo](tutorial-crud-images/08-2-acciones.png)

---

## 12. Extraer la lista a un componente

**Objetivo**: sacar de tu panel el `<ul>`, el recorrido y el estado vacío. Te quedas sólo con la fila.

**Estado de partida**: paso 11 hecho; la entidad funciona y los tests están en verde.

Hasta aquí el `<ul>`, el recorrido y la fila viven en `_panel.twig`. Eso está bien mientras hay una
sola pantalla: se lee de arriba abajo y no esconde nada. El problema aparece cuando quieres repetir el
listado en otra entidad o cuando el estado vacío se desincroniza entre pantallas. El proyecto ya tiene
esa pieza resuelta: `components/list/simple.twig`.

### 12.1 Qué hace el componente

**Componente**: `resources/views/components/list/simple.twig`. Léelo, no lo edites: ya existe y es del
proyecto.

```twig
{# Lista simple con estado vacío.
   Props: items, empty, list_class, empty_class.
   El bloque `row` se renderiza una vez por item y recibe `item`; el markup de
   cada fila queda en el dominio que lo usa.
   El bloque `after` se emite DESPUÉS del `</ul>`: es donde van los diálogos de
   cada fila. Un `<dialog>` es hijo inválido de una `<ul>` —sólo admite `<li>` y
   elementos de soporte de script—, así que el componente reserva ese lugar en
   vez de obligar a meterlo adentro de la fila. #}
<ul class="{{ list_class|default('space-y-2') }}">
    {% for item in items %}
        {% block row %}{% endblock %}
    {% else %}
        <li class="{{ empty_class|default('opacity-60') }}">{{ empty|default('No hay registros todavía.') }}</li>
    {% endfor %}
</ul>
{% block after %}{% endblock %}
```

**Qué hace cada pieza**:

- `<ul class="{{ list_class|default('space-y-2') }}">`: el componente es dueño del contenedor.
  `list_class` es opcional; si no lo pasas, usa `space-y-2`. `default` **reemplaza** el valor, no lo
  fusiona: para agregar una clase tienes que repetir `space-y-2`.
- `{% for item in items %}`: recorre la colección que le pases. `item` es cada elemento.
- `{% block row %}{% endblock %}`: un bloque vacío es un punto de extensión. Ahí entra tu fila. Está
  **dentro del `for`**, así que tu fila ve el `item` de esa vuelta.
- `{% else %}`: pertenece al `for`. Se ejecuta cuando `items` está vacío, sin agregar un `{% if %}`
  alrededor de todo.
- `{% block after %}{% endblock %}`: se emite **después** del `</ul>`. Ahí van los diálogos por fila:
  un `<dialog>` no es hijo válido de una lista, así que el componente no te deja meterlo en la fila.
  Este bloque se pinta una sola vez y no recibe `item`; si necesitas uno por fila, recorre `items`
  tú.
- `{{ empty|default('No hay registros todavía.') }}` dentro de un `<li>`: el estado vacío ocupa una
  fila más de la lista; `empty_class` es su clase opcional.

El componente no sabe nada de `notas` ni de `titulo`: sólo sabe recorrer y dejar un hueco.

**Cómo se rellena el hueco**: ya lo hiciste con el modal. En 7.5 reemplazaste el `<dialog>` por
`components/ui/modal.twig` y escribiste un `{% block body %}`; el modal puso el resto. Aquí es el mismo
`embed`, con una diferencia: `row` se rellena **una vez por item**, porque el componente lo invoca
dentro de su `for`.

Para verlo sin el ruido de los botones, toma la fila mínima de 6.6:

```twig
<div id="notas-panel">
    <ul class="space-y-2">
        {% for item in notas %}
            <li class="rounded-box border border-base-300 bg-base-100 p-3">
                <span class="font-semibold">{{ item.titulo }}</span>
            </li>
        {% else %}
            <li class="opacity-60">No hay notas todavía.</li>
        {% endfor %}
    </ul>
</div>
```

El equivalente con el componente es:

```twig
<div id="notas-panel">
    {% embed 'components/list/simple.twig' with {
        items: notas,
        empty: 'No hay notas todavía.'
    } %}
        {% block row %}
            <li class="rounded-box border border-base-300 bg-base-100 p-3">
                <span class="font-semibold">{{ item.titulo }}</span>
            </li>
        {% endblock %}
    {% endembed %}
</div>
```

**Qué hace cada línea del `embed`**:

- `embed 'components/list/simple.twig'`: incluye el componente y te deja reemplazar sus bloques. Un
  `include` común no puede: copia el componente tal cual y el bloque `row` quedaría vacío.
- `with { items: notas, empty: '…' }`: pasa variables al scope del componente. `items` alimenta su
  `for`; `empty` es el texto de su `else`.
- `{% block row %}`: rellena el bloque vacío. Se renderiza **donde el componente lo invoca**, o sea
  dentro de `{% for item in items %}`; por eso `item` es la nota de esa vuelta.
- `{% endembed %}`: cierra el `embed`, no un `include`.

**La inversión**: compara los dos bloques. Producen el mismo HTML, pero en el segundo tu archivo no
tiene el `<ul>`, ni el `for`, ni el `else`, y aun así el `<li>` se repite una vez por nota. El
componente pone la estructura y el vacío; tú sólo describes una fila. Desconcierta la primera vez
porque la indentación sugiere que el `<li>` se pinta una vez, cuando en realidad se repite.

**Falla si** (cuando lo apliques):

- La lista aparece vacía aunque haya notas: usaste `include` en vez de `embed`, o el
  `{% block row %}` quedó fuera del `embed`.
- Las filas salen vacías: escribiste `{{ item }}` en vez de `{{ item.titulo }}`.
- Aparece «No hay registros todavía.» en vez de tu texto: `empty` no está llegando al componente.
- La lista no aparece: el nombre del componente no coincide con `components/list/simple.twig`.

**Qué ganaste**: sabes qué parte de tu fila va en cada bloque y por qué el `<li>` se repite sin un `for`
tuyo. El panel, mientras tanto, sigue con su propio `<ul>` y su propio estado vacío. Ese es el trabajo
que sigue.

![La pantalla no cambia: el componente se lee](tutorial-crud-images/08-2-acciones.png)

### 12.2 La fila al componente

**Archivo**: `resources/views/pages/notas/_panel.twig`.

**Antes**: el `<div id="notas-panel">` con el `<ul>`, el `{% for %}`, el `<li>` con los dos botones, el
recorrido de diálogos debajo de la lista y el `{% else %}` del vacío.

**Regla**: lo que está entre `<li>` y `</li>` —título y botones— va adentro de `{% block row %}`. Lo
único que desaparece es el `<ul>`, el `{% for %}`, el `{% else %}` y el `<li>` del vacío: eso ya lo pone
el componente. Los diálogos, por ahora, se quedan donde están.

**Después**: el panel queda así.

```twig
<div id="notas-panel">
    {% embed 'components/list/simple.twig' with {
        items: notas,
        empty: 'No hay notas todavía.'
    } %}
        {% block row %}
            <li class="flex items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-3">
                <span class="font-semibold">{{ item.titulo }}</span>
                <div class="flex shrink-0 items-center gap-1">
                    {% include 'components/ui/button.twig' with {
                        label: '',
                        variant: 'ghost',
                        size: 'sm',
                        icon: 'pencil',
                        aria_label: 'Editar ' ~ item.titulo,
                        href: url('/notas/' ~ item.id ~ '/edit')
                    } only %}

                    {% include 'components/ui/button.twig' with {
                        label: '',
                        variant: 'ghost',
                        size: 'sm',
                        icon: 'trash',
                        aria_label: 'Borrar ' ~ item.titulo,
                        data_modal_open: 'nota-delete-' ~ item.id
                    } only %}
                </div>
            </li>
        {% endblock %}
    {% endembed %}

    {% for item in notas %}
        {% embed 'components/ui/modal.twig' with {
            id: 'nota-delete-' ~ item.id,
            title: 'Borrar nota'
        } %}
            {% block body %}
                <p class="py-3 opacity-70">¿Borrar «{{ item.titulo }}»? No se puede deshacer.</p>
                <form method="post" action="{{ url('/notas/' ~ item.id ~ '/delete') }}"
                      onsubmit="this.querySelector('[type=submit]').disabled = true">
                    {% include 'components/form/csrf.twig' %}
                    <div class="modal-action">
                        {% include 'components/ui/button.twig' with {
                            label: 'Cancelar',
                            variant: 'ghost',
                            type: 'button',
                            icon: 'x',
                            data_modal_close: true
                        } only %}
                        {% include 'components/ui/button.twig' with {
                            label: 'Borrar',
                            variant: 'error',
                            type: 'submit',
                            icon: 'trash'
                        } only %}
                    </div>
                </form>
            {% endblock %}
        {% endembed %}
    {% endfor %}
</div>
```

`block row` es la única parte de tu archivo que se repite por nota. Si copias el
`<div id="notas-panel">` adentro del bloque, el id se duplica y HTMX deja de saber qué reemplazar.

**Comprobación**: la lista se ve **idéntica** a la del paso 10, con y sin notas; el lápiz abre el modal
de edición; la papelera abre el diálogo que dice **qué nota** vas a borrar; borrar elimina una sola nota;
`composer test` sigue en verde.

**Falla si**:

- Con notas, la lista se ve vacía: el `{% block row %}` quedó fuera del `embed` o el `embed` apunta a
  otro nombre.
- «No hay notas todavía.» no aparece: `empty` no está llegando.
- La fila se descoloca: el modal quedó dentro del `<li>` y entró en el `flex`.

**Qué no prueba**: que el HTML sea idéntico carácter por carácter. `composer test` prueba el contrato
HTTP —códigos, datos guardados, mensajes—, no el markup. La comparación visual la haces tú.

**Qué ganaste**: el panel ya no sabe cuántas filas hay ni qué pasa si no hay ninguna; sólo describe una
fila. La próxima entidad que hagas puede reusar el componente sin copiar el `<ul>` ni el vacío.

**Pieza — `components/list/simple.twig`**: resuelve el `<ul>`, el recorrido y el estado vacío. Úsalo
para todo listado simple. Evita repetir el mismo bucle en cada pantalla y olvidarte del vacío. Sin él,
cada entidad tiene su propia versión con variaciones accidentales.

![La pantalla no cambia: la lista viaja al componente](tutorial-crud-images/08-2-acciones.png)

### 12.3 Los diálogos a su bloque

El panel ya usa el componente, pero los diálogos siguen siendo un segundo recorrido escrito a mano justo
debajo. Eso funciona en esta pantalla y en ninguna más: quien reusa la lista tiene que acordarse de
repetirlo, y en el orden correcto. El componente reserva un bloque para eso.

**Archivo**: `resources/views/pages/notas/_panel.twig`.

**Antes**: el `{% for item in notas %}` con los `modal.twig`, después del `{% endembed %}`.

**Después**: el mismo recorrido, tal cual, adentro de `{% block after %}`, que el componente emite
después del `</ul>`. El bloque se pinta una sola vez y no recibe `item`, así que el `for` propio es
necesario.

```twig
        {% block after %}
            {% for item in notas %}
                {% embed 'components/ui/modal.twig' with {
                    id: 'nota-delete-' ~ item.id,
                    title: 'Borrar nota'
                } %}
                    {# … el cuerpo del modal, igual que en 10.2 … #}
                {% endembed %}
            {% endfor %}
        {% endblock %}
    {% endembed %}
</div>
```

**Por qué `after` y no la fila**: adentro del `<li>` el diálogo sería un hijo más del `flex` y
descolocaría la fila; además un `<dialog>` no es hijo válido de una `<ul>`. El bloque lo emite después
del cierre, así que el HTML queda válido y la fila no se entera.

**Comprobación**: la pantalla no cambia; la papelera sigue abriendo el diálogo de su nota y borrar
elimina una sola fila. Lo que cambió es de quién es el recorrido: ahora vive dentro del contrato de la
lista.

**Falla si**:

- La papelera no abre nada: el `for` quedó fuera del `embed` o perdió el `block after`.
- Aparecen dos juegos de diálogos: quedó el recorrido viejo además del nuevo.

**Qué ganaste**: el panel tiene ahora dos bloques y ninguna estructura propia. Quien reusa el componente
hereda también el lugar correcto para sus diálogos.

![La pantalla no cambia: los diálogos ya estaban fuera de la lista](tutorial-crud-images/08-2-acciones.png)

### 12.4 Enriquecer la fila

Con el componente en su lugar, la fila todavía muestra sólo el título. Y hay un dato que vienes
guardando desde el capítulo 7 y no se ve en ninguna parte: el `cuerpo`. Una nota con cuerpo y otra sin él
se ven igual, y eso es una pérdida silenciosa.

**Archivo**: `resources/views/pages/notas/_panel.twig`, dentro de `{% block row %}`.

**Antes**:

```twig
                <span class="font-semibold">{{ item.titulo }}</span>
```

**Después**: el título pasa a ser un enlace a editar —un blanco más grande que el lápiz, útil en el
móvil— y debajo aparece el cuerpo cuando existe.

```twig
                <div class="min-w-0">
                    <a class="font-semibold" href="{{ url('/notas/' ~ item.id ~ '/edit') }}">{{ item.titulo }}</a>
                    {% if item.cuerpo %}
                        <p class="text-sm opacity-70">{{ item.cuerpo }}</p>
                    {% endif %}
                </div>
```

El `min-w-0` no es decorativo: sin él, un título largo empuja los botones fuera de la tarjeta. El lápiz
se queda; los dos caminos llevan al mismo modal.

**Comprobación**: cada fila muestra el título como enlace y, si la nota tiene cuerpo, una línea más
debajo. Las notas sin cuerpo se ven igual que antes. El clic en el título abre el modal de edición, igual
que el lápiz.

**Falla si**:

- El cuerpo no aparece aunque la nota lo tenga: falta el `{% if item.cuerpo %}` o el campo no se guardó.
- Los botones se salen de la tarjeta: falta el `min-w-0` en el contenedor del título.
- El título no es un enlace: quedó el `<span>` anterior.

**Qué ganaste**: la fila dice todo lo que la nota tiene, y el título es un blanco de clic más generoso
que un icono de 16 píxeles.

![La fila final: título enlazado y cuerpo visible](tutorial-crud-images/12-4-fila-final.png)


---

## 13. Paginación del servidor

**Objetivo**: que la lista siga siendo usable cuando crezca.

**Estado de partida**: paso 12 hecho; con muchas notas la página se vuelve larga.

### 13.1 Paginar la consulta

La lista trae todo lo que hay en la tabla, y con cien notas la página tarda y el scroll no termina.
Recortar en el navegador no sirve: el HTML ya viajó. La paginación tiene que ocurrir en el servidor,
sobre la consulta, y este paso la agrega sin tocar todavía la vista.

**Archivo**: `app/Http/Controllers/NotaController.php`.

**Acción**: suma dos `use` —`Framework\Http\Pagination` y `Framework\Http\Url`, que es de donde sale
`Url::path()`— y centraliza la lectura en un método privado:

```php
    private function pageData(Request $request): array
    {
        $paginacion = Pagination::fromRequest(
            Nota::orderByDesc('id'),
            $request,
            Url::path('/notas')
        );

        return [
            'notas'       => $paginacion['items'],
            'pagination'  => $paginacion,
            'breadcrumbs' => $this->breadcrumbs('Notas', 'list'),
        ];
    }
```

`Pagination::paginate()` recibe la consulta, la página pedida, el tamaño y la URL base. Devuelve los
items de esa página y los datos que después usan los controles —incluido el total, que la paginación
muestra por su cuenta—. `pageData()` traduce eso a las variables que ya conoce la vista. `breadcrumbs` viaja acá y no en `index()` para que el parcial de
HTMX y los rechazos también lo lleven: si se quedara en `index()`, la región devuelta por HTMX perdería
la miga.

**Archivo**: el mismo controlador, método `index()`.

Ahora `index()` usa ese helper:

```php
    public function index(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'pages/notas/index.twig', $this->pageData($request) + [
            'active' => 'notas',
        ]);
    }
```

Todavía no toques la vista ni el helper de respuesta: primero comprobamos que el servidor recorta bien.

**Comprobación**: con más de diez notas, abre directamente `/notas?page=2&per_page=10`. Esperado: ves
las notas 11 a 20. `?page=1&per_page=25` muestra hasta 25; una página fuera de rango se ajusta a la
última. Los controles visuales todavía no están; eso es 13.2.

**Qué no prueba**: que los controles naveguen sin recargar. Prueba que la consulta y el recorte del
servidor son correctos.

**Falla si**:

- Ves todas las notas en una sola página: `index()` sigue usando `get()` o `Pagination` no recibe la
  consulta.
- El rango de la paginación no coincide: `Pagination` cuenta la tabla entera, no la página; si el
  número no cambia al moverte, la consulta que le pasaste ya venía recortada.
- Una página negativa o enorme rompe: `Pagination` normaliza, pero la página tiene que venir de la query,
  no de un valor fijo.

**Qué ganaste**: la consulta se recorta y el total es el de la tabla, no el de la página, así que la
paginación podrá decir la verdad cuando existan sus controles. Lo que falta se ve en pantalla: todavía no
están, y la única forma de cambiar de página es escribir la query a mano.

**Pieza — `Framework\Http\Pagination`**: resuelve conteo, recorte y página actual del lado del servidor.
Úsalo en toda lista que pueda crecer. Evita el `get()` pelado, que funciona con veinte filas y se cae
con dos mil.

![La página 2 pedida a mano: diez filas y todavía sin controles](tutorial-crud-images/13-1-pagina-2-sin-controles.png)

### 13.2 Pintar los controles

El servidor ya recorta, pero la vista no lo sabe: recibe diez notas y no tiene con qué pedir las
siguientes. Falta el paginador, y falta que viaje **dentro** del panel: cuando HTMX reemplace la región,
los controles tienen que venir con ella o quedan apuntando a un id que ya no se actualiza.

**Archivo**: `resources/views/pages/notas/_panel.twig`, dentro de `div#notas-panel`, después del `embed` y
antes de cerrar el `div`.

**Acción**: agrega los controles:

```twig
    {% endembed %}

    {% include 'components/pagination/simple.twig' with {
        pagination: pagination,
        target: '#notas-panel'
    } %}
</div>
```

**Pieza — `components/pagination/simple.twig`**: resuelve los controles, los estados deshabilitados y
el selector de tamaño. Úsalo con `pagination` y un `target` que apunte a la región que se va a
reemplazar. Evita rearmar los mismos botones en cada lista y desincronizar la página actual.

**Comprobación**: con más de diez notas, abre `/notas`. Esperado: diez filas y, debajo, «Mostrando 1 al
10 de N registros», el selector de tamaño y los botones Primera/Anterior/1/2/3/Siguiente/Última. En la
primera página, «Primera» y «Anterior» salen deshabilitados; con `?page=2&per_page=10` el mismo texto
pasa a «Mostrando 11 al 20 de N registros».

**Falla si**:

- No aparecen los controles: falta el `include` o no está dentro del `div` del panel.
- Los números no coinciden con las filas: no estás pasando `pagination` a la vista.
- Los controles aparecen pero al paginar desaparecen: quedaron fuera del parcial.

**Qué no prueba**: que paginar funcione. En este paso los controles son enlaces normales: cada clic
recarga la página entera, con el parpadeo y el scroll que eso trae. Eso se resuelve en 13.3.

![El paginador al pie del panel: rango, selector de tamaño y botones](tutorial-crud-images/13-2-paginador.png)

### 13.3 Responder sólo la región

Los controles ya existen, pero cada clic es una navegación completa: el servidor devuelve la página
entera, el navegador la repinta y volvés al tope. HTMX evita eso reemplazando sólo el panel, y para que
funcione la respuesta tiene que ser **la región**, no la página.

**Archivo**: el mismo controlador. Crea `renderList()`, que arma la página y decide qué devolver según
el pedido:

```php
    private function renderList(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        $data = $this->pageData($request);

        // Con HX-Request va SÓLO la región. Si devuelves la página entera, el
        // swap mete el shell del layout adentro del panel: dos sidebars, dos
        // headers y el contenido duplicado.
        $template = $request->hasHeader('HX-Request') ? 'pages/notas/_panel.twig' : 'pages/notas/index.twig';

        return $this->render($request, $response, $template, $data + $extra + [
            'active' => 'notas',
        ])->withStatus($status);
    }
```

`renderList()` **no es la acción de ruta**: es la utilidad que comparten `index()`, `edit()` y los dos
rechazos del formulario.

**Archivo**: el mismo controlador, método `index()`. Queda en una línea:

```php
    public function index(Request $request, Response $response): Response
    {
        return $this->renderList($request, $response);
    }
```

La clave está en `HX-Request`: un pedido normal recibe `index.twig` completo; un pedido de HTMX recibe
sólo `_panel.twig`. Si devuelves la página completa a HTMX, el panel se reemplaza por **otra página
completa** y el shell queda anidado: dos sidebars, dos headers y el contenido duplicado.

**Comprobación**: en `/notas`, toca «Siguiente». El rango pasa a «Mostrando 11 al 20 de N» y la página 2
queda marcada, **sin recarga**: el scroll se queda donde estaba. Cuenta en la **página entera** —un solo
`<header>` y dos `<aside>`, el sidebar y la nota del pie—: si ves dos headers y cuatro asides, el
servidor mandó la página completa y el shell quedó **al lado** del panel. Cuenta **elementos**, no
texto: hay un `<aside>` escrito dentro de un comentario de JS que no es parte del DOM.

La URL todavía **no** cambia: la barra sigue en `/notas`. Eso es el paso siguiente.

**Falla si**:

- Paginas y ves dos sidebars o dos headers: el servidor devolvió la página completa a un request de
  HTMX.
- El rango no cambia: `index()` no está usando `renderList()`, o los controles quedaron fuera del
  parcial.

**Qué no prueba**: que la URL del historial diga la página que estás viendo, ni que un 422 no la
empuje. Eso es 13.4.

![Después de «Siguiente»: el rango cambia y el shell no se duplica, pero la URL sigue en /notas](tutorial-crud-images/13-2-paginador-pagina-2.png)

### 13.4 La URL honesta

El swap funciona, pero la barra de direcciones quedó atrás: estás viendo la página 2 y la URL dice
`/notas`. Si recargás o compartís el enlace, volvés a la primera. HTMX puede empujar la URL que el
servidor sirvió, y sólo cuando la respuesta es una navegación real.

**Archivo**: el mismo `renderList()`.

**Acción**: después de renderizar, emite `HX-Push-Url` con la página que el **servidor** recortó; y no la
emitas en un error.

```php
        $rendered = $this->render($request, $response, $template, $data + $extra + [
            'active' => 'notas',
        ])->withStatus($status);

        if ($status >= 400) {
            return $rendered;
        }

        return $rendered->withHeader('HX-Push-Url', Url::path('/notas')
            . '?page=' . $data['pagination']['current_page']
            . '&per_page=' . $data['pagination']['per_page']);
```

El guard `status >= 400` deja esa decisión en un solo lugar: la URL del historial tiene que representar
una página que el usuario realmente navegó, no un 422 con errores.

**Comprobación**: toca «Siguiente» y mira la barra: pasa a `/notas?page=2&per_page=10`. Recarga: seguís en
la página 2. Prueba «Última»: la URL que queda es la última página real, no la que pediste. `?page=99`
escrito a mano no actualiza la barra —esa navegación es completa, y `HX-Push-Url` sólo viaja en las
respuestas de htmx—: ves la última página, pero la URL sigue diciendo `?page=99`.

**Falla si**:

- Paginas y la URL no cambia: falta el `withHeader`, o el estado no se pasa a `renderList()`.
- Un 422 empuja la URL: falta el guard, o el estado no viaja.
- La URL dice la página pedida y no la servida: estás usando la query del request en vez de
  `pagination.current_page`.

**Qué no prueba**: que un rechazo del formulario no cambie la URL. Eso se cierra con el test de 13.5.

**Pieza — `HX-Push-Url`**: resuelve la URL honesta después de un swap 2xx. Úsalo en toda región paginada
o filtrada. Evita que recargar o compartir el enlace te devuelva a otra página. No la emitas en un 422:
esa respuesta sólo repinta el formulario.

![La misma pantalla: ahora la barra de direcciones dice la página que el servidor sirvió](tutorial-crud-images/13-2-paginador-pagina-2.png)

### 13.5 Unificar los renders de edición y 422

Los renders del listado están repetidos en tres métodos, y la repetición ya tiene un costo concreto: cada
uno decide por su cuenta si empuja `HX-Push-Url`, si pasa `active` y en qué estado devuelve la página. Con
un solo camino, esa decisión vive en un lugar y el 422 no puede olvidarse de nada.

**Archivo**: `app/Http/Controllers/NotaController.php`, métodos `edit()`, `store()` y `update()`.

**Acción**: reemplaza los renders sueltos de `edit()` y de los rechazos por `renderList()`, pasando lo
propio en `$extra`:

```php
        // edit()
        return $this->renderList($request, $response, [
            'nota'            => $nota,
            'open_edit_modal' => true,
        ]);

        // rechazo del alta
        return $this->renderList($request, $response, [
            'errors'            => $validator->errors(),
            'form_values'       => $data,
            'open_create_modal' => true,
        ], 422);

        // rechazo de la edición
        return $this->renderList($request, $response, [
            'errors'          => $validator->errors(),
            'form_values'     => $data,
            'nota'            => $nota,
            'open_edit_modal' => true,
        ], 422);
```

En el rechazo de la edición, `'nota' => $nota` no es decorativo: `index.twig` envuelve el modal de
edición en `{% if nota is defined %}`. Sin esa clave el modal no se renderiza y lo tipeado aparece en
el modal de alta, que es el que queda abierto.

El `422` pasa por el mismo `renderList()`, pero el guard de estado evita `HX-Push-Url`. Para fijar ese
contrato, agrega a `tests/Notas/Http/NotaTest.php`:

```php excerpt
    public function test_un_422_no_empuja_la_url(): void
    {
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => '',
            'cuerpo'     => '',
        ]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('HX-Push-Url'));
    }
```

**Comprobación**: el test de 422 pasa; el alta inválida reabre su modal con errores; la edición inválida
reabre el modal de edición con la nota y lo tipeado; la paginación 2xx sigue empujando la URL.

**Qué no prueba**: que el orden sea el correcto entre páginas; lo define tu `orderBy`.

**Falla si**:

- Un 422 deja `HX-Push-Url`: el guard `status >= 400` no está o el estado no se pasa a `renderList()`.
- El modal de edición desaparece en un 422: falta `nota` en `$extra`.
- El alta inválida se abre en el modal de edición: sobra `nota` o `open_edit_modal` en el rechazo del
  alta.
- El test falla con 200: el título vacío no está llegando al validador o el token CSRF no es válido.

**Qué ganaste**: hay un método que arma la página, elige el parcial y decide la cabecera, y los tres casos
lo llaman con lo suyo. Lo que queda afuera es deliberado: las reglas de validación siguen en `store()` y
en `update()`, porque no son parte del render.

**Pieza — un solo camino de render**: resuelve la página, el parcial, el estado y la cabecera en un
único lugar. Úsalo en toda acción que renderice el listado. Evita que el alta, la edición y la
paginación tengan cada una su propia copia de `notas` y `active`.

---

## 14. Repetir el patrón con otra entidad

**Objetivo**: aplicar el mismo camino a una entidad propia.

**Estado de partida**: pasos 1 a 13 hechos.

El recorrido funcionó una vez, sobre `notas`. Lo que se repite no es esa entidad sino el patrón: qué
archivos aparecen siempre, qué cambia con el dominio y en qué orden se comprueban. Este capítulo lo deja
escrito para que la segunda entidad no te obligue a releer los trece anteriores.

| Qué cambia | Qué no cambia |
|---|---|
| El nombre de la entidad y de la tabla | El orden de los pasos |
| Los campos y sus reglas de validación | Los modales de alta, edición y borrado |
| El contenido de la fila | Las acciones por fila con nombre accesible |
| Los textos visibles | La lista en `components/list/simple.twig` |
| Los datos de los tests | La paginación del servidor y `HX-Push-Url` |
| La cantidad de registros de prueba | El encabezado en `components/ui/page-header.twig` |
| El icono y el subtítulo del encabezado | Los seis casos del cierre |

**Los archivos a tocar**, en orden:

1. `database/migrations/NNN_create_<entidad>_table.php`. El número lo asigna
   `composer make:migration Create<Entidad>Table`: la app usa el rango bajo contiguo y los demos
   viven desde `900`, así que nunca choca con un scaffold aunque el demo no esté instalado.
2. `app/Models/<Entidad>.php`
3. `routes/<entidad>.php` (el arranque lo carga solo, con `->add(RequireAuthMiddleware::class)` en el
   grupo; sin eso tu CRUD queda público) y `config/navigation/<entidad>.php`
4. `app/Http/Controllers/<Entidad>Controller.php`
5. `resources/views/pages/<entidad>/index.twig`, `_panel.twig`, `_form.twig`
6. `tests/<Entidad>/Http/<Entidad>Test.php`, con `namespace Tests\<Entidad>\Http`

Para el icono del encabezado y del menú usa un nombre del registro compartido —los de
`resources/views/components/ui/icon.twig`, que ya incluyen `list`, `contacts`, `pencil` y `trash`—: un nombre
que no existe no rompe nada pero **no pinta nada**, ni el svg del `<h1>` ni el del sidebar. Si necesitas
un glifo nuevo, agrégalo ahí primero.

**Sobre el demo como punto de comparación**: `composer demo:notas:install` instala una entidad `notas`
con el mismo nombre de tabla y la misma clase de migración que acabas de escribir, así que **no convive
con tu versión**: el instalador aborta porque `routes/notas.php`, `config/navigation/notas.php`,
`tests/Notas/Http/NotaTest.php` y las vistas ya existen, y con `composer demo:notas:install -- --force`
copia los archivos y la migración aborta igual, porque Phinx no puede declarar dos veces la clase
`CreateNotasTable` —la tuya y la del demo—. Para comparar, usa un clon limpio sin la entidad —o el demo
antes de empezar—: compara comportamiento, no ubicaciones, porque el demo agrupa el código en
`app/Notas/` y el tuyo queda en `app/Models` y `app/Http/Controllers`. Es la misma entidad, no dos que
puedan estar juntas.

**Comprobación**: elige otra entidad (por ejemplo `libros`, con `titulo` y `anio`) y recorre esa lista.
Esperado: al terminar, la pantalla tiene botón de alta en un modal, acciones por fila, borrado con
confirmación, paginación y seis tests en verde.

**Falla si**:

- El modal de borrado no abre: `data_modal_open` apunta a un id sin el número de fila.
- El alta inválida no reabre el modal: falta pasar `open_create_modal`.
- La paginación deja de funcionar después del primer guardado: el `div` del panel quedó fuera del parcial.

**Qué no prueba**: que el patrón sirva para entidades con relaciones, permisos por fila o adjuntos.
Eso es otra conversación, y necesita más piezas.

**Qué ganaste**: tienes el orden, los archivos y las piezas fijas, así que la segunda entidad es cuestión
de reemplazar nombres y campos en vez de decidir de nuevo. Y tienes una salida cuando algo no encaja: la
tabla de problemas frecuentes nombra el síntoma y el paso donde se arregla.

---

## Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| El POST te devuelve al login con «Tu sesión expiró» | Falta el token de CSRF en el formulario | `{% include 'components/form/csrf.twig' %}` dentro del `<form>` |
| El login vuelve al formulario con «Credenciales inválidas.» | Email o contraseña no coinciden con el admin sembrado | Vuelve a sembrar con las mismas variables; el mensaje es genérico a propósito |
| `no such table` | Migración no aplicada en la base que usa la app | `vendor/bin/phinx status` y revisa `DB_DATABASE` |
| El aviso de éxito no aparece | Tu layout no pinta toasts (no estás usando el shell) | Usá `layouts/app.twig`, que es el que los pinta |
| El modal abre cerrado tras un rechazo | No estás pasando `open_create_modal` / `open_edit_modal` | Pásalo en `true` en el render del 422 (pasos 7.6 y 9.2) |
| El error no aparece junto al campo | El include del `field` usa `only` y no recibe `error` | Pasa `error: errors.<campo>|default(null)` |
| El alta vuelve al login sin ningún aviso | El `include` de `_form.twig` lleva `only` y los campos CSRF salen vacíos | Quita el `only` de ese include: el parcial necesita el contexto de CSRF del `render()` |
| El panel desaparece tras el primer guardado | El `div` con el id quedó en `index.twig` | Muévelo adentro del parcial (paso 6.6) |
| La papelera no abre el diálogo | El `data_modal_open` apunta a un id sin el número de fila | Usa `nota-delete-{id}` en el modal y en `data_modal_open` |
| La lista se ve vacía aunque haya notas | El componente se incluyó con `include` y el bloque `row` no se rellena | Usa `embed` y pon la fila en `{% block row %}` (capítulo 12) |
| La papelera no abre nada | El modal quedó fuera del bloque `after` | Muévelo a `{% block after %}`, que el componente emite después del `</ul>` (paso 12.3) |
| La lista trae todo | La consulta no pasa por `Pagination::fromRequest()` | Paso 13.1 |
| Paginas y la URL no cambia | Falta la cabecera `HX-Push-Url` | Paso 13.4 |
| Editar un id inexistente da 500 | No se lanza `HttpNotFoundException` | Paso 9.1 |

## Qué demuestra este recorrido

Que agregar una entidad a este framework tiene un orden, y que ese orden tiene comprobaciones. Las
piezas que usaste —migraciones, modelo, archivo de rutas, `render()`, validador, CSRF, `layouts/app.twig`,
`components/list`, `components/form`, `components/ui/{button,modal,page-header}`, paginación, `HttpTestCase`— no
son opcionales: son el idioma del proyecto.

Y que ese idioma se aprende por contraste: cada pieza apareció primero en su versión básica y después
en la del proyecto, con lo que se gana y lo que se evita. El HTML crudo, el `<dialog>` nativo, el
`confirm()` del navegador y los emojis sin nombre no son pasos en falso: son la razón por la que los
componentes existen.

Lo que queda pendiente y por qué: relaciones entre entidades (necesitan otra conversación), permisos
por fila y adjuntos (más piezas), y despliegue (tiene su propio material).
