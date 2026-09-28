<?php

declare(strict_types=1);

namespace App\Support;

use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as V;

class Validator
{
    private array $errors = [];

    public static function make(array $data, array $rules): self
    {
        $instance = new self();
        $instance->run($data, $rules);
        return $instance;
    }

    private function run(array $data, array $rules): void
    {
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;

            try {
                $this->buildChain($ruleString)->assert($value);
            } catch (NestedValidationException $e) {
                $this->errors[$field] = $e->getMessages();
            }
        }
    }

    private function buildChain(string $ruleString): V
    {
        $chain = V::create();

        foreach (explode('|', $ruleString) as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

            $chain = match ($name) {
                'required' => $chain->notEmpty(),
                'string'   => $chain->stringType(),
                'email'    => $chain->email(),
                'numeric'  => $chain->numeric(),
                'max'      => $chain->length(null, (int) $param),
                'min'      => $chain->length((int) $param, null),
                default    => $chain,
            };
        }

        return $chain;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return array_values($messages)[0] ?? null;
        }
        return null;
    }
}
