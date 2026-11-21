<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\I18n\LocaleResolver;
use PHPUnit\Framework\TestCase;

/**
 * Contrato del resolutor de locale: una función pura, sin sesión ni cabeceras
 * reales, que decide un código de la lista de catálogos disponibles.
 *
 * La tabla es el contrato completo: precedencia explícito > sesión >
 * `Accept-Language` > default, el parseo de verdad de la cabecera (q-values,
 * variantes regionales, comodín, basura) y la garantía de que nunca sale un
 * locale sin catálogo.
 */
final class LocaleResolverTest extends TestCase
{
    /**
     * Tabla de casos: `[explícito, sesión, Accept-Language, default, disponibles, esperado, qué fija]`.
     *
     * @var list<array{0: ?string, 1: ?string, 2: ?string, 3: string, 4: list<string>, 5: string, 6: string}>
     */
    private const CASOS = [
        [null, null, null, 'es', ['es', 'en'], 'es', 'sin nada devuelve el default'],
        [null, null, '', 'es', ['es', 'en'], 'es', 'una cabecera vacía no altera el default'],
        ['en', null, null, 'es', ['es', 'en'], 'en', 'el explícito disponible gana'],
        ['en', 'es', 'es', 'es', ['es', 'en'], 'en', 'el explícito gana sobre la sesión y la cabecera'],
        ['EN', null, '', 'es', ['es', 'en'], 'en', 'el explícito se compara sin distinguir mayúsculas'],
        ['fr', 'en', 'es', 'es', ['es', 'en'], 'en', 'un explícito sin catálogo se ignora y manda la sesión'],
        [null, 'en', 'es', 'es', ['es', 'en'], 'en', 'la sesión gana sobre Accept-Language'],
        [null, 'xx', 'en', 'es', ['es', 'en'], 'en', 'una sesión sin catálogo se ignora'],
        [null, null, 'en', 'es', ['es', 'en'], 'en', 'la cabecera negocia cuando no hay nada más'],
        [null, null, 'zh-CN,zh;q=0.9,en;q=0.8', 'es', ['es', 'en'], 'en', 'los idiomas sin catálogo se saltean y gana el siguiente con q'],
        [null, null, 'en;q=0', 'es', ['es', 'en'], 'es', 'q=0 significa «no acepto»: se descarta'],
        [null, null, 'es-MX', 'es', ['es', 'en'], 'es', 'una variante regional cae en el idioma base'],
        [null, null, 'en-US,en;q=0.9', 'es', ['es', 'en'], 'en', 'la variante regional y su idioma base resuelven el mismo catálogo'],
        [null, null, '*', 'es', ['es', 'en'], 'es', 'el comodín resuelve el default'],
        [null, null, ';;;,,q=x, en', 'es', ['es', 'en'], 'en', 'la basura se ignora sin romper el parseo'],
        [null, null, '%%% ,', 'es', ['es', 'en'], 'es', 'una cabecera sin nada reconocible cae al default'],
        [null, null, 'es;q=0.4,en;q=0.9', 'es', ['es', 'en'], 'en', 'manda el q más alto, no el orden de la cabecera'],
        [null, null, 'en;q=garbage', 'es', ['es', 'en'], 'es', 'un q que no es número descarta esa entrada'],
        [null, null, '', 'fr', ['es', 'en'], 'es', 'un default sin catálogo cae al primero disponible'],
        [null, null, '', 'es', [], 'es', 'sin catálogos disponibles devuelve el default tal cual'],
    ];

    public function test_resuelve_con_la_tabla_de_precedencia(): void
    {
        foreach (self::CASOS as [$explicit, $session, $header, $default, $available, $expected, $reason]) {
            $resolved = LocaleResolver::resolve($explicit, $session, $header, $default, $available);

            self::assertSame(
                $expected,
                $resolved,
                sprintf(
                    'No resolvió «%s» (explícito=%s, sesión=%s, cabecera=%s, default=%s, disponibles=[%s]).',
                    $reason,
                    var_export($explicit, true),
                    var_export($session, true),
                    var_export($header, true),
                    $default,
                    implode(', ', $available)
                )
            );

            if ($available !== []) {
                self::assertContains(
                    $resolved,
                    $available,
                    "Devolvió «{$resolved}», que no tiene catálogo en [" . implode(', ', $available) . '].'
                );
            }
        }
    }

    public function test_find_mapea_variantes_regionales_y_normaliza(): void
    {
        self::assertSame('es', LocaleResolver::find('es-MX', ['es', 'en']));
        self::assertSame('en', LocaleResolver::find('EN', ['es', 'en']));
        self::assertSame('pt_br', LocaleResolver::find('pt-BR', ['es', 'pt_br']));
        self::assertSame('pt_br', LocaleResolver::find('pt_br', ['es', 'pt_br']));
        self::assertNull(LocaleResolver::find('fr', ['es', 'en']));
        self::assertNull(LocaleResolver::find('', ['es', 'en']));
        self::assertNull(LocaleResolver::find('*', ['es', 'en']), 'El comodín lo resuelve resolve(), no find().');
        self::assertNull(LocaleResolver::find('../../en', ['es', 'en']), 'Un locale no puede salirse de la lista de catálogos.');
        self::assertNull(LocaleResolver::find('<script>', ['es', 'en']));
    }

    public function test_accept_language_se_ordena_por_q_y_descarta_lo_que_no_aplica(): void
    {
        self::assertSame([], LocaleResolver::accepted(null));
        self::assertSame([], LocaleResolver::accepted('  '));
        self::assertSame(['zh-cn', 'zh', 'en'], LocaleResolver::accepted('zh-CN,zh;q=0.9,en;q=0.8'));
        self::assertSame(['en', 'es'], LocaleResolver::accepted('es;q=0.4,en;q=0.9'));
        self::assertSame(['es'], LocaleResolver::accepted('en;q=0, es'));
        self::assertSame(['en-gb', 'en'], LocaleResolver::accepted('en;q=0.5, en-GB;q=0.9'));
        self::assertSame(['en'], LocaleResolver::accepted('basura;q=x, en;q=0.9'));
    }
}
