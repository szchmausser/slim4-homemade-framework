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
}
