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
12. [Paginación del servidor](#12-paginación-del-servidor)
13. [Repetir el patrón con otra entidad](#13-repetir-el-patrón-con-otra-entidad)

Cada pieza de UI aparece dos veces: primero en su versión básica —HTML crudo, `<dialog>` nativo,
`confirm()` del navegador, iconos sin nombre— y después sustituida por el componente del proyecto, con
lo que se gana y lo que se evita. Los pasos 1 a 11 dejan la entidad completa; el 12 la vuelve usable
cuando crece, y el 13 te deja el patrón para otra entidad tuya.

**¿Quieres ver el resultado antes de empezar?** El mismo recorrido existe como demo instalable:
`composer demo:notas:install` (se quita con `composer demo:notas:remove`). Sirve para comparar, no para
aprender: el demo te da el código hecho, el recorrido te hace escribirlo. La diferencia concreta es de
nombres —el demo vive en `App\Notas\…` porque tiene que ser removible; el recorrido usa
`App\Models\Nota` y `App\Http\Controllers\NotaController`, que es donde va una entidad tuya—. El
comportamiento y las vistas son los mismos.

## 1. Arrancar la app hasta tener sesión

**Objetivo**: pasar de un clon sin configurar a una app respondiendo, con sesión iniciada.

**Estado de partida**: el repositorio clonado, terminal en la raíz del proyecto.

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

```bash
vendor/bin/phinx seed:run -s DatabaseSeeder
```

¿Por qué en `.env` y no en el comando? Porque las vas a necesitar otra vez —después de
`phinx rollback -t 0`, o al reinstalar un demo— y así no quedan en el historial del shell. La
contraseña tiene que tener 8 caracteres o más.

Si prefieres que no toquen el disco, el seeder también lee el entorno real:

```bash
ADMIN_EMAIL=dev@ejemplo.com ADMIN_PASSWORD=una-clave-de-8-o-mas \
  vendor/bin/phinx seed:run -s DatabaseSeeder
```

> Esa forma multilínea es de bash: en PowerShell no funciona (usa `$env:ADMIN_EMAIL = "..."` antes del
> comando). Y mientras corre el proceso, las variables inline son visibles en `ps`. Si están en los dos
> lados, gana el entorno real.

Y levanta el servidor de desarrollo:

```bash
php -S 0.0.0.0:8080 -t public
```

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

---

## 2. La migración de `notas`

**Objetivo**: crear la tabla. Sin tabla, el modelo no puede guardar nada.

**Estado de partida**: el paso 1 hecho; `composer migrate` corre sin errores.

**Acción**: mira qué números de migración están libres:

```bash
ls database/migrations/
```

Vas a ver archivos como `002_create_users_table.php` y `003_create_remember_tokens.php`. La
convención del proyecto es **numeración secuencial**, no timestamps, así que toma el siguiente
número libre. Si el último es `006_...`, tu archivo es `007_create_notas_table.php`:

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
`20261112180349_create_notas_table.php` con el esqueleto y el `change()` vacío. Sirve, pero su versión
es un **timestamp**, y este proyecto usa numeración secuencial porque los timestamps saturan el entero
de 32 bits en bases grandes. Así que, si lo usas, renombralo a `007_create_notas_table.php`: la versión
sale del prefijo del archivo y el nombre de la clase no cambia.

Por eso el recorrido crea el archivo a mano: son veinte líneas, y el nombre —que es lo que Phinx toma
como versión— queda bien desde el principio. Si algún día armas un demo instalable, el generador acepta
`--path` para escribir dentro de `database/migrations/<demo>/`.

**Comprobación**: ejecuta `vendor/bin/phinx status` (o vuelve a `composer migrate`). Tienes que ver tu
migración listada como aplicada, sin pendientes.

**Falla si**:

- Phinx dice que ya existe una migración con ese número: alguien usó ese número mientras mirabas.
  Elige el siguiente libre, nunca reutilices uno.
- El error menciona `notas` ya existente: la tabla quedó de un intento anterior. `vendor/bin/phinx
  rollback -t 0` la baja, y vuelves a subir.

**Qué no prueba**: que el modelo use esta tabla ni que los campos alcancen. La migración sólo prueba
que el esquema se puede crear y revertir.

> **Por qué `titulo` no es `NOT NULL`**: si miras la tabla vas a ver `VARCHAR(120)` que acepta nulos.
> La obligatoriedad la impone el validador (paso 7), no la base. Es la convención del proyecto: el
> mensaje de error lo da la capa de validación, que es la que puede explicarle al usuario qué
> corregir.

**Pieza — migraciones Phinx con numeración secuencial**: resuelven el versionado del esquema sin
depender de un ORM que lo adivine. Usalas siempre que cambie el esquema. Evitan el problema de los
timestamps, que saturan enteros de 32 bits en bases grandes. Sin esta pieza tendrías que aplicar SQL
a mano y acordarte del orden; el proyecto ya tiene el mecanismo, sólo hay que escribir el archivo.

---

## 3. El modelo `Nota`

**Objetivo**: poder leer y escribir la tabla desde PHP sin SQL escrito a mano.

**Estado de partida**: paso 2 hecho, la tabla existe.

**Acción**: crea `app/Models/Nota.php`:

```php
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

Esperado: `Lint OK (N archivos)`, con un archivo **más** que en la corrida anterior a crear el
modelo.

**Falla si**:

- El lint reporta un error de sintaxis: revisa llaves y `;`.
- El total de archivos no subió respecto de la corrida anterior: el archivo no está donde el lint
  mira. Anota el total antes de crearlo y compara.

**Qué no prueba**: que el modelo conecte con la tabla. El lint sólo verifica sintaxis; la prueba real
llega en el paso 7, cuando creas la primera nota. Si el modelo apunta a una tabla inexistente, el
error aparece ahí, con `no such table: notas`.

> El modelo sí se puede comprobar desde la terminal: `php -r 'require "bootstrap/app.php"; echo
> App\Models\Nota::query()->count(), PHP_EOL;'` imprime `0` sin avisos.

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

**Acción**: el archivo de rutas de tu entidad, `routes/notas.php`:

```php
<?php

declare(strict_types=1);

use Framework\Http\Middleware\RequireAuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app) {
    $app->group('/notas', function ($group) {
        $group->get('', function (Request $request, Response $response): Response {
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

**Por qué no esperar un error**: un 500 también aparece cuando el problema está en otro lado —el `.env`,
la base, una migración sin correr—, así que no distingue «la ruta quedó registrada» de «el proyecto está
roto». Un 200 con un texto que solo puede salir de esta ruta no se confunde con nada.

**Falla si**:

- Ves la **página 404 del framework**: el archivo no está directamente en `routes/`, no termina en
  `.php`, o su `return function (App $app) { … };` no está devolviendo el closure.
- Ves el login en vez del texto aunque hayas iniciado sesión: la sesión se perdió. Vuelve al paso 1.
- Llegas al texto por otra URL: el patrón del `group('/notas')` no coincide con la que estás abriendo.

**Qué no prueba**: que la ruta haga algo útil. Prueba el registro y la protección; el controlador llega
en el paso 5.

**Convenciones que este paso deja claras**:

- **El nombre del archivo no importa.** El arranque hace `glob('routes/*.php')` y los carga todos. Se
  llama `notas.php` por legibilidad —un archivo por entidad—, no porque el framework lo busque por
  nombre: un `routes/mi-entidad.php` responde igual.
- **La ubicación sí importa.** Sólo el primer nivel de `routes/`: un archivo en `routes/sub/` nunca se
  carga, y su ruta da 404 sin ningún aviso.
- **El orden de carga es alfabético**, con `web.php` forzado primero. Importa sólo si dos archivos
  declaran la misma ruta y método.
- **Cada archivo devuelve un closure** que recibe la app: `return function (App $app) { … };`. Si no
  devuelve uno, el archivo se carga y no registra nada, sin error.

**Pieza — un archivo de rutas por entidad, cargado por convención**: resuelve la separación entre «la
ruta existe» y «el arranque de la app». Cada entidad tiene su archivo y el arranque los levanta solo.
Evita meter rutas de dominio dentro de `bootstrap/app.php`, que es arranque del framework y no tuyo.
Sin esta pieza tendrías todas las rutas del proyecto en un archivo que crece sin límite.

**Pieza — `RequireAuthMiddleware`**: resuelve la puerta de entrada a las rutas privadas. Se aplica al
grupo, no ruta por ruta, para que no se te olvide ninguna. Evita el clásico «me olvidé de proteger el
POST». Sin él, `curl` podría crear filas sin sesión.

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

La carpeta no tiene archivos versionados en el repo: la crea la instalación de un demo, y al quitarlo
se borra si queda vacía. No está en `.gitignore`, así que la entrada que agregues se versiona junto
con tu entidad.

Ahora el archivo, `config/navigation/notas.php`:

```php
<?php

declare(strict_types=1);

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

**Falla si**:

- No aparece: falta el directorio `config/navigation/`, el archivo no termina en `.php`, o le falta
  alguna de las claves `label`, `href` o `key`.
- Aparece y al tocarlo da 404: `href` no coincide con el grupo de tu archivo de rutas.

**Qué no prueba**: que la pantalla exista. Probaste que la ruta responde (4.1) y que hay un camino
hasta ella (4.2); lo que se ve al llegar es el paso 6.

**Convenciones que este paso deja claras**:

- **El nombre del archivo no importa.** El contenedor hace `glob('config/navigation/*.php')` y lee cada
  uno; la entrada se identifica por su clave `key`, no por el nombre del archivo. Se llama `notas.php`
  porque un archivo por módulo se lee mejor.
- **La `key` sí importa**: es el valor que la página declara como `active` para que el sidebar la marque.
- **`position` ordena**, no el orden alfabético ni el nombre del archivo.

**Pieza — convención de navegación**: cada módulo declara su entrada en `config/navigation/<módulo>.php`
y el contenedor las descubre. Evita editar `sidebar.twig` y `container.php` para cada entidad —que
además están copiados en la documentación del framework y obligarían a `docs:sync`—. Agregar una
entidad es dejar su archivo; quitarla es borrarlo.

> Si tu entidad es además un **demo instalable**, el mismo archivo lleva `summary` (el texto de la
> tarjeta en la portada) y el nombre del demo, y la portada la lista con su estado *Instalado* u
> *Opcional*. Para una entidad de tu app alcanza con `label`, `href`, `icon`, `key` y `position`.

---

## 5. La primera ruta: JSON verificable

**Objetivo**: comprobar que tu ruta responde algo, antes de involucrar vistas y formularios.

**Estado de partida**: paso 4 hecho; `/notas` responde el texto de prueba `notas: ruta registrada`.

**Acción**: ahora el controlador. Crea `app/Http/Controllers/NotaController.php` con una sola acción:

```php
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

return function (App $app) {
    $app->group('/notas', function ($group) {
        $group->get('', [NotaController::class, 'index']);
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

---

## 6. La pantalla del listado: del HTML crudo al shell

**Objetivo**: llegar a la pantalla del listado entendiendo qué aporta cada pieza, empezando por lo
básico.

**Estado de partida**: paso 5 hecho; `/notas` devuelve el JSON de prueba y el menú ya muestra la entrada. Saca esa ruta: `index()` pasa
a renderizar una vista.

### 6.1 Primero, un HTML cualquiera

**Acción**: crea `resources/views/notas/index.twig` con HTML suelto, sin nada del proyecto:

```twig
<!DOCTYPE html>
<html lang="es">
<head><title>Notas</title></head>
<body>
    <h1>Notas</h1>
    <ul>
        {% for nota in notas %}
            <li>{{ nota.titulo }}</li>
        {% else %}
            <li>No hay notas todavía.</li>
        {% endfor %}
    </ul>
</body>
</html>
```

Y el controlador. Suma `use App\Models\Nota;` arriba, junto a los otros `use`: desde aquí la clase se usa
y sin el import el error es `Class "App\Http\Controllers\Nota" not found`, que parece un problema de
rutas y no lo es.

```php
    public function index(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'notas/index.twig', [
            'notas' => Nota::orderByDesc('id')->get(),
        ]);
    }
```

**Comprobación**: abre `http://localhost:8080/notas`. Vas a ver el título y el texto «No hay notas
todavía.», **sin sidebar, sin header, sin tema oscuro y sin nada de la aplicación alrededor**. Es una
página suelta.

**Qué no prueba**: nada del proyecto. Prueba que tu ruta, tu controlador y tu vista se hablan.

### 6.2 Ahora sí, el shell del proyecto

**Acción**: reemplaza todo el archivo por esto:

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Notas{% endblock %}

{% block content %}
    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-4">
            <h1 class="text-2xl font-bold">Notas</h1>
            <ul class="space-y-2">
                {% for nota in notas %}
                    <li class="rounded-box border border-base-300 bg-base-100 p-3">
                        <span class="font-semibold">{{ nota.titulo }}</span>
                    </li>
                {% else %}
                    <li class="opacity-60">No hay notas todavía.</li>
                {% endfor %}
            </ul>
        </div>
    </section>
{% endblock %}
```

**Comprobación**: recarga `/notas`. Ahora está el sidebar —con tu usuario al pie, como en el paso 1—,
el header, el tema y el contenido dentro de una tarjeta. **El `<body>`, el `<head>` y los assets ya no
son tuyos.**

**Qué no prueba**: que los modales y los avisos funcionen; eso lo da el mismo layout, más adelante.

**Pieza — `layouts/app.twig`**: resuelve el armado del shell completo (sidebar, header, avisos, tema,
assets con SRI) una sola vez. Úsalo en toda página tuya y escribe sólo el bloque `content`. Evita
rearmar el shell en cada pantalla y quedar desincronizado del resto. Sin él, cada vista repetiría el
header, y el tema se rompería en la que te olvidaras.

### 6.3 Sustituir la lista a mano por el componente

La lista de arriba funciona, pero el `{% for %} … {% else %}` es exactamente el markup que el proyecto
ya tiene empaquetado. Es el momento de reemplazarlo.

**Acción**: deja el parcial envolviéndose a sí mismo, `resources/views/notas/_panel.twig`:

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

En `index.twig`, cambia el `<ul>` por el include, **sin** envolverlo:

```twig
            {% include 'notas/_panel.twig' %}
```

El `div` con el id va adentro del parcial por una razón concreta: cuando más adelante el panel se
intercambie con HTMX y `outerHTML`, la respuesta **reemplaza** al elemento apuntado. Si el `div`
viviera en `index.twig`, el primer swap se lo llevaría junto con su id, y todo lo que apunte a
`#notas-panel` quedaría apuntando a algo que ya no existe.

**Comprobación**: la lista se ve **idéntica** a la de 6.2, con y sin notas.

**Qué ganaste**: el estado vacío, el `<ul>` y el recorrido ya no están en tu archivo; tu bloque dice
sólo cómo se ve **una fila**. Y cuando en el paso 12 entre la paginación, el mismo componente la
respeta sin cambios.

**Falla si**:

- La lista aparece vacía teniendo notas: `items` no está llegando; revisa el nombre de la variable.
- La fila sale con el título en blanco, sin error: dentro del bloque `row` la variable se llama
  `item`, no `nota`, y Twig imprime vacío en vez de protestar.

**Pieza — `components/list/simple.twig`**: resuelve el `<ul>`, el recorrido y el estado vacío. Úsalo
para todo listado simple. Evita repetir el mismo bucle en cada pantalla y olvidarte del vacío. Sin él,
cada entidad tiene su propia versión con variaciones accidentales.

### 6.4 El encabezado con la acción y el contador

**Acción**: en `index.twig`, reemplaza el `<h1 class="text-2xl font-bold">Notas</h1>` que dejó el paso
6.2 —el bloque de abajo ya trae ese mismo `h1`— y deja esto arriba del include del panel:

```twig
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Notas</h1>
                    <p class="mt-1 text-sm opacity-70">Tus notas, con alta y acciones por fila.</p>
                </div>
                <button class="btn btn-primary gap-2" onclick="document.getElementById('nota-create-modal').showModal()">
                    Agregar nota
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">Listado</h2>
                <span class="badge badge-ghost">{{ total }} notas</span>
            </div>
```

Y suma el total en el controlador:

```php
            'total'  => Nota::query()->count(),
```

**Comprobación**: aparecen el título, el botón «Agregar nota» a la derecha, el badge «0 notas» y la
lista vacía. El botón todavía no abre nada: el modal es el paso 7.

**Falla si**:

- El badge dice 0 con filas en la tabla: `total` no está llegando a la vista.
- El botón aparece sin estilo: la clase `btn` no existe en el bundle, o el layout no está cargando los assets.

**Qué no prueba**: que el botón haga algo, ni que la fila tenga acciones. Eso son los pasos 7 y 8.

---

## 7. El alta: del formulario crudo al modal del proyecto

**Objetivo**: crear notas desde un diálogo, entendiendo qué aporta cada componente en el camino.

**Estado de partida**: paso 6 hecho; el botón «Agregar nota» existe y no abre nada.

### 7.1 Un diálogo nativo con campos a mano

**Acción**: al final de `index.twig`, **dentro del bloque `content`** y después del `</section>`, un
`<dialog>` nativo con el formulario a mano.

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
                <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('nota-create-modal').close()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear nota</button>
            </div>
        </form>
    </div>
</dialog>
```

Y `store()` en el controlador. Vas a necesitar tres `use` más arriba del archivo, junto a los que ya
están:

```php
use Framework\Http\Flash;
use Framework\Http\Url;
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
            return $this->render($request, $response, 'notas/index.twig', [
                'notas'  => Nota::orderByDesc('id')->get(),
                'total'  => Nota::query()->count(),
                'active' => 'notas',
            ])->withStatus(422);
        }

        Nota::create([
            'titulo' => (string) $data['titulo'],
            'cuerpo' => $data['cuerpo'] !== '' ? $data['cuerpo'] : null,
        ]);

        Flash::set('success', 'Nota creada.');

        return $response->withStatus(303)->withHeader('Location', Url::path('/notas'));
    }
