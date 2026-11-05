<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * S2: una clase que la plantilla referencia y el bundle vendorizado no trae se
 * ve como "sin estilo", y nadie lo nota hasta mirar esa pantalla. Este test
 * compara las clases de resources/views contra daisyUI 5.7.46 leyendo los
 * archivos: sin navegador, sin base de datos y sin arrancar la app.
 *
 * El bundle NO es la autoridad de todas las clases. Hay dos familias:
 *
 * 1. daisyUI: sus componentes (btn, input, label, modal, ...), sus
 *    modificadores (btn-ghost, input-sm) y sus colores semanticos usados como
 *    utilidad (text-success, bg-base-100/95, outline-primary). De esta familia
 *    el bundle SI es la autoridad, y es lo que este test vigila.
 * 2. Utilidades planas de Tailwind (flex, mb-4, h-5, text-2xl, opacity-70,
 *    bg-black/50, focus-visible:outline-2, ...): las resuelve en runtime
 *    public/assets/tailwind-browser-4.3.3.js y no estan en el bundle.
 *    Marcarlas seria un falso positivo en casi cada linea, asi que se
 *    reconocen por su forma y quedan fuera.
 *
 * Tampoco se valida contra el bundle lo que el propio proyecto declara en sus
 * <style> (toast-*, sidebar-*): esas clases existen, solo que no las trae
 * daisyUI.
 */
final class ViewClassIntegrityTest extends TestCase
{
    private const BUNDLE = 'public/assets/daisyui-5.7.46.css';

    /**
     * Raices que daisyUI 4 publicaba y la 5 elimino. No se pueden derivar del
     * bundle porque justamente ya no estan: sin esta lista `form-control` (el
     * wrapper de formulario de la v4) se confundiria con una utilidad de
     * Tailwind y pasaria en silencio. Es vocabulario de la familia daisyUI, no
     * una lista de clases muertas esperadas: corregir las plantillas no cambia
     * nada aca.
     */
    private const LEGACY_DAISY_ROOTS = ['form'];

    /**
     * @var array{classes: array<string, list<string>>, dead: array<string, list<string>>}|null
     */
    private static ?array $scan = null;

    public function testTemplatesDoNotReferenceDaisyClassesMissingFromTheVendoredBundle(): void
    {
        $scan = $this->scan();

        self::assertGreaterThan(
            150,
            count($scan['classes']),
            'La extraccion quedo vacia o casi: el guard estaria pasando por no mirar nada.'
        );
        self::assertSame([], $scan['dead'], $this->deadMessage($scan['dead']));
    }

    /**
     * La extraccion es la mitad dificil del guard: una clase puede vivir en el
     * texto del atributo, en un literal dentro de una condicion Twig o en un
     * binding de Alpine. Si solo se leyera el `class="..."` literal, el test
     * pasaria igual y no protegeria nada.
     *
     * Se prueba contra fragmentos SINTETICOS y no contra las plantillas: si
     * dependiera de una clase real, sacarla del proyecto (que es justamente lo
     * que hacen las tareas siguientes) romperia este test sin que el extractor
     * se haya roto. La extraccion es una funcion del texto; se prueba como tal.
     */
    public function testExtractionReadsClassLiteralsInsideTwigConditionsAndAlpineBindings(): void
    {
        // Atributo plano: lo que esta fuera de los delimitadores es literal.
        self::assertSame(
            ['btn', 'btn-primary'],
            $this->classTokens('class', 'btn btn-primary  ')
        );

        // Literal dentro de una condicion Twig: `{{ ok ? 'text-success' : '' }}`.
        self::assertContains(
            'text-success',
            $this->classTokens('class', "btn {{ ok ? 'text-success' : '' }}"),
            'un literal dentro de {{ }} es una clase en potencia'
        );

        // Binding de Alpine: las clases viajan entre comillas simples, y la
        // variante responsive se descarta para juzgar la clase base.
        $alpine = $this->classTokens(':class', "sidebarMini && 'lg:tooltip lg:tooltip-right'");
        self::assertContains('tooltip', $alpine);
        self::assertContains('tooltip-right', $alpine);
        self::assertNotContains('lg:tooltip', $alpine, 'la variante no forma parte del nombre');

        // Variante de estado sobre una utilidad de color: se juzga la base.
        self::assertSame(
            ['outline-primary'],
            $this->classTokens('class', 'focus-visible:outline-primary')
        );

        // `x-transition:*` lleva la lista suelta, igual que un `class`.
        self::assertSame(
            ['transition', 'ease-out', 'duration-200'],
            $this->classTokens('x-transition:enter', 'transition ease-out duration-200')
        );

        // Un bloque `{% %}` no aporta clases: adentro vive dato, no markup
        // (`active == 'tareas'`, `'completado'`). Si se extrajera, esos valores
        // se reportarian como clases muertas. El texto FUERA del bloque sí es
        // una clase real, porque Twig lo renderiza tal cual.
        self::assertSame(
            ['flex', 'hidden'],
            $this->classTokens('class', "flex {% set y = 'ghost' %} hidden")
        );
    }

