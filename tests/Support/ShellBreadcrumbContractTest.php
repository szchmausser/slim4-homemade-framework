<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Contrato del shell: toda página que pinta el header declara su miga de pan.
 *
 * Por qué existe: el header decide entre la miga declarada y un fallback de un
 * solo «Inicio». Ese fallback es legítimo —el recorrido del tutorial pinta el
 * shell desde 6.2, tres pasos antes de enseñar `breadcrumbs`, y la portada usa
 * un único crumb—, pero mientras nadie lo compruebe un olvido se ve igual que
 * una página en la raíz: el header miente y nada falla.
 *
 * El marcador es el **header** (`id="collapse-btn"`), no el sidebar: una vista
 * puede reemplazar el bloque `sidebar` y entonces un marcador puesto ahí
 * apagaría el contrato entero sin que nadie lo note.
 *
 * Las rutas con `{placeholder}` quedan fuera porque necesitan datos; cada
 * entidad cubre su página de edición en su propio test.
 */
final class ShellBreadcrumbContractTest extends HttpTestCase
{
    public function test_toda_pagina_que_pinta_el_header_declara_su_miga(): void
    {
        self::loginAs();

        $app = self::app();
        $revisadas = [];

        foreach ($app->getRouteCollector()->getRoutes() as $route) {
            if (!in_array('GET', $route->getMethods(), true)) {
                continue;
            }

            $pattern = $route->getPattern();

            if (str_contains($pattern, '{')) {
                continue;
            }

            $response = $app->handle(self::request('GET', $pattern));

            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $html = (string) $response->getBody();

            if (!str_contains($html, 'id="collapse-btn"')) {
                continue;
            }

            $revisadas[] = $pattern;

            // La portada es la raíz: su miga correcta es un solo «Inicio» y no
            // debe emitir `<nav>`. Se pide, pero no se evalúa contra el nav.
            if ($pattern === '/') {
                continue;
            }

            self::assertStringContainsString(
                'aria-label="Miga de pan"',
                $html,
                "La página `{$pattern}` pinta el header y no declara `breadcrumbs`: "
                . 'el header cae en su fallback y muestra «Inicio» como página actual.'
            );

            // Una miga sin `current` pinta todos los crumbs como enlaces: la
            // página actual deja de estar marcada para el lector de pantalla.
            $nav = substr($html, strpos($html, 'aria-label="Miga de pan"'));
            $nav = substr($nav, 0, strpos($nav, '</nav>'));
            self::assertSame(
                1,
                substr_count($nav, 'aria-current="page"'),
                "La miga de `{$pattern}` no marca exactamente una página como actual."
            );
        }

        // La guarda no puede depender de que haya demos instalados, y sí tiene
        // que demostrar que el recorrido revisó algo: la portada siempre pinta
        // el header, también en un clon limpio.
        self::assertContains('/', $revisadas, 'El recorrido no revisó ninguna página con header.');
    }

    public function test_un_solo_crumb_no_emite_nav(): void
    {
        $html = (string) self::app()->getContainer()
            ->get(\Slim\Views\Twig::class)
            ->getEnvironment()
            ->render('partials/layout/header.twig', [
                'user_id' => 1,
                'user_email' => 'yo@ejemplo.com',
                'breadcrumbs' => [['label' => 'Inicio', 'icon' => 'home', 'current' => true]],
            ]);

        self::assertStringNotContainsString('aria-label="Miga de pan"', $html);
        self::assertStringContainsString('Inicio', $html);
    }
}