```

Y la ruta que la atiende, en `routes/notas.php`, debajo del `get`:

```php
        $group->post('', [NotaController::class, 'store']);
```

Se registra aquí, con su acción, y no en el paso 4: una ruta sin acción es una promesa que el framework
no puede cumplir —y el error aparece recién cuando alguien la visita—.

Y para que el aviso llegue en una pantalla con redirect, el controlador opta por el toast del shell:

```php
    protected function usesToastFlash(): bool
    {
        return true;
    }
```

**Comprobación**: toca «Agregar nota»: se abre el diálogo nativo. Guarda con un título: vuelves a
`/notas`, la nota aparece y el aviso «Nota creada.» sale abajo a la derecha.

**Qué no prueba**: que el error quede donde corresponde. Si el POST falla la validación, este
formulario repinta la página y **pierde lo tipeado y el modal abierto**.

### 7.2 Sustituir los campos a mano por el del proyecto

**Acción**: cambia los dos pares `label` + `input` por el componente, que trae label, error asociado y
atributos de accesibilidad:

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

Cambia también los dos `hidden` de CSRF por el partial que los emite:

```twig
            {% include 'partials/_csrf.twig' %}
```

**Comprobación**: los campos quedan en el mismo lugar y el input mide lo mismo, pero el label pasa a
`label.fieldset-legend` (12px semibold en vez de 16px normal) y el HTML ahora es un `fieldset` con su
label asociado por `for`/`id`. El campo opcional dice «(opcional)» y se mantienen `required` y
`maxlength`. Mira el HTML con el inspector: `label for` coincide con el `id` del input.

> Los atributos `aria-invalid` y `aria-describedby` que agrega el componente **recién se ven cuando hay
> error**, y eso llega en el paso 7.3, cuando el controlador empieza a devolver `errors`. En este paso
> el formulario todavía no tiene cómo producirlos.

**Qué ganaste**: el campo como una unidad con su label, su marca de opcional y el lugar donde irá el
mensaje. Eso antes no existía: tenías un `label` y un `input` sueltos que se podían desincronizar.

**Pieza — `components/form/field.twig`**: resuelve el campo con su label, su mensaje y sus atributos
de accesibilidad. Úsalo en vez de `<input>` sueltos. Evita el error que no queda pegado al campo que lo
causó. Con `error`, el input sale con `aria-invalid` y `aria-describedby` apuntando al mensaje.

**Pieza — `partials/_csrf.twig`**: resuelve los dos campos ocultos del token. Va **dentro** de todo
`<form>` que mute datos. Evita el rechazo con redirect al login que no explica nada.

### 7.3 Sustituir el `<dialog>` crudo por el modal del proyecto

El diálogo de 7.1 funciona, pero no reabre con un error del servidor y no tiene el tratamiento de tema
y telón del proyecto. Ese es exactamente el problema que resuelve el componente.

**Acción**: primero extrae el formulario a `resources/views/notas/_form.twig`:

```twig
{# `form_values` gana sobre la nota guardada: en un rechazo se repinta lo que el
   usuario tipeó, no lo que hay en la base. #}
{% set values = form_values|default(nota|default(null)) %}

<form method="post" action="{{ action }}" class="space-y-4"
      onsubmit="this.querySelector('[type=submit]').disabled = true">
    {% include 'partials/_csrf.twig' %}

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
            onclick: "document.getElementById('" ~ cancel_target ~ "').close()"
        } only %}
        {% include 'components/ui/button.twig' with {
            label: submit_label|default('Guardar'),
            type: 'submit',
            icon: submit_icon|default('check')
        } only %}
    </div>
