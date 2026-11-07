<?php

declare(strict_types=1);

namespace Framework\Validation;

use InvalidArgumentException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as V;

class Validator
{
    /**
     * Reglas que entiende la sintaxis `'campo' => 'required|max:120'`. `max` y
     * `min` comparten `length()`: la regla sola no trae largos, los trae el
     * parámetro, y sin esta lista un typo (`requird`) se descartaba en silencio.
     */
    private const RULES = ['required', 'string', 'email', 'numeric', 'max', 'min'];

    /** @var array<string, string> campo => mensaje, uno solo por campo. */
    private array $errors = [];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     */
    public static function make(array $data, array $rules): self
    {
        $instance = new self();
        $instance->run($data, $rules);
        return $instance;
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     */
    private function run(array $data, array $rules): void
    {
        foreach ($rules as $key => $ruleString) {
            $field = (string) $key;
            $value = $data[$field] ?? null;

            // TODAS las reglas se parsean antes de evaluar la primera: una
            // regla mal escrita tiene que explotar siempre, aunque una regla
            // anterior ya hubiera fallado y no se llegue a evaluar (S2).
            foreach ($this->parse((string) $ruleString, $field) as [$name, $param]) {
                if ($this->passes($name, $param, $value)) {
                    continue;
                }

                // Primera regla que falla, único mensaje del campo: seguir
                // evaluando el resto sólo cambiaría el texto del aviso (el
                // campo ya es inválido) y hacía que el orden interno de
                // Respect eligiera el mensaje en vez del orden de las reglas.
                $this->errors[$field] = $this->message($name, $param);
                continue 2;
            }
        }
    }

    /**
     * Convierte `required|max:120` en pares regla/parámetro, validando el
     * nombre y el número del largo. Cualquier cosa fuera de lugar corta acá con
     * una excepción que nombra la regla, el campo y el string completo: es el
     * dato que hace falta para encontrar el template roto.
     *
     * @return list<array{0: string, 1: string|null}>
     */
    private function parse(string $ruleString, string $field): array
    {
        $parsed = [];

        foreach (explode('|', $ruleString) as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

            if (!in_array($name, self::RULES, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown validation rule "%s" for field "%s" in "%s".',
                    $name,
                    $field,
                    $ruleString
                ));
            }

            // `(int) null` convertía un `max` sin número en `length(0)`: el
            // campo no podía pasar nunca y nadie sabía por qué.
            if (($name === 'max' || $name === 'min') && ($param === null || !ctype_digit($param))) {
                throw new InvalidArgumentException(sprintf(
                    'Validation rule "%s" for field "%s" needs a numeric parameter in "%s".',
                    $name,
                    $field,
                    $ruleString
                ));
            }

            $parsed[] = [$name, $param];
        }

        return $parsed;
    }

    /**
     * Una regla por vez, con las mismas primitivas de Respect que usaba la
     * cadena: `required` es `notEmpty()` (los espacios cuentan como vacío) y
     * `max`/`min` son `length()` (cuenta caracteres, no bytes). `numeric` va
     * por `numericVal()`: `numeric()` no existe como nombre de regla y lanzaba
     * una ComponentException en vez de validar.
     */
    private function passes(string $name, ?string $param, mixed $value): bool
    {
        $rule = match ($name) {
            'required' => V::notEmpty(),
            'string'   => V::stringType(),
            'email'    => V::email(),
            'numeric'  => V::numericVal(),
            'max'      => V::length(null, (int) $param),
            'min'      => V::length((int) $param, null),
            default    => throw new InvalidArgumentException(sprintf('Unsupported validation rule "%s".', $name)),
        };

        try {
            $rule->assert($value);
        } catch (ValidationException) {
            return false;
        }

        return true;
    }

    /**
     * Mensaje en castellano y sin el nombre del campo: la vista ya lo nombra
     * con su label, y los textos de Respect vienen en inglés y con su propio
     * vocabulario (`notEmpty`, `length`), que no es el de estas reglas (S3).
     */
    private function message(string $name, ?string $param): string
    {
        return match ($name) {
            'required' => 'Este campo es obligatorio.',
            'string'   => 'Este campo debe ser texto.',
            'email'    => 'Este campo debe ser un email válido.',
            'numeric'  => 'Este campo debe ser un número.',
            'max'      => sprintf('No puede superar los %d caracteres.', (int) $param),
            'min'      => sprintf('Debe tener al menos %d caracteres.', (int) $param),
            default    => throw new InvalidArgumentException(sprintf('Unsupported validation rule "%s".', $name)),
        };
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    /** @return array<string, string> campo => mensaje. */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $message) {
            return $message;
        }
        return null;
    }
}
