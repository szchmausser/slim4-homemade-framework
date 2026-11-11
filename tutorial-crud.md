# Tu primera entidad en este framework

Recorrido para agregar tu propia entidad a este proyecto, desde un clon recién hecho hasta tenerla
funcionando con tests. Está escrito para seguirse de arriba abajo, en orden, sin saltear pasos.

Al terminar vas a tener una entidad `notas` que lista, crea, valida y responde por identificador, y
una suite mínima que reemplaza las comprobaciones que fuiste haciendo a mano.

## Prerrequisitos

Necesitás:

- PHP 8.4.1 o superior con `pdo_sqlite`, `mbstring` y `sqlite3`.
- Composer 2.
- Un navegador.
- `curl` (opcional: se usa una sola vez, y hay alternativa con el navegador).

**Lo que este recorrido no enseña**: instalar PHP, Composer ni Termux; el contenedor de dependencias;
los middlewares; la autenticación; los assets y el sistema visual; el despliegue. Todo eso está en
`docs/guia-stack-php-termux.md`. Acá se da por hecho que el framework ya arranca.

## Cómo leer cada paso

Cada paso tiene la misma forma, y conviene respetarla:

- **Objetivo**: la acción concreta.
- **Estado de partida**: qué tiene que estar hecho. Si falta, el paso dice cómo volver.
- **Acción**: el archivo y el código, o el comando.
- **Comprobación**: qué ejecutar y **qué tenés que ver**, escrito antes de que lo veas.
- **Falla si**: la señal concreta de que el paso no quedó.
- **Qué no prueba**: el límite de esa comprobación.
- **Pieza**: cuando aparece algo reutilizable, qué resuelve, cuándo usarlo, qué evita y cómo se haría
  sin él.

Si una comprobación no da lo esperado, no sigas: el paso siguiente asume que este quedó bien.

## Índice