</form>
```

Y en `index.twig`, reemplaza el `<dialog>` completo por:

```twig
{% embed 'components/ui/modal.twig' with {
    id: 'nota-create-modal',
    title: 'Nueva nota',
    subtitle: 'Pon un título; el cuerpo es opcional.',
    open: open_create_modal|default(false)
} %}
    {% block body %}
        {% include 'notas/_form.twig' with {
            action: url('/notas'),
            submit_label: 'Crear nota',
            submit_icon: 'plus',
            cancel_target: 'nota-create-modal',
            nota: null
        } %}
    {% endblock %}
{% endembed %}
```

`nota: null` no es decorativo: `_form.twig` cae a `nota` cuando no hay `form_values`, y en
`/notas/{id}/edit` el modal de alta se abriría con los datos de la nota que estás editando.

Por último, el alta inválida reusa el mismo render con el modal abierto y lo tipeado:

```php
        if ($validator->fails()) {
            return $this->render($request, $response, 'notas/index.twig', [
                'notas'             => Nota::orderByDesc('id')->get(),
                'total'             => Nota::query()->count(),
                'errors'            => $validator->errors(),
                'form_values'       => $data,
                'open_create_modal' => true,
                'active'            => 'notas',
            ])->withStatus(422);
        }
```

> **Sin `only` en ese include, a propósito.** `only` aisla el contexto: si lo usas, `_form.twig` se queda
> sin `csrf_name_key`, `csrf_value_key`, `errors` ni `form_values`, y el token sale con `name=""`. El
> síntoma es un POST rechazado por CSRF que termina en el login, y no un error de Twig. Los `field`
> internos sí usan `only` porque reciben todo lo que necesitan por parámetro.

**Comprobación**: toca «Agregar nota»: abre con el mismo formulario y en el mismo lugar, más el
subtítulo y el botón ✕ que agrega el componente. Guarda con un título: todo sigue igual.
Ahora, para el caso inválido, envía el POST salteando la validación del navegador —el campo lleva
`required`, así que la pantalla no te deja—: abre el modal, escribe el cuerpo y en la consola del
navegador ejecuta

```js
document.querySelector('#nota-create-modal form[method=post]').submit()
```

Esperado: **422** (lo ves en la pestaña Red) y el HTML vuelve con el modal **abierto**, el mensaje
«Este campo es obligatorio.» junto al campo y «Quiero conservar esto» en el cuerpo. Eso es lo que el
`<dialog>` crudo no hacía.

> Si lo haces con `curl`, el token va con `--data-urlencode`: el valor es base64 y trae `+` y `/`. Con
> `-d "csrf_value=$V"` el `+` se convierte en espacio, el POST rechaza el CSRF y te devuelve al login
> —303, no 422—, que parece un problema de sesión. Agrega `-w '\n%{http_code}\n'` para ver el código.

**Falla si**:

- El modal abre y no se ve el botón de guardar: falta el bloque `modal-action` dentro del form.
- Tras un alta inválida el modal sale cerrado: no estás pasando `open_create_modal`.
- El botón «Cancelar» no cierra el modal: el include de `_form.twig` no está recibiendo
  `cancel_target`. El `onclick` sale con `getElementById('')` y no hay error de Twig.
- El alta se abre con los datos de la nota que estás editando: falta `nota: null` en el include
  del alta; sin eso, `_form.twig` hereda la variable `nota` de la página de edición.
- El aviso de éxito no aparece: falta `usesToastFlash()`.

**Qué no prueba**: que Escape cierre el modal reabierto por el servidor. Un `<dialog open>` no entra al
top layer, así que lo cierra el botón «Cancelar», no Escape.

> **Sobre la guarda del doble submit**: `onsubmit` es un atributo inline, como los `onclick` que ya usas
> para abrir modales; no agrega bloques de JavaScript nuevos. `hx-disabled-elt` del demo de tareas no
> sirve aquí porque este formulario no envía por HTMX: es un POST con navegación.

**Pieza — `components/ui/modal.twig`**: resuelve el diálogo con título, subtítulo, pie y el estado
`open` que permite reabrirlo desde el servidor. Úsalo para todo alta, edición y confirmación. Evita
armar `<dialog>` a mano y pelear con el foco, el telón y el tema oscuro. Sin él, cada modal se comporta
distinto.

**Pieza — `components/ui/button.twig`**: resuelve el botón con variantes, iconos y atributos. Úsalo en
vez de `<button class="btn …">`. Evita que cada pantalla tenga su propia idea de qué es un botón
primario, y con `href` emite un `<a>` en vez de un `<button>`, que es lo que necesita un enlace de
verdad.

---

## 8. Las acciones por fila: del icono mudo al botón accesible

**Objetivo**: que cada fila ofrezca editar y borrar, con nombre accesible.

**Estado de partida**: paso 7 hecho; hay al menos una nota en la lista.

### 8.1 Primero, el atajo que no se debe dejar

**Acción**: en el bloque `row` del parcial, la fila pasa a ser flex —si no, las acciones caen debajo del
título en vez de quedar a la derecha— y se agregan los dos controles con emoji. El `<li>` queda así:

```twig
<li class="flex items-start justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-3">
    <span class="font-semibold">{{ item.titulo }}</span>
    <div class="flex shrink-0 items-center gap-1">
        <a class="btn btn-ghost btn-sm" href="{{ url('/notas/' ~ item.id ~ '/edit') }}">✏️</a>
        <button class="btn btn-ghost btn-sm"
                onclick="document.getElementById('nota-delete-{{ item.id }}')">🗑️</button>
    </div>
