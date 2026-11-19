<?php

declare(strict_types=1);

namespace Framework\Validation;

use InvalidArgumentException;
use Respect\Validation\ChainedValidator;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Validator as V;

/**
 * Valida un array de datos contra reglas escritas como texto
 * (`'campo' => 'required|max:120'`).
 *
 * Traduce cada regla a un validador de respect/validation y a su mensaje en
 * castellano, y corta en el primer fallo de cada campo: un campo inválido produce
 * un solo mensaje, el de la primera regla que falló. Los controladores la usan
 * antes de tocar la base y las vistas reciben el mapa `campo => mensaje`.
 */
class Validator
{
    /** @var array<string, string> campo => mensaje, uno solo por campo. */
    private array $errors = [];

    /**
     * Construye el validador y corre todas las reglas de una vez.
     *
     * Es el único camino de construcción y no hay setters: un validador ya
     * ejecutado solo se consulta.
     *
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
     * Recorre los campos en el orden declarado y deja un mensaje por cada uno que
     * falle; el orden del mapa resultante es el del template.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     */
    private function run(array $data, array $rules): void
    {
        foreach ($rules as $key => $ruleString) {
            $field = (string) $key;
            $value = $data[$field] ?? null;

            // Todas las reglas del campo se resuelven antes de evaluar la
            // primera: una regla mal escrita tiene que explotar siempre, aunque
            // otra del mismo campo ya haya fallado y nunca se llegue a ella.
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
                    // Primera regla que falla, y único mensaje del campo. Seguir
                    // evaluando solo cambiaría el texto del aviso y dejaría que el
                    // orden interno de Respect elija el mensaje en vez del orden de
                    // las reglas escritas en el template.
                    $this->errors[$field] = $message;
                    break;
                }
            }
        }
    }

    /**
     * Traduce una regla del texto a su validador y su mensaje.
     *
     * Validador y mensaje salen del mismo `match` para que no puedan describir
     * cosas distintas. `required` es `notEmpty()` (los espacios cuentan como
     * vacío), `max` y `min` son `length()` (caracteres, no bytes) y `numeric` va
     * por `numericVal()`, que es el nombre que existe en Respect.
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
     * Lee el número de `max`/`min` una sola vez, para que el validador y el
     * mensaje no puedan discrepar, y rechaza el template roto con una excepción.
     *
     * Un parámetro que no es un entero positivo no es un dato del usuario: sin
     * este chequeo, `(int) null` daría `length(0)` y el campo no pasaría nunca sin
     * decir por qué.
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

    /**
     * ¿Quedó algún campo inválido? Es lo que consulta el controlador antes de tocar
     * la base.
     */
    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Mapa `campo => mensaje` con un solo mensaje por campo; vacío si todo pasó.
     *
     * @return array<string, string> campo => mensaje.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Primer mensaje del mapa, en el orden en que se declararon los campos.
     *
     * Lo usa el flash de un formulario que no tiene lugar para errores por campo.
     */
    public function firstError(): ?string
    {
        foreach ($this->errors as $message) {
            return $message;
        }
        return null;
    }
}
