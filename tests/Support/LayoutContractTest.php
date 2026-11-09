<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Contratos del shell que no se pueden probar por HTTP porque viven enteros en
 * el cliente: PHPUnit no ejecuta JavaScript ni simula un viewport.
 *
 * El criterio es el mismo que usa ViewClassIntegrityTest para el CSS: se
 * verifica el mecanismo, no el efecto visual. Es una guarda de regresión y se
 * lee como tal; si alguien cambia la forma de sincronizar el estado, actualiza
 * la guarda con el mismo criterio con el que cambia el layout.
 *
 * Las aserciones de JS se hacen sobre el CUERPO de la función, extraído por
 * nombre, y no sobre el archivo entero con ventanas de longitud. Una ventana
 * (`[\s\S]{0,900}`) se rompe por un comentario nuevo, una línea movida o un
 * rename cosmético, y el que llega después no distingue "el JS está mal" de
 * "el número quedó corto".
 */
final class LayoutContractTest extends TestCase
{
    private static function view(string $relative): string
    {
        $path = dirname(__DIR__, 2) . '/' . $relative;

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * `assertMatchesRegularExpression` imprime el asunto entero al fallar, y
     * acá el asunto son 550 líneas de layout. Este helper conserva el mensaje
     * propio y evita el volcado.
     */
    private static function assertPattern(string $pattern, string $subject, string $message): void
    {
        self::assertSame(1, preg_match($pattern, $subject), $message);
    }

    /**
     * Devuelve el cuerpo de `function <name>(...) { ... }` contando llaves.
     *
     * Alcanza para las funciones de este layout, que no tienen llaves dentro de
     * strings ni de comentarios. Si alguna vez las tiene, el conteo hay que
     * hacerlo con un tokenizador, no con esta función: la guarda avisaría con
     * un cuerpo truncado en vez de mentir en silencio.
     */
    private static function jsFunction(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        self::assertNotFalse($start, "no encontré la función {$name}() en el layout");

        $open = strpos($source, '{', $start);
        self::assertNotFalse($open, "la función {$name}() no tiene cuerpo");

        $depth = 0;
        $length = strlen($source);

        for ($i = $open; $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;
                continue;
            }

            if ($source[$i] !== '}') {
                continue;
            }

            $depth--;

            if ($depth === 0) {
                return substr($source, $open, $i - $open + 1);
            }
        }

        self::fail("la función {$name}() tiene las llaves desbalanceadas");
    }

    /**
     * Devuelve la etiqueta de apertura completa (`<button …>`, `<label …>`)
     * que matchea `$pattern`.
     *
     * Los atributos de un control se juzgan sobre SU etiqueta y no sobre el
     * header entero: buscar `x-cloak` o `aria-label` en todo el archivo da
     * verde con el control de al lado bien hecho y éste sin nada.
     */
    private static function openingTag(string $source, string $pattern, string $message): string
    {
        self::assertSame(1, preg_match($pattern, $source, $match), $message);

        return $match[0];
    }

    /**
     * S1: `sidebarOpen` se inicializa leyendo `window.innerWidth` UNA vez. Sin
     * un observador del breakpoint, achicar o estirar la ventana deja el estado
     * pegado al ancho con el que se cargó la página: al pasar a escritorio el
     * sidebar se ve (lg:translate-x-0) pero sigue inert, sin foco ni click en
     * los enlaces ni en "Salir", y no hay control que lo reabra porque el
     * hamburguesa es lg:hidden. Al revés, en móvil el drawer queda abierto
     * tapando el contenido.
     */
    public function test_el_sidebar_sigue_el_breakpoint_cuando_cambia_el_viewport(): void
    {
        $shell = self::jsFunction(self::view('resources/views/layouts/app.twig'), 'shell');

        self::assertPattern(
            "/matchMedia\(\s*'\(min-width:\s*1024px\)'\s*\)/",
            $shell,
            'shell() no observa el breakpoint lg: sidebarOpen queda atado al ancho inicial y el sidebar se vuelve inalcanzable al cruzar 1024px.'
        );

        self::assertPattern(
            "/addEventListener\(\s*'change'[\s\S]*sidebarOpen\s*=\s*e\.matches/",
            $shell,
            'el observador del breakpoint tiene que escribir sidebarOpen con el resultado del media query; si sólo lo lee, no sincroniza nada.'
        );
    }

    /**
     * S1 (la otra mitad): el `inert` que cierra el sidebar tiene que depender
     * del MISMO estado que sincroniza el breakpoint. Si alguien lo ata a otra
     * cosa —`window.innerWidth` en línea, un booleano aparte—, los dos caminos
     * vuelven a divergir y la guarda de arriba deja de proteger.
     */
    public function test_el_inert_del_sidebar_depende_del_estado_sincronizado(): void
    {
        $sidebar = self::view('resources/views/partials/layout/sidebar.twig');

        self::assertPattern(
            '/x-bind:inert="!sidebarOpen"/',
            $sidebar,
            'el sidebar debe atar inert a sidebarOpen, que es el estado que sincroniza el breakpoint.'
        );
    }

