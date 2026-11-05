<?php

declare(strict_types=1);

namespace App\Support\Docs;

/**
 * Parser mínimo de los bloques de código Markdown que declaran de qué archivo
 * del repo salen (la línea de prosa justo encima del fence, con la ruta entre
 * backticks: "`resources/views/tasks/_list.twig`:").
 *
 * Existe por una razón concreta: `docs/guia-stack-php-termux.md` copia
 * archivos enteros, y esas copias se pudren solas (la mitad estaban viejas la
 * primera vez que se midió). Con este parser, `composer docs:check` compara
 * cada copia contra el archivo real y `composer docs:sync` la reescribe: la
 * fuente de verdad es el repo, no la guía.
 *
 * Un fence que es un FRAGMENTO (un método, tres líneas de un array) se marca
 * con `excerpt` en el info string y queda afuera del control: no es una copia,
 * así que no hay nada que comparar.
 */
final class CodeBlocks
{
    /** Extensiones que la guía copia enteras y por lo tanto se verifican. */
    public const VERIFIED_EXTENSIONS = ['php', 'twig', 'json', 'neon', 'sql'];

    /**
     * Parte el Markdown en bloques de código.
     *
     * @return list<array{line: int, end_line: int, info: string, content: string, intro: string}>
     *         line y end_line son 1-based (fence de apertura y de cierre).
     */
    public static function parse(string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $blocks = [];
        $opened = false;
        $info = '';
        $open = 0;
        $buffer = [];

        foreach ($lines as $i => $line) {
            if (!$opened) {
                // El info string puede traer espacios (`php excerpt`): se captura
                // entero. Si no, ese fence no se ve como apertura y el cierre se
                // lee como apertura nueva: el parseo queda invertido hasta el EOF.
                if (preg_match('/^```(.*)$/', $line, $match) === 1) {
                    $opened = true;
                    $info = trim($match[1]);
                    $open = $i;
                    $buffer = [];
                }

                continue;
            }

            if (preg_match('/^```\s*$/', $line) === 1) {
                $blocks[] = [
                    'line'     => $open + 1,
                    'end_line' => $i + 1,
                    'info'     => $info,
                    'content'  => implode("\n", $buffer),
                    'intro'    => self::introBefore($lines, $open),
                ];
                $opened = false;

                continue;
            }

            $buffer[] = $line;
        }

        return $blocks;
    }

    /**
     * La ruta del archivo que declara la prosa, si hay exactamente una y existe.
     * Dos rutas ("`a.php`, `b.php`:") = fence comparativo, no se verifica.
     */
    public static function resolvePath(string $intro, string $root): ?string
    {
        preg_match_all('/`([A-Za-z0-9_.\/-]+)\.([A-Za-z0-9]+)`/', $intro, $matches, PREG_SET_ORDER);

        $found = [];
        foreach ($matches as $match) {
            $path = $match[1] . '.' . $match[2];
            if (!in_array(strtolower($match[2]), self::VERIFIED_EXTENSIONS, true)) {
                continue;
            }
            // Nada de rutas absolutas ni escapes hacia afuera del repo.
            if (str_contains($path, '..') || str_starts_with($path, '/')) {
                continue;
            }
            if (!is_file($root . '/' . $path)) {
                continue;
            }

            $found[$path] = true;
        }

        return count($found) === 1 ? (string) array_key_first($found) : null;
    }

    /** `excerpt` en el info string: el bloque es un fragmento, no una copia. */
    public static function isExcerpt(string $info): bool
    {
        $tokens = preg_split('/\s+/', strtolower(trim($info))) ?: [];

        return in_array('excerpt', $tokens, true);
    }

    /** Normaliza para comparar: sin CRLF y sin el \n final (no cambia el código). */
    public static function normalize(string $code): string
    {
        return rtrim(str_replace(["\r\n", "\r"], "\n", $code), "\n");
    }

    /**
     * @param list<string> $lines
     */
    private static function introBefore(array $lines, int $fenceIndex): string
    {
        for ($i = $fenceIndex - 1; $i >= 0; $i--) {
            $candidate = trim($lines[$i]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
