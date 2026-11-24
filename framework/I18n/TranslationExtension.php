<?php

declare(strict_types=1);

namespace Framework\I18n;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Traducción en Twig sin `symfony/twig-bridge` (L4).
 *
 * El bridge existe para esto, pero arrastra una superficie mucho más grande que
 * la necesaria: acá alcanza con dos alias del mismo método para cubrir las dos
 * formas que se leen cómodas en una plantilla.
 *
 *     {{ t('modal.close') }}
 *     {{ 'modal.close'|trans }}
 *     {{ t('pagination.showing', {'%from%': pagination.from}) }}
 *
 * `|num` y `|localdate` son la cara Twig de `Format`: el framework pone los
 * separadores y patrones del locale activo y la app elige la forma y los
 * decimales. Devuelven strings sin marcar como seguros: Twig los escapa como
 * cualquier otro texto.
 *
 *     {{ pagination.total|num }}
 *     {{ amount|num(2) }}
 *     {{ task.completed_at|localdate('numeric_time') }}
 *
 * La firma usa `Symfony\Contracts\Translation\TranslatorInterface` y no la
 * clase concreta: el contenedor expone la interfaz (S2) y las extensiones no
 * necesitan un `Translator` para traducir.
 */
final class TranslationExtension extends AbstractExtension
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('t', $this->translate(...))];
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', $this->translate(...)),
            new TwigFilter('num', Format::number(...)),
            new TwigFilter('localdate', Format::date(...)),
        ];
    }

    /**
     * @param array<string, string|int|float> $params
     */
    public function translate(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params);
    }
}