    /**
     * S5: un 422 no es un fallo de transporte, es la respuesta con el panel
     * re-renderizado y el mensaje de validación adentro. htmx 2.0.11 no
     * swapea NINGÚN 4xx por default (`responseHandling`:
     * `[{code:"204",swap:false},{code:"[23]..",swap:true},{code:"[45]..",swap:false,error:true}]`),
     * así que sin este handler el mensaje por campo se quedaría en el cable:
     * el panel no cambia, el 422 llega como error y el usuario sólo vería el
     * toast genérico de `notifyHtmxFailures()`.
     *
     * `isError = false` es la otra mitad: es el flag del que htmx deriva
     * `failed`/`successful` y el que decide si emite `htmx:responseError`.
     * Swapear sin apagarlo dejaría el toast genérico denunciando un fallo que
     * ya está explicado en el formulario.
     */
    public function test_el_layout_permite_el_swap_de_un_422(): void
    {
        $allow = self::jsFunction(self::view('resources/views/layouts/app.twig'), 'allowValidationSwap');

        self::assertPattern(
            "/addEventListener\(\s*'htmx:beforeSwap'/",
            $allow,
            'allowValidationSwap() tiene que escuchar htmx:beforeSwap; sin ese evento htmx ya decidió descartar el cuerpo del 422.'
        );

        self::assertPattern(
            '/xhr\.status\s*!==\s*422/',
            $allow,
            'el handler tiene que acotarse al 422: los demás 4xx conservan el canal de error de htmx.'
        );

        self::assertPattern(
            '/shouldSwap\s*=\s*true/',
            $allow,
            'un 422 sin shouldSwap = true no se intercambia y el mensaje por campo no se ve nunca.'
        );

        self::assertPattern(
            '/isError\s*=\s*false/',
            $allow,
            'isError tiene que quedar en false: si no, htmx emite htmx:responseError y el toast genérico denuncia un fallo que el formulario ya explica.'
        );
    }

    /**
     * S6: el formulario de alta vive FUERA de `#tareas-panel`, así que el swap
     * no lo toca y el texto tipeado quedaría pegado tras un alta exitosa. La
     * condición para limpiarlo no puede ser `event.detail.successful`: con el
     * swap del 422 habilitado (S5) esa bandera pasa a ser true en un fallo de
     * validación, y el usuario perdería justo lo que tiene que corregir. Lo
     * que distingue éxito de fallo es el status del servidor.
     */
    public function test_el_alta_de_tareas_solo_se_limpia_con_un_200_del_servidor(): void
    {
        $form = self::view('framework/scaffolds/tasks/resources/views/tasks/index.twig');

        self::assertPattern(
            '/hx-on::after-request="if \(event\.detail\.xhr\.status === 200\) this\.reset\(\)"/',
            $form,
            'el alta tiene que limpiarse sólo con un 200: en un 422 el texto tipeado es lo único que el usuario tiene para corregir.'
        );

        // La condición se juzga en el ATRIBUTO, no en el archivo: el comentario
        // de arriba nombra `event.detail.successful` justamente para explicar
        // por qué no se usa, y buscarlo en todo el template daría un falso
        // positivo por documentación.
        preg_match('/hx-on::after-request="([^"]*)"/', $form, $handler);
        self::assertArrayHasKey(1, $handler, 'no encontré el handler hx-on::after-request del alta.');

        self::assertStringNotContainsString(
            'successful',
            $handler[1],
            'con el swap del 422 habilitado, successful es true en un fallo: la condición tiene que ser el status.'
        );
    }

    /**
     * S2: el nombre accesible del header no puede depender de Alpine.
     *
     * `:aria-label` es una directiva, no un atributo ARIA: antes de que Alpine
     * arranque —y con JS apagado, para siempre— el HTML no tiene `aria-label`
     * ni texto hijo, así que el lector de pantalla anuncia sólo «botón». El
     * sidebar ya resolvió esto con `aria-label` estático; el header tiene que
     * hacer lo mismo. La directiva se conserva: Alpine sobrescribe el atributo
     * en runtime y el estático es únicamente el valor de arranque.
     */
    public function test_el_header_expone_nombres_accesibles_estaticos(): void
    {
        $header = self::view('resources/views/partials/layout/header.twig');

        $hamburger = self::openingTag(
            $header,
            '/<button\b[^>]*@click="sidebarOpen = !sidebarOpen"[^>]*>/',
            'no encontré el hamburguesa (@click="sidebarOpen = !sidebarOpen") en el header.'
        );

        self::assertPattern(
            '/\saria-label="Abrir menú"/',
            $hamburger,
            'el hamburguesa no tiene aria-label estático: hasta que Alpine arranca, y sin JS, el botón no anuncia nada y el lector de pantalla dice sólo «botón».'
        );

        $collapse = self::openingTag(
            $header,
            '/<button\b[^>]*@click="toggleMini\(\)"[^>]*>/',
            'no encontré el botón de plegar (@click="toggleMini()") en el header.'
        );

        self::assertPattern(
            '/\saria-label="Plegar menú"/',
            $collapse,
            'el botón de plegar no tiene aria-label estático: con el menú desplegado, el control que lo pliega queda sin nombre hasta que Alpine arranca.'
        );
    }