</li>
```

**Comprobación**: se ven dos iconos por fila. Y si abres el inspector o pasas el lector de pantalla,
los dos controles **no tienen nombre**: el enlace se anuncia como «✏️» y el botón como «🗑️». Para
alguien que no ve la pantalla, son dos botones indistinguibles.

**Qué no prueba**: nada bueno. Sirve para ver el problema antes de arreglarlo.

> El `onclick` está interpolado con `{{ item.id }}` a propósito: sin interpolar, el botón no haría nada
> y parecería que el problema del paso es el JavaScript, cuando el problema es que **no tiene nombre**.

### 8.2 Ahora, el botón del proyecto

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
                        onclick: "document.getElementById('nota-delete-" ~ item.id ~ "').showModal()"
                    } only %}
                </div>
```

Y suma las rutas al archivo de rutas, que queda con cinco:

```php
        $group->get('/{id}/edit', [NotaController::class, 'edit']);
        $group->post('/{id}', [NotaController::class, 'update']);
        $group->post('/{id}/delete', [NotaController::class, 'destroy']);
```

`edit` se implementa en el paso 9 y `update`/`destroy` en los pasos 9 y 10: hasta entonces, tocar el
lápiz da error, y eso es lo esperado en este punto.

**Comprobación**: los iconos se ven igual, pero cada control ahora se anuncia como «Editar <título>» y
«Borrar <título>». El lápiz navega a `/notas/{id}/edit`; la papelera todavía no abre nada, porque el
diálogo llega en el paso 10.