    /**
     * @return array{classes: array<string, list<string>>, dead: array<string, list<string>>}
     */
    private function scan(): array
    {
        if (self::$scan !== null) {
            return self::$scan;
        }

        $root = dirname(__DIR__, 2);
        $templates = $this->templateFiles($root);
        self::assertNotSame([], $templates, 'No se encontro ninguna plantilla en resources/views.');

        $css = file_get_contents($root . '/' . self::BUNDLE);
        self::assertIsString($css, 'No se pudo leer el bundle ' . self::BUNDLE . '.');

        // El bundle y el CSS propio del proyecto son las dos unicas fuentes
        // que pueden hacer existir una clase; lo que no este en ninguna de las
        // dos y tenga forma de daisyUI es una clase muerta.
        $bundle = $this->bundleClasses($css);
        $local = $this->projectClasses($root, $templates);
        $colors = $this->daisyColors($css);
        $roots = $this->componentRoots($bundle);

        $classes = $this->referencedClasses($root, $templates);

        $dead = [];
        foreach ($classes as $class => $where) {
            if (isset($bundle[$class]) || isset($local[$class])) {
                continue;
            }
            if (!$this->isDaisyClass($class, $colors['colors'], $colors['groups'], $roots)) {
                continue;
            }
            $unique = array_values(array_unique($where));
            sort($unique);
            $dead[$class] = $unique;
        }
        ksort($dead);

        return self::$scan = ['classes' => $classes, 'dead' => $dead];
    }

