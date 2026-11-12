<?php

declare(strict_types=1);

namespace Framework\Validation;

use InvalidArgumentException;
use Respect\Validation\ChainedValidator;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as V;

class Validator
{
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

            // TODAS las reglas se resuelven antes de evaluar la primera: una
            // regla mal escrita tiene que explotar siempre, aunque una regla
            // anterior ya hubiera fallado y no se llegue a evaluar (S2).
            $resolved = [];
            foreach (explode('|', (string) $ruleString) as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                $resolved[] = $this->rule($name, $param, $field, (string) $ruleString);
            }

            foreach ($resolved as [$chain, $message]) {
                try {
                    $chain->assert($value);
                    continue;
                } catch (ValidationException) {
                    // Primera regla que falla, único mensaje del campo: seguir
                    // evaluando el resto sólo cambiaría el texto del aviso (el
                    // campo ya es inválido) y hacía que el orden interno de
                    // Respect eligiera el mensaje en vez del orden de las
                    // reglas.
                    $this->errors[$field] = $message;
                    break;
                }
            }
        }
    }

    /**
     * UNA sola fuente por regla: el validador y su mensaje salen del mismo
     * brazo del match.
     *
     * Antes había tres lugares que decían lo mismo —la lista `RULES`, el match
     * de `passes()` y el de `message()`—, así que agregar una regla eran tres
     * ediciones que podían divergir sin que nada fallara, y el `default` de
     * cada match era código inalcanzable porque `parse()` ya había filtrado el
     * nombre. Aquí el único `default` es el que rechaza.
     *
     * `required` es `notEmpty()` (los espacios cuentan como vacío) y `max`/`min`
     * son `length()` (cuenta caracteres, no bytes), como fijaba la suite desde
     * antes. `numeric` va por `numericVal()`: `numeric()` no existe como nombre
     * de regla y lanzaba una ComponentException en vez de validar.
     *
     * @return array{0: ChainedValidator, 1: string}
     */
    private function rule(string $name, ?string $param, string $field, string $ruleString): array
    {
        // `max`/`min` son las únicas con parámetro: se lee una vez y lo usan el
        // validador y el mensaje, así no pueden discrepar.
        $limit = in_array($name, ['max', 'min'], true)
            ? $this->lengthParam($name, $param, $field, $ruleString)
            : null;

        return match ($name) {
            'required' => [V::notEmpty(), 'Este campo es obligatorio.'],
            'string'   => [V::stringType(), 'Este campo debe ser texto.'],
            'email'    => [V::email(), 'Este campo debe ser un email válido.'],
            'numeric'  => [V::numericVal(), 'Este campo debe ser un número.'],
            'max'      => [V::length(null, (int) $limit), sprintf('No puede superar los %d caracteres.', $limit)],
            'min'      => [V::length((int) $limit, null), sprintf('Debe tener al menos %d caracteres.', $limit)],
            // El string completo viaja en el mensaje: es el dato que hace
            // falta para encontrar el template roto.
            default    => throw new InvalidArgumentException(sprintf(
                'Unknown validation rule "%s" for field "%s" in "%s".',
                $name,
                $field,
                $ruleString
            )),
        };
    }

    /**
     * `(int) null` convertía un `max` sin número en `length(0)`: el campo no
     * podía pasar nunca y nadie sabía por qué. Un parámetro que no es un entero
     * positivo es un template roto, no un dato del usuario.
     */
    private function lengthParam(string $name, ?string $param, string $field, string $ruleString): int
    {
        if ($param === null || !ctype_digit($param)) {
            throw new InvalidArgumentException(sprintf(
                'Validation rule "%s" for field "%s" needs a numeric parameter in "%s".',
                $name,
                $field,
                $ruleString
            ));
        }

        return (int) $param;
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