1. [Arrancar la app hasta tener sesión](#1-arrancar-la-app-hasta-tener-sesión)
2. [La migración de `notas`](#2-la-migración-de-notas)
3. [El modelo `Nota`](#3-el-modelo-nota)
4. [Registrar la entidad en las rutas](#4-registrar-la-entidad-en-las-rutas)
5. [La primera ruta: JSON verificable](#5-la-primera-ruta-json-verificable)
6. [El listado](#6-el-listado)
7. [El alta con validación](#7-el-alta-con-validación)
8. [Consultar una nota y avisar del inexistente](#8-consultar-una-nota-y-avisar-del-inexistente)
9. [Tests que reemplazan lo que hiciste a mano](#9-tests-que-reemplazan-lo-que-hiciste-a-mano)
10. [La lista como región HTMX](#10-la-lista-como-región-htmx)
11. [El componente de lista](#11-el-componente-de-lista)
12. [Paginación del servidor](#12-paginación-del-servidor)
13. [Los dos transportes de CSRF](#13-los-dos-transportes-de-csrf)
14. [Repetir el patrón con otra entidad](#14-repetir-el-patrón-con-otra-entidad)

Los pasos 1 a 9 son el recorrido mínimo: al terminarlos tenés una entidad funcionando y verificada
con tests. Del 10 en adelante la entidad pasa a hablar el idioma del framework, y el 14 te deja el
patrón para aplicar a otra entidad tuya.

---

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

Después sembrá el administrador. Las credenciales se pasan **por entorno** y no se escriben en
ningún archivo del repo:

```bash
ADMIN_EMAIL=vos@ejemplo.com ADMIN_PASSWORD=una-clave-de-8-o-mas \
  vendor/bin/phinx seed:run -s DatabaseSeeder
```

Y levantá el servidor de desarrollo:

```bash
php -S 0.0.0.0:8080 -t public
```

**Comprobación**: abrí `http://localhost:8080/login` en el navegador. Tenés que ver el formulario de
acceso. Entrá con el email y la contraseña que sembraste: después del login el navegador te lleva a
la portada y en el header aparece tu usuario.

**Falla si**:

- `phinx seed:run` aborta con un mensaje sobre `ADMIN_EMAIL` y `ADMIN_PASSWORD`: no pasaste las
  variables o la contraseña tiene menos de 8 caracteres.
- El login vuelve al formulario con el aviso «Credenciales inválidas.»: el email o la contraseña no
  coinciden con el admin que sembraste. El mensaje es genérico a propósito —no dice cuál de los dos
  falló, para no revelar qué cuentas existen—, así que revisá los dos.
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

**Acción**: mirá qué números de migración están libres:

```bash
ls database/migrations/
```

Vas a ver archivos como `002_create_users_table.php` y `003_create_remember_tokens.php`. La
convención del proyecto es **numeración secuencial**, no timestamps, así que tomás el siguiente
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

**Comprobación**: corré `vendor/bin/phinx status` (o volvé a `composer migrate`). Tenés que ver tu
migración listada como aplicada, sin pendientes.

**Falla si**:

- Phinx dice que ya existe una migración con ese número: alguien usó ese número mientras mirabas.
  Elegí el siguiente libre, nunca reutilices uno.
- El error menciona `notas` ya existente: la tabla quedó de un intento anterior. `vendor/bin/phinx
  rollback -t 0` la baja, y volvés a subir.

**Qué no prueba**: que el modelo use esta tabla ni que los campos alcancen. La migración sólo prueba
que el esquema se puede crear y revertir.

**Pieza — migraciones Phinx con numeración secuencial**: resuelven el versionado del esquema sin
depender de un ORM que lo adivine. Usalas siempre que cambie el esquema. Evitan el problema de los
timestamps, que saturan enteros de 32 bits en bases grandes. Sin esta pieza tendrías que aplicar SQL
a mano y acordarte del orden; el proyecto ya tiene el mecanismo, sólo hay que escribir el archivo.

---

## 3. El modelo `Nota`

**Objetivo**: poder leer y escribir la tabla desde PHP sin SQL escrito a mano.

**Estado de partida**: paso 2 hecho, la tabla existe.

**Acción**: creá `app/Models/Nota.php`:

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

Esperado: sin errores, con `app/` entre los directorios revisados.

**Falla si**:

- El lint reporta un error de sintaxis: revisá llaves y `;`.
- El lint no revisa tu archivo: no está en `app/Models/`, así que la ruta y el `namespace` no van a
  coincidir.

**Qué no prueba**: que el modelo conecte con la tabla. El lint sólo verifica sintaxis; la prueba real
llega en el paso 7, cuando creás la primera nota. Si el modelo apunta a una tabla inexistente, el
error aparece ahí, con `no such table: notas`.

> No intentes comprobar el modelo con un `php -r` que cargue `bootstrap/app.php`: ese arranque espera
> un request HTTP (sesión abierta, entorno cargado) y en CLI falla con avisos que parecen errores del
> modelo.

**Pieza — Eloquent standalone**: resuelve el acceso a datos con un modelo por tabla. Usalo para
toda lectura y escritura; el proyecto no escribe SQL a mano en los controladores. Evita el SQL
disperso por las vistas y los controladores. Sin él tendrías que preparar sentencias y mapear filas a
mano en cada operación.

---

## 4. Registrar la entidad en las rutas

**Objetivo**: que el framework sepa que existe `/notas`.

**Estado de partida**: pasos 1 a 3 hechos.

**Acción**: primero, el archivo de rutas de tu entidad, `routes/notas.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\NotaController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;

return function (App $app) {
    $app->group('/notas', function ($group) {
        $group->get('', [NotaController::class, 'index']);
        $group->post('', [NotaController::class, 'store']);
        $group->get('/{id}', [NotaController::class, 'show']);
    })->add(RequireAuthMiddleware::class);
};
```

Después, registralo en `bootstrap/app.php`, junto a los otros archivos de rutas:

```php
(require __DIR__ . '/../routes/notas.php')($app);
```

**Comprobación**: abrí `http://localhost:8080/notas` en el navegador. **Todavía no hay controlador**, así
que lo esperado es un **error 500 con la página de depuración de Whoops**, quejándose de que la clase
`NotaController` no existe. Una página grande, con stack trace.

Esa diferencia es la que confirma que la ruta existe: la ruta se resolvió y falló al instanciar el
controlador.

**Falla si**:

- Ves la **página 404 del framework**, chica, sin stack trace: el archivo de rutas no se está
  registrando. Revisá la línea que agregaste en `bootstrap/app.php`.
- Te devuelve al login aunque hayas iniciado sesión: el grupo está protegido y la sesión se perdió.
  Volvé al paso 1.

**Qué no prueba**: que la ruta haga algo. Prueba que el registro funciona y que la protección está
activa.

**Pieza — el archivo de rutas y su registro**: resuelve la separación entre «la ruta existe» y «el
arranque de la app». Cada entidad tiene su archivo y una línea de registro. Evita meter rutas de
dominio dentro de `bootstrap/app.php`, que es arranque del framework y no tuyo. Sin esta pieza
tendrías todas las rutas del proyecto en un archivo que crece sin límite.

**Pieza — `RequireAuthMiddleware`**: resuelve la puerta de entrada a las rutas privadas. Se aplica al
grupo, no ruta por ruta, para que no se te olvide ninguna. Evita el clásico «me olvidé de proteger el
POST». Sin él, `curl` podría crear filas sin sesión.

---

## 5. La primera ruta: JSON verificable

**Objetivo**: comprobar que tu ruta responde algo, antes de involucrar vistas y formularios.

**Estado de partida**: paso 4 hecho; `/notas` da error de controlador inexistente.

**Acción**: creá `app/Http/Controllers/NotaController.php` con una sola acción:

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

**Comprobación**: con sesión iniciada, abrí `http://localhost:8080/notas` en el navegador. Tenés que
ver exactamente:

```json
{"status":"ok","recurso":"notas"}
```

**Falla si**:

- Ves `{"status":"ok","recurso":"notas"}` pero el login te lo pidió de nuevo: normal, la ruta está
  protegida; iniciá sesión y volvé.
- Ves HTML: el navegador está mostrando otra ruta; revisá la URL.
- Ves un error de PHP: el `namespace` o el nombre de la clase no coinciden con la ruta del archivo.

**Qué no prueba**: nada sobre datos ni sobre interfaz. Prueba el camino completo request → ruta →
controlador → respuesta, que es lo que se rompe primero cuando algo está mal cableado.

> Esta primera comprobación es JSON a propósito: no hay HTML que interpretar, así que la comparación
> es cadena contra cadena. Cuando en el paso 6 entre una vista, la comprobación pasa a ser visual y
> menos precisa.

---

## 6. El listado

**Objetivo**: ver las notas guardadas en una página real del shell.

**Estado de partida**: paso 5 hecho; `/notas` devuelve el JSON.

**Acción**: cambiá `index()` para renderizar una vista y crear `resources/views/notas/index.twig`:

```php
    public function index(Request $request, Response $response): Response
    {
        return $this->render($request, $response, 'notas/index.twig', [
            'notas'  => Nota::orderByDesc('id')->get(),
            'active' => 'notas',
        ]);
    }
```

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
                        <a class="font-semibold" href="{{ url('/notas/' ~ nota.id) }}">{{ nota.titulo }}</a>
                        {% if nota.cuerpo %}
                            <p class="text-sm opacity-70">{{ nota.cuerpo }}</p>
                        {% endif %}
                    </li>
                {% else %}
                    <li class="opacity-60">No hay notas todavía.</li>
                {% endfor %}
            </ul>
        </div>
    </section>
{% endblock %}
```

**Comprobación**: abrí `http://localhost:8080/notas`. Tenés que ver el shell completo (sidebar,
header con tu usuario) y, dentro, el título «Notas» y el texto «No hay notas todavía.».

**Falla si**:

- Ves sólo el título sin el shell: la vista no está extendiendo `layouts/app.twig`, o el bloque se
  llama distinto de `content`.
- Error de Twig «Unable to find template»: la ruta del archivo no coincide con la que pasaste a
  `render()` (`notas/index.twig` → `resources/views/notas/index.twig`).

**Qué no prueba**: que puedas crear nada. La lista está vacía y no hay formulario todavía.

**Pieza — `layouts/app.twig` y los bloques**: resuelve el armado del shell (sidebar, header, avisos,
tema) una sola vez. Usalo en toda página tuya y escribí sólo el bloque `content`. Evita rearmar el
shell en cada pantalla y quedar desincronizado del resto. Sin él, cada vista repetiría el header y el
tema se rompería en la que te olvidaras.

**Pieza — `Controller::render()`**: resuelve el pasaje de datos a la vista, e inyecta lo que el shell
necesita (usuario, tokens de formulario, avisos). Usalo en vez de llamar a Twig directamente. Evita
olvidarte del token de formulario, que es lo que hace fallar los POST con 400 sin explicación. Sin
esta pieza tendrías que pasar a mano cinco variables en cada acción, y alguna vez olvidarías una.

---

## 7. El alta con validación

**Objetivo**: crear notas desde la interfaz, rechazando datos inválidos con el mensaje donde se
cometió el error.

**Estado de partida**: paso 6 hecho; la lista se ve vacía.

**Acción**: agregá `store()` al controlador. Vas a necesitar cuatro `use` más, arriba del archivo:

```php
use App\Models\Nota;
use Framework\Http\Flash;
use Framework\Http\Url;
use Framework\Validation\Validator;
```

Y la acción:

```php
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'titulo' => 'required|max:120',
            'cuerpo' => 'max:2000',
        ]);

        if ($validator->fails()) {
            return $this->render($request, $response, 'notas/index.twig', [
                'notas'  => Nota::orderByDesc('id')->get(),
                'errors' => $validator->errors(),
                'titulo' => (string) ($data['titulo'] ?? ''),
                'cuerpo' => (string) ($data['cuerpo'] ?? ''),
                'active' => 'notas',
            ])->withStatus(422);
        }

        Nota::create([
            'titulo' => (string) $data['titulo'],
            'cuerpo' => (string) ($data['cuerpo'] ?? '') !== '' ? (string) $data['cuerpo'] : null,
        ]);

        Flash::set('success', 'Nota creada.');

        return $response->withStatus(303)->withHeader('Location', Url::path('/notas'));
    }
```

Y sumá el formulario arriba de la lista en `notas/index.twig`:

```twig
            <form method="post" action="{{ url('/notas') }}" class="flex flex-col gap-3">
                {% include 'partials/_csrf.twig' %}

                {% include 'components/form/field.twig' with {
                    name: 'titulo',
                    label: 'Título',
                    value: titulo|default(''),
                    required: true,
                    maxlength: 120,
                    error: errors.titulo|default(null)
                } only %}

                {% include 'components/form/field.twig' with {
                    name: 'cuerpo',
                    label: 'Cuerpo (opcional)',
                    value: cuerpo|default(''),
                    maxlength: 2000,
                    error: errors.cuerpo|default(null)
                } only %}

                {% include 'components/ui/button.twig' with {
                    label: 'Guardar',
                    type: 'submit'
                } only %}
            </form>
```

**Comprobación**: en `/notas`, escribí un título y guardá. Esperado: volvés a `/notas` con la nota
en la lista y un aviso de éxito.

Después probá el rechazo: guardá con el título vacío. Esperado: seguís en el formulario, con un
mensaje de error **junto al campo del título**, y lo que habías escrito en el cuerpo sigue ahí.

**Falla si**:

- El POST te devuelve al login con «Tu sesión expiró. Volvé a iniciar sesión.»: falta el
  `{% include 'partials/_csrf.twig' %}` dentro del formulario. Sin HTMX el rechazo de CSRF es un 303
  al login con ese aviso (el del handler de `config/container.php`), no un 400.
- El formulario se limpia al fallar la validación: estás devolviendo la vista sin los valores
  `titulo` y `cuerpo`, o sin el status 422.
- La nota se crea pero no aparece: la lista no se volvió a leer después de guardar.

**Qué no prueba**: que el título largo se rechace en el servidor y no sólo en el navegador. El
`maxlength` de la interfaz se puede saltear.

**Pieza — `Framework\Validation\Validator`**: resuelve la validación en el servidor con reglas
declarativas. Usalo en toda acción que reciba datos. Evita validar a mano campo por campo, que es
donde se cuelan los `if` incompletos. Sin él, cada acción tendría su propia versión de «el título no
puede estar vacío» y alguna sería distinta.

**Pieza — `partials/_csrf.twig`**: resuelve la protección de los formularios. Incluilo en **todo**
formulario que modifique datos. Evita el 400 sin mensaje que se lleva horas de diagnóstico. Sin él, el
middleware rechaza el POST y no hay nada en pantalla que explique por qué.

**Pieza — `Flash` + redirección**: resuelve el mensaje que sobrevive al redirect. Usalo cuando la
acción termina navegando. Evita el «se guardó pero no sé si se guardó». Sin él, el mensaje se pierde
al cambiar de página.

---

## 8. Consultar una nota y avisar del inexistente

**Objetivo**: que un identificador que no existe dé un aviso legible, no un 404 mudo ni un 500.

**Estado de partida**: paso 7 hecho; podés crear y listar.

**Acción**: agregá `use Slim\Exception\HttpNotFoundException;` arriba, `show()` en el controlador y
su vista:

```php
    public function show(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request, 'No existe una nota con ese identificador.');
        }

        return $this->render($request, $response, 'notas/show.twig', [
            'nota'   => $nota,
            'active' => 'notas',
        ]);
    }
```

```twig
{% extends 'layouts/app.twig' %}

{% block title %}{{ nota.titulo }}{% endblock %}

{% block content %}
    <section class="card border border-base-300 bg-base-100">
        <div class="card-body gap-3">
            <h1 class="text-2xl font-bold">{{ nota.titulo }}</h1>
            {% if nota.cuerpo %}
                <p class="opacity-80">{{ nota.cuerpo }}</p>
            {% else %}
                <p class="opacity-60">Esta nota no tiene cuerpo.</p>
            {% endif %}
            <a class="link" href="{{ url('/notas') }}">Volver a notas</a>
        </div>
    </section>
{% endblock %}
```

**Comprobación**: desde la lista, hacé clic en el título de una nota. Esperado: la ves sola, con su
cuerpo o el aviso de que no tiene.

Después, en la barra de direcciones, pedí una que no existe: `http://localhost:8080/notas/99999`.
Esperado: la página de error del framework, con código 404. **No** esperes el mensaje que escribiste
en la excepción: esa página se arma por código HTTP, no por mensaje (mirá
`resources/views/errors/error.twig`).

**Falla si**:

- Ves una pantalla en blanco: se lanzó una excepción que no está mapeada a la página de error.
- Ves el 404 crudo del servidor de PHP: la ruta no está registrada con `{id}`.

**Qué no prueba**: que el mensaje sea el que escribiste, ni el comportamiento con HTMX. Lo segundo
importa: cuando en el paso 10 tu detalle pase a ser una región, un 404 dentro del panel metería la
página de error **adentro** del panel. El demo de tareas resuelve ese caso con un flash en lugar de un
404; si vas a consultar desde la región, tomá esa decisión a conciencia.

---

## 9. Tests que reemplazan lo que hiciste a mano

**Objetivo**: convertir en automático lo que venías comprobando a mano. Después de este paso, un
cambio futuro que rompa la entidad falla en la suite y no en tu navegador.

**Estado de partida**: pasos 1 a 8 hechos y comprobados a mano.

**Acción**: creá `tests/Http/NotaTest.php`:

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

    public function test_crea_una_nota_y_la_muestra_en_la_lista(): void
    {
        // El POST necesita el par CSRF que emite la página, igual que un formulario.
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

    public function test_rechaza_un_titulo_vacio(): void
    {
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => '',
        ]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, Nota::query()->count());
    }

    public function test_avisa_cuando_el_identificador_no_existe(): void
    {
        $response = self::app()->handle(self::request('GET', '/notas/99999'));
        $html = (string) $response->getBody();

        self::assertSame(404, $response->getStatusCode());
        self::assertNotSame('', $html, 'Un 404 mudo no avisa nada.');
        // La página de error se arma por código, no por mensaje: sin asserts sobre el texto.
        self::assertLessThan(4096, strlen($html), 'Un 404 no debería gastar la página de depuración.');
    }
}
```

**Comprobación**:

```bash
vendor/bin/phpunit --filter NotaTest
```

Esperado: tres tests, todos en verde.

**Falla si**:

- El POST responde 400: el token no está llegando. `HttpTestCase::token()` te da el par; si el GET no
  renderiza el partial de CSRF, no hay token que extraer.
- El 404 devuelve 200: no estás lanzando `HttpNotFoundException`, o la ruta `{id}` no está registrada.
- La suite completa falla después de estos tests: falta el `tearDown` que limpia `notas`.

**Qué no prueba**: que la interfaz se vea bien, ni que el flujo con HTMX funcione. Prueba el contrato
HTTP de la entidad: qué código devuelve, qué guarda y qué mensaje da.

> **Checkpoint**: acá tenés una entidad completa y verificable. Si esto no funciona, lo que sigue no
> lo va a arreglar. Es un buen momento para parar.

**Pieza — `Tests\Support\HttpTestCase`**: resuelve el arranque de la app en un test, la sesión
inyectada y el request con cuerpo de formulario. Usalo como base de todos tus tests HTTP. Evita
tests que arman el entorno a mano y se rompen cuando cambia el bootstrap. Sin él, cada test tendría
su propia versión del arranque.

---

## 10. La lista como región HTMX

**Objetivo**: que guardar una nota no recargue la página, y que el aviso aparezca donde corresponde.

**Estado de partida**: paso 9 hecho; la entidad funciona con navegación clásica.

**Acción**: separá la lista en un parcial. Creá `resources/views/notas/_panel.twig` y mové ahí el
`<ul>` completo. En `index.twig`, reemplazalo por:

```twig
    <div id="notas-panel">
        {% include 'notas/_panel.twig' %}
    </div>
```

Después, el formulario pasa a mandar por HTMX:

```twig
<form method="post" action="{{ url('/notas') }}"
      hx-post="{{ url('/notas') }}"
      hx-target="#notas-panel"
      hx-swap="outerHTML"
      class="flex flex-col gap-3">
```

Y el controlador elige qué devolver según el request:

```php
    private function respond(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        $template = $request->hasHeader('HX-Request') ? 'notas/_panel.twig' : 'notas/index.twig';

        return $this->render($request, $response, $template, $extra + [
            'notas'  => Nota::orderByDesc('id')->get(),
            'active' => 'notas',
        ])->withStatus($status);
    }
```

`index()` devuelve `$this->respond($request, $response)`. `store()` usa `respond()` en el rechazo y,
en el éxito, responde con el panel y el aviso:

```php
        Flash::set('success', 'Nota creada.');

        return $this->respond($request, $response);
```

**Comprobación**: en `/notas`, guardá una nota. Esperado: la lista se actualiza **sin recargar la
página**, el formulario se vacía y aparece un aviso flotante abajo a la derecha.

Después repetí el rechazo (título vacío). Esperado: la lista sigue igual, el mensaje de error aparece
junto al campo y **lo que escribiste no se borra**.

**Falla si**:

- Aparece la página entera dentro del panel (dos headers, dos títulos): el controlador devolvió
  `index.twig` a un request de HTMX. El `hasHeader('HX-Request')` no está tomando.
- Guarda bien pero el aviso no aparece: el flash se está mostrando por el canal de página completa en
  vez del de HTMX (lo resuelve el shell, no tu código).
- El formulario se limpia en el rechazo: el status tiene que ser 422, no 200.

**Qué no prueba**: que funcione sin JavaScript. El shell está pensado para que el `href` y la
navegación clásica sigan andando, pero eso se comprueba apagando JS.

**Pieza — región de HTMX**: resuelve el intercambio de una parte de la página sin recargar. Usalo
cuando la acción cambia un bloque y el resto no. Evita re-renderizar el shell entero para una fila
nueva. Sin él, cada guardado cuesta una recarga y pierdes el estado de la página.

---

## 11. El componente de lista

**Objetivo**: dejar de escribir a mano el markup de la lista y el estado vacío.

**Estado de partida**: paso 10 hecho; el panel ya es una región.

**Acción**: reemplazá el `<ul>` del parcial por el componente:

```twig
{% embed 'components/list/simple.twig' with {
    items: notas,
    empty: 'No hay notas todavía.'
} %}
    {% block row %}
        <li class="rounded-box border border-base-300 bg-base-100 p-3">
            <a class="font-semibold" href="{{ url('/notas/' ~ item.id) }}">{{ item.titulo }}</a>
            {% if item.cuerpo %}
                <p class="text-sm opacity-70">{{ item.cuerpo }}</p>
            {% endif %}
        </li>
    {% endblock %}
{% endembed %}
```

**Comprobación**: la lista se ve **exactamente** igual que antes, con y sin notas. Si borraste todas,
tiene que aparecer «No hay notas todavía.».

**Falla si**:

- La lista aparece vacía teniendo notas: `items` no está llegando; revisá el nombre de la variable.
- Error de Twig sobre `item`: dentro del bloque `row` la variable se llama `item`, no `nota`.

**Qué no prueba**: que el componente cubra todos los casos. Cubre lista, estado vacío y clases; no
cubre paginación, que es el paso siguiente.

**Pieza — `components/list/simple.twig`**: resuelve el `<ul>`, el recorrido y el estado vacío. Usalo
para cualquier listado simple. Evita repetir el `{% for %} … {% else %}` en cada pantalla y olvidarte
del vacío. Sin él tenés el mismo markup copiado en cada entidad, con variaciones accidentales.

---

## 12. Paginación del servidor

**Objetivo**: que la lista siga siendo usable cuando crezca.

**Estado de partida**: paso 11 hecho; con muchas notas la página se vuelve larga.

**Acción**: en el controlador, sumá `use Framework\Http\Pagination;` y `use Framework\Http\Url;`, y
reemplazá la lectura directa por la paginación:

```php
use Framework\Http\Pagination;
use Framework\Http\Url;

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
            'pagination' => $paginacion,
        ];
    }
```

`respond()` pasa a usar `$this->pageData($request) + $extra + [...]`, y en el parcial, al final:

```twig
{% include 'components/pagination/simple.twig' with {
    pagination: pagination,
    target: '#notas-panel'
} %}
```

**Comprobación**: creá más de diez notas. Esperado: la lista muestra diez y aparece la paginación
debajo; al pasar de página, cambia la lista sin recargar la página entera.

**Falla si**:

- La paginación muestra números pero no navega: falta `target` o el panel no tiene ese `id`.
- Al crear una nota desde la página 2 volvés a la 1: el formulario no está arrastrando `page` y
  `per_page` en su URL.
- Todo aparece en una página: no estás pasando `pagination` a la vista.

**Qué no prueba**: que el orden de las filas sea el correcto entre páginas. Prueba que el servidor
cuenta, recorta y navega; el orden lo define el `orderBy` que elegiste.

**Pieza — `Framework\Http\Pagination` + `components/pagination/simple.twig`**: resuelven el conteo,
el recorte y los controles, del lado del servidor. Usalos en toda lista que pueda crecer. Evitan el
`get()` pelado, que trae toda la tabla y funciona hasta que deja de funcionar. Sin esta pieza la
página se degrada en silencio: anda perfecto con veinte filas y se cae con dos mil.

---

## 13. Los dos transportes de CSRF

**Objetivo**: entender por qué hay dos formas de mandar el token, y cuándo va cada una.

**Estado de partida**: paso 12 hecho.

**Acción**: no hay código nuevo. Es la lectura de lo que ya tenés:

- **Dentro de un formulario**: los campos ocultos de `partials/_csrf.twig`. Es el caso del alta.
- **Fuera de un formulario**: un botón suelto que dispara una mutación manda el par por cabecera,
  con `hx-headers`.

```twig
<button class="btn btn-error"
        hx-delete="{{ url('/notas/' ~ item.id) }}"
        hx-target="#notas-panel"
        hx-headers='{"{{ csrf_name_key }}": "{{ csrf_name }}", "{{ csrf_value_key }}": "{{ csrf_value }}"}'>
    Borrar
</button>
```

**Comprobación**: en `/notas`, con el formulario de alta, guardá una nota (transporte de formulario).
Después, agregá temporalmente el botón de arriba a una fila y comprobá que el request sale con el
token en la cabecera: en las herramientas del navegador, la petición tiene el par `csrf` en los
headers y no en el cuerpo.

**Falla si**:

- Con HTMX recibís 400 y el evento `csrf` («Tu sesión expiró. Recargá la página.»): el token no está
  llegando, o el par de claves no coincide con el que emite `partials/_csrf.twig`.
- Sin HTMX, el mismo fallo se ve como 303 al login con «Tu sesión expiró. Volvé a iniciar sesión.»:
  es el mismo rechazo por otro canal, no otro problema.
- El botón suelto funciona pero el formulario no: estás en el caso inverso; revisá que el `include`
  esté **dentro** del `<form>`.

**Qué no prueba**: que el token no se pueda reutilizar ni nada de seguridad avanzada. Prueba que el
middleware recibe el token por el canal que el disparador usa.

**Pieza — los dos transportes**: resuelven el mismo problema con dos disparadores distintos. Usá
formulario cuando hay campos, cabecera cuando es un botón suelto. Evitan el 400 que aparece sólo en
algunas acciones y no en otras. Sin esta distinción, la mitad de tus mutaciones falla y la otra mitad
funciona, que es el peor escenario para diagnosticar.

---

## 14. Repetir el patrón con otra entidad

**Objetivo**: aplicar el mismo camino a una entidad propia, sin releer todo.

**Estado de partida**: pasos 1 a 13 hechos.

| Qué cambia | Qué no cambia |
|---|---|
| El nombre de la entidad y de la tabla | El orden de los pasos |
| Los campos y sus reglas de validación | Los dos transportes de CSRF |
| La vista y el parcial del panel | El patrón de `respond()` con `HX-Request` |
| Los textos visibles | La paginación del servidor |
| Los tests: qué datos usás | El `tearDown` que limpia |

**Los archivos a tocar**, en orden:

1. `database/migrations/NNN_create_<entidad>_table.php`
2. `app/Models/<Entidad>.php`
3. `routes/<entidad>.php` + una línea en `bootstrap/app.php`
4. `app/Http/Controllers/<Entidad>Controller.php`
5. `resources/views/<entidad>/index.twig`, `_panel.twig`, `show.twig`
6. `tests/Http/<Entidad>Test.php`

**Comprobación**: elegí una entidad distinta (por ejemplo `libros`, con `titulo` y `anio`) y recorré
esa lista. Esperado: al terminar, lista, crea, valida y responde por identificador, igual que
`notas`.

**Falla si**:

- Se repite el error de CSRF: te salteaste el `include` dentro del formulario.
- La lista aparece dentro de la página entera: te salteaste el `respond()` con `HX-Request`.

**Qué no prueba**: que el patrón sirva para cualquier entidad. Sirve para entidades simples con
listado y alta; una entidad con relaciones, borrado o edición necesita más piezas y más decisiones.

---

## Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| POST con HTMX responde 400 y avisa «Tu sesión expiró» | Falta el token de CSRF | `{% include 'partials/_csrf.twig' %}` dentro del `<form>`, o `hx-headers` si es un botón suelto |
| POST sin HTMX te manda al login con «Tu sesión expiró» | El mismo rechazo de CSRF, otro canal | Igual que arriba |
| El login vuelve al formulario | Credenciales que no coinciden con el admin sembrado | Volvé a sembrar con las mismas variables |
| `no such table` | Migración no aplicada en la base que usa la app | `vendor/bin/phinx status`, revisá `DB_DATABASE` |
| Página entera dentro del panel | El controlador devolvió la vista completa a HTMX | Elegí la plantilla con `hasHeader('HX-Request')` |
| El formulario se limpia al fallar | Falta el 422 o los valores de vuelta | Devolvé `errors`, `titulo` y `cuerpo` con status 422 |
| La lista trae todo | Falta `Pagination::paginate()` | Paso 12 |
| 404 mudo en el detalle | No se lanza `HttpNotFoundException` con mensaje | Paso 8 |

## Qué demuestra este recorrido

Que agregar una entidad a este framework tiene un orden, y que ese orden tiene comprobaciones. Las
piezas que usaste —migraciones, modelo, archivo de rutas, `render()`, validador, CSRF, lista,
paginación, `HttpTestCase`— no son opcionales: son el idioma del proyecto. El recorrido no las
inventa, las presenta cuando hacen falta.

Lo que queda pendiente y por qué: editar y borrar (agregan pasos sin enseñar nada nuevo), relaciones
entre entidades (otra conversación), y despliegue (tiene su propio material).
