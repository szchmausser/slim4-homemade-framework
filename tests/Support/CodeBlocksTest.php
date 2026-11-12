<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Docs\CodeBlocks;
use PHPUnit\Framework\TestCase;

/**
 * El parser que sostiene el candado de la guía. Si esto se rompe en silencio,
 * `docs:check` deja de verificar las copias y la guía vuelve a podrirse sin
 * que nadie se entere: por eso el parser tiene tests propios.
 */
final class CodeBlocksTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = dirname(__DIR__, 2) . '/storage/cache/tests/codeblocks-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/app', 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '' && is_dir($this->dir)) {
            $this->removeTree($this->dir);
        }
    }

    /**
     * Borrado recursivo en PHP: `rm -rf` no existe en Windows y dejaba
     * cientos de directorios de prueba en storage/cache/tests.
     */
    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->removeTree($path . '/' . $entry);
        }

        rmdir($path);
    }

    public function test_parse_encuentra_fences_con_info_intro_y_lineas(): void
    {
        $md = "# Título\n\nIntro suelta.\n\n`app/Modelo.php`:\n\n```php\n<?php echo 1;\n```\n\nTexto.\n\n```bash\necho hola\n```\n";

        $blocks = CodeBlocks::parse($md);

        self::assertCount(2, $blocks);
        self::assertSame('php', $blocks[0]['info']);
        self::assertSame('<?php echo 1;', $blocks[0]['content']);
        self::assertSame('`app/Modelo.php`:', $blocks[0]['intro']);
        self::assertSame(7, $blocks[0]['line'], 'La línea del fence de apertura es 1-based.');
        self::assertSame(9, $blocks[0]['end_line']);
        self::assertSame('bash', $blocks[1]['info']);
        self::assertSame('echo hola', $blocks[1]['content']);
    }

    public function test_parse_ignora_un_fence_sin_cerrar(): void
    {
        self::assertSame([], CodeBlocks::parse("```php\n<?php echo 1;\n"));
    }

    public function test_parse_acepta_info_con_espacios_y_no_desincroniza_el_resto(): void
    {
        // Bug real: con `/^```(\S*)$/` el fence `php excerpt` no se veía como
        // apertura y su cierre se leía como apertura nueva, invirtiendo el
        // parseo del archivo entero (menos bloques verificados, en silencio).
        $md = "```php excerpt\necho 1;\n```\n\ntexto\n\n```php\n<?php echo 2;\n```\n";

        $blocks = CodeBlocks::parse($md);

        self::assertCount(2, $blocks);
        self::assertSame('php excerpt', $blocks[0]['info']);
        self::assertSame('echo 1;', $blocks[0]['content']);
        self::assertSame('php', $blocks[1]['info']);
        self::assertSame('<?php echo 2;', $blocks[1]['content']);
    }

    public function test_intro_salta_lineas_vacias(): void
    {
        $md = "`app/Otro.php`:\n\n\n```php\n<?php\n```\n";

        self::assertSame('`app/Otro.php`:', CodeBlocks::parse($md)[0]['intro']);
    }

    public function test_resolve_path_acepta_una_ruta_existente(): void
    {
        file_put_contents($this->dir . '/app/Unica.php', "<?php\n");

        self::assertSame('app/Unica.php', CodeBlocks::resolvePath('`app/Unica.php`:', $this->dir));
        self::assertSame('app/Unica.php', CodeBlocks::resolvePath('Completa `app/Unica.php` (ojo con el secret):', $this->dir));
    }

    public function test_resolve_path_ignora_dos_rutas_porque_es_un_fence_comparativo(): void
    {
        file_put_contents($this->dir . '/app/A.php', "<?php\n");
        file_put_contents($this->dir . '/app/B.php', "<?php\n");

        self::assertNull(CodeBlocks::resolvePath('`app/A.php`, `app/B.php`:', $this->dir));
    }

    public function test_resolve_path_ignora_extensiones_no_copiadas_y_rutas_inexistentes(): void
    {
        file_put_contents($this->dir . '/app/Notas.md', "# x\n");

        self::assertNull(CodeBlocks::resolvePath('`app/Notas.md`:', $this->dir), 'Un .md no se verifica como copia.');
        self::assertNull(CodeBlocks::resolvePath('`app/Fantasma.php`:', $this->dir));
        self::assertNull(CodeBlocks::resolvePath('sin rutas acá', $this->dir));
    }

    public function test_resolve_path_no_escapa_del_repo(): void
    {
        file_put_contents($this->dir . '/afuera.php', "<?php\n");

        self::assertNull(CodeBlocks::resolvePath('`../afuera.php`:', $this->dir));
        self::assertNull(CodeBlocks::resolvePath('`' . $this->dir . '/afuera.php`:', $this->dir));
    }

    public function test_is_excerpt_reconoce_el_marcador(): void
    {
        self::assertTrue(CodeBlocks::isExcerpt('php excerpt'));
        self::assertTrue(CodeBlocks::isExcerpt('excerpt'));
        self::assertTrue(CodeBlocks::isExcerpt('PHP  EXCERPT'));
        self::assertFalse(CodeBlocks::isExcerpt('php'));
        self::assertFalse(CodeBlocks::isExcerpt(''));
        self::assertFalse(CodeBlocks::isExcerpt('excerpts'));
    }

    public function test_normalize_ignora_crlf_y_saltos_finales(): void
    {
        self::assertSame("a\nb", CodeBlocks::normalize("a\r\nb\r\n\n"));
        self::assertSame("a\nb", CodeBlocks::normalize("a\nb"));
        self::assertSame('', CodeBlocks::normalize("\n\n"));
    }
}
