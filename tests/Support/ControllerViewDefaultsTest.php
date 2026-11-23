<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Http\Controller;
use Framework\Http\Url;
use Framework\I18n\Trans;
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
 * Contrato de `Controller::viewDefaults()` y de las migas de pan.
 *
 * `viewDefaults()` es el punto de extensión por el que un controlador agrega
 * variables a todas sus vistas sin reescribir `render()` ni repetir `$extra` en
 * cada acción. El repo no lo sobrescribe en ningún lado, así que este test es lo
 * que garantiza que el hook funciona y que el orden de precedencia es el que
 * dice el docblock: `$extra` gana sobre `viewDefaults()`.
 *
 * `breadcrumbs()`/`breadcrumbsFor()` son las dos formas que el shell sabe
 * pintar (dos migas y tres migas). La forma importa: la clave `icon` viaja sólo
 * cuando hay icono (una miga con `icon => null` pinta un hueco) y `current` sólo
 * en la última.
 *
 * La miga raíz es la única que arma la base: sale del catálogo con
 * `Trans::t('nav.home')`, así que las aserciones la comparan contra el catálogo y
 * no contra un literal (el traductor es estado del proceso y acá no se fija: lo
 * que se mide es la FORMA de las migas). Que en español esa clave diga «Inicio»,
 * carácter por carácter, lo fija `I18nDemosTest::ESPANOL_DEMOS`.
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

    public function test_breadcrumbs_arma_dos_migas_con_icono(): void
    {
        self::assertSame([
            ['label' => Trans::t('nav.home'), 'href' => Url::path('/')],
            ['label' => 'Notas', 'icon' => 'list', 'current' => true],
        ], self::controlador()->migasParaTest('Notas', 'list'));
    }

    public function test_breadcrumbs_sin_icono_no_incluye_la_clave(): void
    {
        $crumbs = self::controlador()->migasParaTest('Notas');

        self::assertCount(2, $crumbs);
        self::assertSame('Notas', $crumbs[1]['label']);
        self::assertTrue($crumbs[1]['current']);
        self::assertArrayNotHasKey('icon', $crumbs[1]);
    }

    public function test_breadcrumbs_for_arma_tres_migas_con_la_ultima_actual(): void
    {
        self::assertSame([
            ['label' => Trans::t('nav.home'), 'href' => Url::path('/')],
            ['label' => 'Contactos', 'href' => '/contacts'],
            ['label' => 'Editar', 'current' => true],
        ], self::controlador()->migasForParaTest('Contactos', '/contacts', 'Editar'));
    }

    /**
     * Doble de `Controller`: hereda la base y publica los helpers protegidos.
     * El tipo nativo es la base; los métodos `*ParaTest` existen sólo en el
     * doble y se resuelven en runtime (tests/ queda fuera del alcance de PHPStan).
     */
    private static function controlador(): Controller
    {
        $twig = new Twig(new ArrayLoader([
            'fixture.twig' => 'marca:{{ marca }}|usuario:{{ user_id is defined ? user_id : "sin-sesion" }}',
        ]));

        return new class ($twig, new Guard(new ResponseFactory())) extends Controller {
            /**
             * @param array<string, mixed> $extra
             */
            public function renderParaTest(Request $request, Response $response, array $extra = []): Response
            {
                return $this->render($request, $response, 'fixture.twig', $extra);
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            public function migasParaTest(string $label, ?string $icon = null): array
            {
                return $this->breadcrumbs($label, $icon);
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            public function migasForParaTest(string $label, string $href, string $action): array
            {
                return $this->breadcrumbsFor($label, $href, $action);
            }

            /** @return array<string, mixed> */
            protected function viewDefaults(): array
            {
                return ['marca' => 'Notas'];
            }
        };
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function renderizar(array $extra): string
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/fixture');

        return (string) self::controlador()
            ->renderParaTest($request, new SlimResponse(), $extra)
            ->getBody();
    }
}