    /**
     * @return list<string> rutas relativas a la raiz del proyecto, ordenadas
     */
    private function templateFiles(string $root): array
    {
        $directory = $root . '/resources/views';
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'twig') {
                continue;
            }
            $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }

        sort($files);

        return $files;
    }

    /**
     * Extrae las clases de cada atributo `class`, `:class` y de los
     * `x-transition:*` de Alpine (que tambien reciben una lista de clases).
     * Los bloques {% %} se saltean a proposito: ahi `'home'` o `'completado'`
     * son datos, no clases. Los `{# ... #}` tampoco cuentan: un comentario no
     * se renderiza.
     *
     * @param list<string> $files
     * @return array<string, list<string>> clase normalizada => "ruta:linea"
     */
    private function referencedClasses(string $root, array $files): array
    {
        $classes = [];

        foreach ($files as $relative) {
            $source = (string) file_get_contents($root . '/' . $relative);

            $comments = [];
            preg_match_all('/\{#.*?#\}/s', $source, $commentMatches, PREG_OFFSET_CAPTURE);
            foreach ($commentMatches[0] as [$comment, $commentOffset]) {
                $comments[] = [$commentOffset, $commentOffset + strlen($comment)];
            }

            preg_match_all(
                '/(?<![\w:-])([:@]?class|x-transition(?::[a-z-]+)?)\s*=\s*"([^"]*)"/',
                $source,
                $attributes,
                PREG_OFFSET_CAPTURE | PREG_SET_ORDER
            );

            foreach ($attributes as $attribute) {
                $offset = $attribute[0][1];
                if ($this->insideComment($offset, $comments)) {
                    continue;
                }

                $where = $relative . ':' . (substr_count(substr($source, 0, $offset), "\n") + 1);
                foreach ($this->classTokens($attribute[1][0], $attribute[2][0]) as $class) {
                    $classes[$class][] = $where;
                }
            }
        }

        ksort($classes);

        return $classes;
    }

    /**
     * @param list<array{0: int, 1: int}> $comments
     */
    private function insideComment(int $offset, array $comments): bool
    {
        foreach ($comments as [$start, $end]) {
            if ($offset >= $start && $offset < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function classTokens(string $attribute, string $value): array
    {
        $fragments = [];

        if ($attribute === ':class') {
            // Alpine: las clases viajan entre comillas simples dentro de la
            // expresion, incluso como claves de un objeto.
            preg_match_all("/'([^']*)'/", $value, $literals);
            $fragments = $literals[1];
        } else {
            // Twig: lo que esta fuera de los delimitadores es texto literal;
            // dentro de `{{ ... }}`, los literales son clases en potencia. En
            // `x-transition:*` la lista viene suelta, como en un `class`.
            $parts = preg_split('/(\{\{.*?\}\}|\{%.*?%\})/s', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
            foreach ($parts as $part) {
                if (str_starts_with($part, '{%')) {
                    continue;
                }
                if (str_starts_with($part, '{{')) {
                    preg_match_all("/'([^']*)'/", $part, $literals);
                    array_push($fragments, ...$literals[1]);
                    continue;
                }
                $fragments[] = $part;
            }
        }

        $classes = [];
        foreach ($fragments as $fragment) {
            foreach (preg_split('/\s+/', trim($fragment)) ?: [] as $raw) {
                $class = $this->baseClass($raw);
                if ($class !== null) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    /**
     * Deja el token en la forma en que se juzga: sin prefijo de variante
     * (`focus-visible:outline-primary` -> `outline-primary`, `lg:tooltip` ->
     * `tooltip`) y sin el `-` de las utilidades negativas. Un token que no
     * tiene forma de clase (puntuacion, expresiones, HTML) se descarta.
     */
    private function baseClass(string $raw): ?string
    {
        if ($raw === '' || preg_match('/^-?[a-zA-Z][a-zA-Z0-9:._\/%-]*$/', $raw) !== 1) {
            return null;
        }

        while (preg_match('/^[a-z0-9-]+:(.+)$/', $raw, $variant) === 1) {
            $raw = $variant[1];
        }

        $raw = ltrim($raw, '-');

        return $raw === '' ? null : $raw;
    }

    /**
     * Nombres de clase que define el bundle. Las variantes responsive viajan
     * escapadas (`.\32 xl\:alert`) y no sirven como autoridad de la clase
     * base, asi que se descartan. El `/` de las utilidades de color tambien
     * viaja escapado (`bg-base-100\/90`), pero se normaliza para comparar:
     * el nombre que se escribe en la plantilla es `bg-base-100/90`.
     *
     * @return array<string, true>
     */
    private function bundleClasses(string $css): array
    {
        preg_match_all('/\.([a-zA-Z_-][a-zA-Z0-9_-]*(?:\\\\\/[0-9.]+)?)(?![a-zA-Z0-9_-]|\\\\)/', $css, $matches);

        $classes = [];
        foreach ($matches[1] as $token) {
            $classes[str_replace('\\/', '/', $token)] = true;
        }

        return $classes;
    }

    /**
     * Clases que el proyecto define en sus propios <style>. Se leen del
     * template que las declara para que sigan al dia si el CSS se mueve; los
     * comentarios CSS se descartan para no tomar como definida una clase que
     * solo se menciona en una explicacion.
     *
     * @param list<string> $files
     * @return array<string, true>
     */
    private function projectClasses(string $root, array $files): array
    {
        $classes = [];

        foreach ($files as $relative) {
            $source = (string) file_get_contents($root . '/' . $relative);
            if (preg_match_all('~<style[^>]*>(.*?)</style>~is', $source, $blocks) === 0) {
                continue;
            }
            foreach ($blocks[1] as $css) {
                $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
                preg_match_all('/\.([a-zA-Z][a-zA-Z0-9_-]*)/', $css, $matches);
                foreach ($matches[1] as $class) {
                    $classes[$class] = true;
                }
            }
        }

        return $classes;
    }

    /**
     * Tokens semanticos de color que daisyUI publica en el tema, derivados de
     * sus custom properties (`--color-primary`, `--color-base-100`). Se
     * excluye `black`: es un escalar de la paleta cruda, no un token
     * semantico, y `bg-black/50` es una utilidad de Tailwind que el bundle no
     * tiene por que traer.
     *
     * @return array{colors: array<string, true>, groups: array<string, true>}
     */
    private function daisyColors(string $css): array
    {
        preg_match_all('/--color-([a-z0-9-]+)\s*:/', $css, $matches);

        $colors = [];
        $groups = [];
        foreach (array_unique($matches[1]) as $token) {
            if (in_array($token, ['black', 'white', 'transparent', 'current'], true)) {
                continue;
            }
            $colors[$token] = true;
            $groups[explode('-', $token)[0]] = true;
        }

        return ['colors' => $colors, 'groups' => $groups];
    }

    /**
     * Una raiz de componente es un nombre de UNA sola palabra en el bundle
     * (`btn`, `input`, `label`, `select`, `modal`, ...). Los modificadores
     * (`input-sm`, `btn-ghost`) y las utilidades de color (`text-success`) no
     * son raices: por eso `text-2xl` o `rounded-xl` no entran al conjunto de
     * daisyUI. Queda una zona gris conocida: utilidades de Tailwind que
     * empiezan con el nombre de un componente (`select-none`, `table-fixed`,
     * `mask-*`, `tab-4`). Ninguna se usa hoy en las plantillas; si aparece
     * una, hay que excluirla aca en vez de aflojar la regla.
     *
     * @param array<string, true> $bundle
     * @return array<string, true>
     */
    private function componentRoots(array $bundle): array
    {
        $roots = [];
        foreach (array_keys($bundle) as $class) {
            if (!str_contains($class, '-')) {
                $roots[$class] = true;
            }
        }

        return $roots;
    }

    /**
     * Familia 1: lo que daisyUI tiene que proveer. Dos formas:
     *
     * - utilidad de color con un token semantico (`text-success`,
     *   `bg-base-100/95`, `outline-primary`), con opacidad incluida. El
     *   prefijo se compara con la lista completa de utilidades de color
     *   porque el bundle solo emite `bg-*`/`text-*`/`border-*`: si falta
     *   `outline-primary`, tambien faltarian `ring-primary`, etc.
     * - modificador de un componente que el bundle si define
     *   (`input-bordered`, `label-text`, `select-bordered`).
     *
     * El resto (`flex`, `mb-4`, `bg-black/50`, `text-slate-300`) es Tailwind
     * y queda fuera: lo resuelve el browser build en runtime.
     *
     * @param array<string, true> $colors
     * @param array<string, true> $groups
     * @param array<string, true> $roots
     */
    private function isDaisyClass(string $class, array $colors, array $groups, array $roots): bool
    {
        $colorPrefixes = [
            'text', 'bg', 'border', 'outline', 'ring-offset', 'ring', 'fill', 'stroke',
            'divide', 'from', 'via', 'to', 'shadow', 'caret', 'decoration', 'placeholder', 'accent',
        ];

        foreach ($colorPrefixes as $prefix) {
            if (!str_starts_with($class, $prefix . '-')) {
                continue;
            }
            $value = (string) preg_replace('~/(?:[0-9]+|\[[^\]]+\])$~', '', substr($class, strlen($prefix) + 1));
            if (isset($colors[$value])) {
                return true;
            }
            foreach (array_keys($groups) as $group) {
                // Un sufijo que no existe (`bg-base-999`) sigue siendo un typo
                // de daisyUI, no una utilidad de Tailwind.
                if (str_starts_with($value, $group . '-')) {
                    return true;
                }
            }
        }

        $root = explode('-', $class)[0];

        return isset($roots[$root]) || in_array($root, self::LEGACY_DAISY_ROOTS, true);
    }

    /**
     * @param array<string, list<string>> $dead
     */
    private function deadMessage(array $dead): string
    {
        $lines = ['Clases referenciadas en resources/views que no existen en ' . self::BUNDLE . ':'];
        foreach ($dead as $class => $where) {
            $lines[] = '  - ' . $class . ' (' . implode(', ', $where) . ')';
        }

        return implode("\n", $lines);
    }
}
