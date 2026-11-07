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
        $script = self::view('resources/views/layouts/app.twig');

        self::assertPattern(
            "/matchMedia\\(\\s*'\\(min-width:\\s*1024px\\)'\\s*\\)/",
            $script,
            'app.twig no observa el breakpoint lg: sidebarOpen queda atado al ancho inicial y el sidebar se vuelve inalcanzable al cruzar 1024px.'
        );

        self::assertPattern(
            "/addEventListener\\(\\s*'change'[\\s\\S]{0,240}sidebarOpen/",
            $script,
            'el observador del breakpoint tiene que escribir sidebarOpen; si sólo lee el media query, no sincroniza nada.'
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
        $script = self::view('resources/views/layouts/app.twig');

        self::assertPattern(
            "/addEventListener\(\s*'htmx:beforeSwap'[\s\S]{0,900}xhr\.status\s*!==\s*422[\s\S]{0,400}shouldSwap\s*=\s*true[\s\S]{0,400}isError\s*=\s*false/",
            $script,
            'app.twig tiene que habilitar el swap del 422 (shouldSwap + isError): sin eso htmx descarta el cuerpo y el mensaje por campo no se ve nunca.'
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

        self::assertPattern(
            '/hx-on::after-request="([^"]*)"/',
            $form,
            'no encontré el handler hx-on::after-request del formulario de alta.'
        );
        preg_match('/hx-on::after-request="([^"]*)"/', $form, $handler);

        self::assertStringNotContainsString(
            'successful',
            $handler[1],
            'con el swap del 422 habilitado, successful es true en un fallo: la condición tiene que ser el status.'
        );
    }
}
