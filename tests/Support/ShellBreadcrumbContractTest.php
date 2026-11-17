<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Contrato del shell: toda página que se pinta con `layouts/app.twig` declara
 * su miga de pan.
 *
 * Por qué existe: `partials/layout/header.twig` tiene un fallback silencioso
 * —un único «Inicio» marcado como actual— para cuando `breadcrumbs` no llega.
 * Ese fallback es el que hizo que el demo de notas dijera que estabas en la
 * portada sin que ningún test se enterara. El fallback se queda, porque las
 * páginas de error también usan el header y no tienen ruta que migar; lo que
 * cambia es que ahora el olvido se detecta.
 *
 * La señal es exacta: con una sola miga el header pinta un `<span>` sin
 * `<nav>`; con dos o más pinta `<nav class="breadcrumbs" aria-label="Miga de
 * pan">`. Su ausencia en una página con shell significa que nadie pasó
 * `breadcrumbs`.
 */
final class ShellBreadcrumbContractTest extends HttpTestCase
{
    public function test_toda_pagina_que_pinta_el_shell_declara_su_miga(): void
    {
        self::loginAs();

        $app = self::app();
        $revisadas = [];

        foreach ($app->getRouteCollector()->getRoutes() as $route) {
            if (!in_array('GET', $route->getMethods(), true)) {
                continue;
            }

            $pattern = $route->getPattern();

            // La portada es la raíz: su miga correcta es un solo «Inicio» y no
            // debe emitir `<nav>`. El fallback y esa miga se ven igual, así que
            // no hay señal que distinguir.
            if ($pattern === '/') {
                continue;
            }

            // Las rutas con parámetros necesitan datos que este contrato no
            // tiene: las cubre cada test de su entidad.
            if (str_contains($pattern, '{')) {
                continue;
            }

            $response = $app->handle(self::request('GET', $pattern));

            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $html = (string) $response->getBody();

            // Sin sidebar no es el shell: `/login` usa `layouts/auth.twig` y
            // los endpoints JSON no pintan HTML.
            if (!str_contains($html, 'id="sidebar"')) {
                continue;
            }

            $revisadas[] = $pattern;

            self::assertStringContainsString(
                'aria-label="Miga de pan"',
                $html,
                "La página `{$pattern}` pinta el shell y no declara `breadcrumbs`: "
                . 'el header cae en su fallback y muestra «Inicio» como página actual.'
            );
        }

        // Si el recorrido no encontró ninguna página, el contrato estaría
        // pasando por vacío: la app siempre tiene al menos la portada.
        self::assertNotSame([], $revisadas, 'El recorrido no encontró ninguna página que pinte el shell.');
    }

    public function test_el_fallback_del_header_no_finge_una_miga(): void
    {
        // Un solo crumb no emite `<nav>`: así se distingue «página sin miga»
        // de «página con miga» sin mirar el texto.
        $html = (string) self::app()->getContainer()
            ->get(\Slim\Views\Twig::class)
            ->getEnvironment()
            ->render('partials/layout/header.twig', [
                'user_id' => 1,
                'user_email' => 'yo@ejemplo.com',
            ]);

        self::assertStringNotContainsString('aria-label="Miga de pan"', $html);
        self::assertStringContainsString('Inicio', $html);
    }
}