**Qué ganaste**: nombre accesible, icono del registro compartido y el mismo tratamiento visual que el
resto de la app.

**Falla si**:

- Los botones aparecen como cuadrados vacíos: falta el `icon`, o el nombre no existe en el registro.
- El lápiz no navega: `href` no llega (con `href` el componente emite `<a>`).

---

## 9. Editar en el mismo modal

**Objetivo**: editar una nota reusando el formulario y el diálogo del alta.

**Estado de partida**: paso 8 hecho; el lápiz navega a una ruta que todavía no existe.

**Acción**: `edit()` abre la misma página con el modal cargado; `update()` valida y guarda. Suma
arriba el import de la excepción, junto a los otros `use` —sin él el error que ves es
`Class "App\Http\Controllers\HttpNotFoundException" not found`, un 500 que parece un problema de rutas:

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

        return $this->render($request, $response, 'notas/index.twig', [
            'notas'          => Nota::orderByDesc('id')->get(),
            'total'          => Nota::query()->count(),
            'nota'           => $nota,
            'open_edit_modal' => true,
            'active'         => 'notas',
        ]);
    }

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
            return $this->render($request, $response, 'notas/index.twig', [
                'notas'           => Nota::orderByDesc('id')->get(),
                'total'           => Nota::query()->count(),
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

        return $response->withStatus(303)->withHeader('Location', Url::path('/notas'));
    }
```

Y el segundo modal, junto al del alta y **dentro** del mismo bloque `content`, que sólo se renderiza
cuando hay nota:

```twig
{% if nota is defined %}
    {% embed 'components/ui/modal.twig' with {
        id: 'nota-edit-modal',
        title: 'Editar nota',
        open: open_edit_modal|default(false)
    } %}
        {% block body %}
            {% include 'notas/_form.twig' with {
                action: url('/notas/' ~ nota.id),
                submit_label: 'Guardar cambios',
                cancel_target: 'nota-edit-modal'
            } %}
        {% endblock %}
    {% endembed %}
{% endif %}
```

**Comprobación**: toca el lápiz de una nota. Esperado: se abre un diálogo con el título y el cuerpo ya
cargados. Cambia el título y guarda: vuelves a la lista, la fila muestra el título nuevo y el aviso
«Nota actualizada.».

Un id que no existe (`/notas/99999/edit`) tiene que dar la página 404 del framework, no un error de
PHP.

**Falla si**:

- El modal abre vacío: `nota` no está llegando, o el `_form.twig` no está leyendo `nota`/`form_values`.
- Guardar no cambia nada: el `action` del formulario no apunta a `/notas/{id}`, o el controlador no hace `save()`.
- El 404 sale como 500: no estás lanzando `HttpNotFoundException` antes de tocar la nota.

**Qué no prueba**: que dos personas editando a la vez no se pisen. No hay control de concurrencia, y
este recorrido no lo agrega.

**Pieza — reusar el modal para alta y edición**: resuelve el formulario una sola vez y evita dos
pantallas con reglas que se desincronizan. Úsalo cuando los campos son los mismos. Sin esto, el alta
y la edición validan distinto y una de las dos se olvida de algo.

---

## 10. Borrar: del confirm del navegador al diálogo del proyecto

**Objetivo**: que borrar pida confirmación, entendiendo por qué el atajo del navegador no alcanza.

**Estado de partida**: paso 9 hecho; la papelera no hace nada.

### 10.1 Primero, el atajo

**Acción**: envuelve la papelera en un formulario con el `confirm` del navegador:

```twig
<form method="post" action="{{ url('/notas/' ~ item.id ~ '/delete') }}"
      class="inline" onsubmit="return confirm('¿Borrar esta nota?')">
    {% include 'partials/_csrf.twig' %}
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

Y `destroy()`:

```php
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request, 'No existe una nota con ese identificador.');
        }

        $nota->delete();

        Flash::set('success', 'Nota borrada.');

        return $response->withStatus(303)->withHeader('Location', Url::path('/notas'));
    }
```

**Comprobación**: toca la papelera. Aparece el cartel del navegador «¿Borrar esta nota?». Si aceptas,
la fila desaparece y el aviso «Nota borrada.» sale abajo a la derecha.

Funciona. **Y sin embargo no se queda**: `confirm()` es un cartel del sistema, no se puede estilar,
no respeta el tema, no dice **qué** nota estás borrando y bloquea el hilo del navegador. Por eso el
proyecto usa un diálogo.

### 10.2 Ahora, el diálogo de confirmación

**Acción**: saca el `onsubmit` y el formulario de la fila, y pon el diálogo dentro del bloque `row`,
después del contenido:

```twig
            {% embed 'components/ui/modal.twig' with {
                id: 'nota-delete-' ~ item.id,
                title: 'Borrar nota'
            } %}
                {% block body %}
                    <p class="py-3 opacity-70">¿Borrar «{{ item.titulo }}»? No se puede deshacer.</p>
                    <form method="post" action="{{ url('/notas/' ~ item.id ~ '/delete') }}"
                          onsubmit="this.querySelector('[type=submit]').disabled = true">
                        {% include 'partials/_csrf.twig' %}
                        <div class="modal-action">
                            {% include 'components/ui/button.twig' with {
                                label: 'Cancelar',
                                variant: 'ghost',
                                type: 'button',
                                icon: 'x',
                                onclick: "document.getElementById('nota-delete-" ~ item.id ~ "').close()"
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
```

La papelera del paso 8 ya apunta a ese diálogo con `showModal()`.

**Comprobación**: toca la papelera de una nota. Esperado: se abre un diálogo que dice **qué nota** vas
a borrar —«¿Borrar «Comprar pan»? No se puede deshacer.»—, con «Cancelar» y «Borrar». «Cancelar» no
hace nada; «Borrar» elimina la fila y muestra el aviso.

**Qué ganaste**: el texto dice qué se borra, el diálogo respeta el tema y el foco, y cancelar es una
acción explícita en vez de cerrar un cartel del sistema.

**Falla si**:

- La papelera no abre nada: el `onclick` apunta a un id que no existe (el id lleva el número de fila).
- Cancelar borra igual: el botón de cancelar tiene que ser `type: 'button'`; con `submit` el formulario
  se envía.
- El borrado te devuelve al login con «Tu sesión expiró»: falta el `{% include 'partials/_csrf.twig' %}`
  dentro del formulario del modal. Sin HTMX el rechazo es un 303 al login; el 400 con `HX-Trigger` es
  sólo para disparadores HTMX.

**Qué no prueba**: que el borrado sea reversible, ni que exista borrado lógico. Borra la fila.

**Pieza — confirmación en un diálogo antes de mutar**: resuelve el «¿estás seguro?» con el idioma del
proyecto. Úsala en toda acción destructiva. Evita el borrado por accidente y el cartel del sistema que
no dice qué se borra. Sin esto, la papelera borra al primer toque y no hay vuelta atrás.

> **Por qué un diálogo por fila y no uno solo**: el demo de contactos usa un único modal y le cambia
> el `action` por JavaScript. Aquí el diálogo vive en cada fila para no agregar JS: es más markup, pero
> es server-rendered y no depende de estado del cliente. Con decenas de filas, el modal único con JS
> vuelve a tener sentido.

---

## 11. Tests que reemplazan lo que hacías a mano

**Objetivo**: convertir en automático lo que venías comprobando a mano.

**Estado de partida**: pasos 1 a 10 hechos y comprobados en pantalla.

**Acción**: crea `tests/Http/NotaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

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
        return Nota::create(['titulo' => 'De prueba'] + $datos);
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

    public function test_avisa_cuando_el_identificador_no_existe(): void
    {
        $response = self::app()->handle(self::request('GET', '/notas/99999/edit'));
        $html = (string) $response->getBody();

        self::assertSame(404, $response->getStatusCode());
        self::assertNotSame('', $html, 'Un 404 mudo no avisa nada.');
        self::assertLessThan(4096, strlen($html), 'Un 404 no debería gastar la página de depuración.');
    }
}
```

**Comprobación**:

```bash
vendor/bin/phpunit --filter NotaTest
```

Esperado: cinco tests en verde.

**Falla si**:

- El test falla en `Location` (`/login` en vez de `/notas`): el token no está llegando, aunque el
  primer `assertSame(303, …)` pase igual. Sin HTMX el rechazo de CSRF es un 303 al login, no un 400.
  `HttpTestCase::token()` te da el par.
- El test del modal falla en el `assertMatchesRegularExpression`: el atributo `open` no está saliendo; revisa `open_create_modal`.
- La suite completa falla después: falta el `tearDown` que limpia `notas`.

**Qué no prueba**: que la pantalla se vea bien, ni que los diálogos abran. Prueba el contrato HTTP:
qué código, qué guarda y qué mensaje.

> **Checkpoint**: aquí tienes la entidad completa —crear, editar, borrar con confirmación y tests—. Si
> esto no funciona, lo que sigue no lo arregla.

**Pieza — `Tests\Support\HttpTestCase`**: resuelve el arranque de la app, la sesión inyectada, el
request con cuerpo de formulario y los tokens (`token()`). Úsalo como base de todos tus tests HTTP.
Evita tests que arman el entorno a mano y se rompen cuando cambia el bootstrap.

---

## 12. Paginación del servidor

**Objetivo**: que la lista siga siendo usable cuando crezca.

**Estado de partida**: paso 11 hecho; con muchas notas la página se vuelve larga.

**Acción**: en el controlador, suma `use Framework\Http\Pagination;` y centraliza la lectura:

```php
    private function pageData(Request $request): array
    {
        $query = $request->getQueryParams();

        $paginacion = Pagination::paginate(
            Nota::orderByDesc('id'),
            (int) ($query['page'] ?? 1),
            (int) ($query['per_page'] ?? Pagination::DEFAULT_PER_PAGE),
            Url::path('/notas')
        );

        return [
            'notas'      => $paginacion['items'],
            'total'      => $paginacion['total'],
            'pagination' => $paginacion,
        ];
    }
```

Y un helper que arma la página, para que el `HX-Push-Url` no se repita en cada acción:

```php
    private function renderIndex(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        $data = $this->pageData($request);

        // Con HX-Request va SÓLO la región. Si devuelves la página entera, el
        // swap mete el shell del layout adentro del panel: dos sidebars, dos
        // headers y el contenido duplicado.
        $template = $request->hasHeader('HX-Request') ? 'notas/_panel.twig' : 'notas/index.twig';

        return $this->render($request, $response, $template, $data + $extra + [
            'active' => 'notas',
        ])->withStatus($status)
            ->withHeader('HX-Push-Url', Url::path('/notas')
                . '?page=' . $data['pagination']['current_page']
                . '&per_page=' . $data['pagination']['per_page']);
    }
```

`index()` queda en una línea (`return $this->renderIndex($request, $response);`), y `edit()` y los dos
renders de `422` pasan a usarlo pasando lo suyo en `$extra`:

```php
        // edit()
        return $this->renderIndex($request, $response, [
            'nota'            => $nota,
            'open_edit_modal' => true,
        ]);

        // rechazo del alta
        return $this->renderIndex($request, $response, [
            'errors'            => $validator->errors(),
            'form_values'       => $data,
            'open_create_modal' => true,
        ], 422);

        // rechazo de la edición
        return $this->renderIndex($request, $response, [
            'errors'          => $validator->errors(),
            'form_values'     => $data,
            'nota'            => $nota,
            'open_edit_modal' => true,
        ], 422);
```

En el rechazo de la edición, `'nota' => $nota` no es decorativo: `index.twig` envuelve el modal de
edición en `{% if nota is defined %}`. Sin esa clave el modal no se renderiza y lo tipeado aparece en
el modal de alta, que es el que queda abierto.

La cabecera se emite con la página que el **servidor** recortó, no con la que pidió el cliente: si
alguien pide `?page=99` sobre tres páginas, la URL que queda es la 3.

Y la paginación, **dentro** del `div#notas-panel` y después del `embed` —si la dejas afuera, el swap
reemplaza el panel y los controles quedan apuntando a un id que sigue existiendo pero ya no se
actualiza:

```twig
    {% include 'components/pagination/simple.twig' with {
        pagination: pagination,
        target: '#notas-panel'
    } %}
</div>
```

**Comprobación**: crea más de diez notas. Esperado: se ven diez, con los controles debajo
(Primera/Anterior, números, Siguiente/Última) y el selector de tamaño.

Probalos uno por uno: cambiar el tamaño a 25 muestra las 25 que haya y actualiza la URL; «Última» va a
la última página con las filas que queden; «Anterior» y «Primera» retroceden; en los extremos, los
botones que no aplican salen deshabilitados. En todos los casos la página **no se recarga** y queda
**un solo** sidebar y **un solo** header.

Comprobalo con el inspector después de paginar, pero contando en la **página entera**: tiene que haber
un solo `<header>` y dos `<aside>` (el sidebar y el de avisos), y `#notas-panel` tiene que seguir
existiendo con la paginación adentro. Si ves dos headers y cuatro asides, el servidor mandó la página
completa: el shell queda **al lado** del panel y no adentro, así que mirar sólo `#notas-panel` no
muestra nada raro y el bug pasa igual.

**Falla si**:

- La paginación no navega: el `div` con el id quedó fuera del parcial y el primer swap se lo llevó.
- Paginas y la URL no cambia: falta `HX-Push-Url`.
- Después de paginar ves **dos** sidebars o dos headers: el servidor devolvió la página completa a un
  request de HTMX, y el shell entró dentro del panel. Es el error más visible de este paso.
- Cambias el tamaño y vuelves a la página 1: es lo esperado al cambiar el tamaño; si no quieres eso, se decide en otra parte del código.
- Todo aparece en una página: no estás pasando `pagination` a la vista.

**Qué no prueba**: que el orden sea el correcto entre páginas; lo define tu `orderBy`. Tampoco prueba
la navegación sin JavaScript: los enlaces llevan `href` real, pero el `<noscript>` del selector es
otra cosa.

**Pieza — `Framework\Http\Pagination` + `components/pagination/simple.twig`**: resuelven conteo,
recorte y controles del lado del servidor. Usalos en toda lista que pueda crecer. Evitan el `get()`
pelado, que funciona con veinte filas y se cae con dos mil.

**Pieza — `HX-Push-Url`**: resuelve la URL honesta después de un swap. Úsalo en toda región paginada o
filtrada. Evita que recargar o compartir el enlace te devuelva a otra página.

---

## 13. Repetir el patrón con otra entidad

**Objetivo**: aplicar el mismo camino a una entidad propia.

**Estado de partida**: pasos 1 a 12 hechos.

| Qué cambia | Qué no cambia |
|---|---|
| El nombre de la entidad y de la tabla | El orden de los pasos |
| Los campos y sus reglas de validación | Los modales de alta, edición y borrado |
| El contenido de la fila | Las acciones por fila con nombre accesible |
| Los textos visibles | La paginación del servidor y `HX-Push-Url` |
| Los datos de los tests | Los cinco casos del cierre |

**Los archivos a tocar**, en orden:

1. `database/migrations/NNN_create_<entidad>_table.php`
2. `app/Models/<Entidad>.php`
3. `routes/<entidad>.php` (el arranque lo carga solo) y `config/navigation/<entidad>.php`
4. `app/Http/Controllers/<Entidad>Controller.php`
5. `resources/views/<entidad>/index.twig`, `_panel.twig`, `_form.twig`
6. `tests/Http/<Entidad>Test.php`

Si quieres un punto de comparación ya hecho, `composer demo:notas:install` instala esta misma entidad
como demo removible, con ocho tests.

**Comprobación**: elige otra entidad (por ejemplo `libros`, con `titulo` y `anio`) y recorre esa lista.
Esperado: al terminar, la pantalla tiene botón de alta en un modal, acciones por fila, borrado con
confirmación, paginación y cinco tests en verde.

**Falla si**:

- El modal de borrado no abre: `onclick` apunta a un id sin el número de fila.
- El alta inválida no reabre el modal: falta pasar `open_create_modal`.
- La paginación deja de funcionar después del primer guardado: el `div` del panel quedó fuera del parcial.

**Qué no prueba**: que el patrón sirva para entidades con relaciones, permisos por fila o adjuntos.
Eso es otra conversación, y necesita más piezas.

---

## Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| El POST te devuelve al login con «Tu sesión expiró» | Falta el token de CSRF en el formulario | `{% include 'partials/_csrf.twig' %}` dentro del `<form>` |
| El login vuelve al formulario con «Credenciales inválidas.» | Email o contraseña no coinciden con el admin sembrado | Vuelve a sembrar con las mismas variables; el mensaje es genérico a propósito |
| `no such table` | Migración no aplicada en la base que usa la app | `vendor/bin/phinx status` y revisa `DB_DATABASE` |
| El aviso de éxito no aparece | Falta `usesToastFlash()` en el controlador | Pasa el método a `true` |
| El modal abre cerrado tras un rechazo | No estás pasando `open_create_modal` / `open_edit_modal` | Pásalo en `true` en el render del 422 |
| El error no aparece junto al campo | El include del `field` usa `only` y no recibe `error` | Pasa `error: errors.<campo>|default(null)` |
| El panel desaparece tras el primer guardado | El `div` con el id quedó en `index.twig` | Movelo adentro del parcial (paso 6.3) |
| La papelera no abre el diálogo | El `onclick` apunta a un id sin el número de fila | Usa `nota-delete-{id}` en el modal y en el `onclick` |
| La lista trae todo | Falta `Pagination::paginate()` | Paso 12 |
| Paginas y la URL no cambia | Falta la cabecera `HX-Push-Url` | Paso 12 |
| Editar un id inexistente da 500 | No se lanza `HttpNotFoundException` | Paso 9 |

## Qué demuestra este recorrido

Que agregar una entidad a este framework tiene un orden, y que ese orden tiene comprobaciones. Las
piezas que usaste —migraciones, modelo, archivo de rutas, `render()`, validador, CSRF, `layouts/app.twig`,
`components/list`, `components/form`, `components/ui/{button,modal}`, paginación, `HttpTestCase`— no
son opcionales: son el idioma del proyecto.

Y que ese idioma se aprende por contraste: cada pieza apareció primero en su versión básica y después
en la del proyecto, con lo que se gana y lo que se evita. El HTML crudo, el `<dialog>` nativo, el
`confirm()` del navegador y los emojis sin nombre no son pasos en falso: son la razón por la que los
componentes existen.

Lo que queda pendiente y por qué: relaciones entre entidades (necesitan otra conversación), permisos
por fila y adjuntos (más piezas), y despliegue (tiene su propio material).
