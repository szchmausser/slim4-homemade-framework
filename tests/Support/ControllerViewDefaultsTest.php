<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Http\Controller;
use Framework\Http\Url;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response as SlimResponse;
use Slim\Views\Twig;
use Twig\Loader\ArrayLoader;

/**
 * Contrato de `Controller::viewDefaults()`.
 *
 * Es el punto de extensión por el que un controlador agrega variables a todas
 * sus vistas sin reescribir `render()` ni repetir `$extra` en cada acción. El
 * repo no lo sobrescribe en ningún lado, así que este test es lo que garantiza
 * que el hook funciona y que el orden de precedencia es el que dice el
 * docblock: `$extra` gana sobre `viewDefaults()`.
 */
final class ControllerViewDefaultsTest extends TestCase
{
    public function test_view_defaults_inyecta_en_todas_las_vistas(): void
    {
        self::assertStringContainsString('marca:Notas', self::renderizar([]));
    }

    public function test_lo_que_pasa_la_accion_gana_sobre_el_default(): void
    {
        $html = self::renderizar(['marca' => 'Otra']);

        self::assertStringContainsString('marca:Otra', $html);
        self::assertStringNotContainsString('marca:Notas', $html);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function renderizar(array $extra): string
    {
        $twig = new Twig(new ArrayLoader([
            'fixture.twig' => 'marca:{{ marca }}|usuario:{{ user_id is defined ? user_id : "sin-sesion" }}',
        ]));

        $controlador = new class ($twig, new Guard(new ResponseFactory())) extends Controller {
            /**
             * @param array<string, mixed> $extra
             */
            public function renderParaTest(Request $request, Response $response, array $extra = []): Response
            {
                return $this->render($request, $response, 'fixture.twig', $extra);
            }

            /** @return array<string, mixed> */
            protected function viewDefaults(): array
            {
                return ['marca' => 'Notas'];
            }
        };

        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/fixture');

        return (string) $controlador->renderParaTest($request, new SlimResponse(), $extra)->getBody();
    }
}