    /**
     * S2 (la otra mitad): un control que sólo funciona con Alpine no puede
     * pintarse como si ya funcionara. Sin `x-cloak` el hamburguesa, el de
     * plegar y el toggle de tema aparecen antes de que Alpine arranque y, con
     * JS apagado, quedan para siempre. El toggle es el peor de los tres:
     * cambia sol/luna y no toca `data-theme`, así que parece que hace algo y no
     * hace nada.
     */
    public function test_los_controles_que_dependen_de_alpine_se_ocultan_antes_de_arrancar(): void
    {
        $header = self::view('resources/views/partials/layout/header.twig');

        $controls = [
            'el hamburguesa' => '/<button\b[^>]*@click="sidebarOpen = !sidebarOpen"[^>]*>/',
            'el botón de plegar' => '/<button\b[^>]*@click="toggleMini\(\)"[^>]*>/',
            'el toggle de tema' => '/<label\b[^>]*\bswap-rotate\b[^>]*>/',
        ];

        foreach ($controls as $name => $pattern) {
            $tag = self::openingTag($header, $pattern, "no encontré {$name} en el header.");

            self::assertPattern(
                '/\sx-cloak[\s>]/',
                $tag,
                "{$name} no lleva x-cloak: se pinta antes de que Alpine arranque y, con JS apagado, queda a la vista un control que no hace nada."
            );
        }

        self::assertPattern(
            '/\[x-cloak\]\s*\{\s*display:\s*none\s*!important;?\s*\}/',
            self::view('resources/views/layouts/app.twig'),
            'app.twig no define [x-cloak] { display: none !important; }: sin la regla el atributo no oculta nada y los tres controles se ven igual que antes.'
        );
    }

    /**
     * Los bindings que ya estaban no se mudan al atributo estático: Alpine
     * sigue siendo dueño del estado en runtime. Si alguien borra la directiva,
     * el nombre deja de seguir al estado y el toggle deja de escribir `theme`.
     */
    public function test_los_controles_del_header_conservan_sus_bindings_de_alpine(): void
    {
        $header = self::view('resources/views/partials/layout/header.twig');

        $hamburger = self::openingTag(
            $header,
            '/<button\b[^>]*@click="sidebarOpen = !sidebarOpen"[^>]*>/',
            'no encontré el hamburguesa (@click="sidebarOpen = !sidebarOpen") en el header.'
        );

        self::assertPattern(
            '/:aria-label="sidebarOpen \? \'Cerrar menú\' : \'Abrir menú\'"/',
            $hamburger,
            'el hamburguesa perdió su :aria-label: el nombre tiene que seguir al estado abierto/cerrado en runtime.'
        );

        self::assertPattern(
            '/:aria-expanded="sidebarOpen \? \'true\' : \'false\'"/',
            $hamburger,
            'el hamburguesa perdió :aria-expanded y el estado del menú deja de anunciarse.'
        );

        $collapse = self::openingTag(
            $header,
            '/<button\b[^>]*@click="toggleMini\(\)"[^>]*>/',
            'no encontré el botón de plegar (@click="toggleMini()") en el header.'
        );

        self::assertPattern(
            '/:aria-label="sidebarMini \? \'Desplegar menú\' : \'Plegar menú\'"/',
            $collapse,
            'el botón de plegar perdió su :aria-label: el nombre tiene que seguir al estado plegado/desplegado en runtime.'
        );

        $theme = self::openingTag(
            $header,
            '/<input\b[^>]*>/',
            'no encontré el checkbox del toggle de tema en el header.'
        );

        self::assertPattern(
            '/\saria-label="Cambiar entre tema claro y oscuro"/',
            $theme,
            'el toggle de tema perdió su aria-label estático y queda sin nombre accesible.'
        );

        self::assertPattern(
            '/:checked="theme === \'dark\'"/',
            $theme,
            'el checkbox del tema perdió :checked y deja de reflejar el tema activo.'
        );

        self::assertPattern(
            '/@change="theme = \$event\.target\.checked \? \'dark\' : \'light\'"/',
            $theme,
            'el checkbox del tema perdió el @change que escribe theme; el sol/luna cambiaría y data-theme no.'
        );
    }
}
