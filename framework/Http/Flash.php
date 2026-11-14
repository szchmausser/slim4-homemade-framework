<?php

declare(strict_types=1);

namespace Framework\Http;

class Flash
{
    public static function set(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    public static function get(string $key): ?string
    {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);

        // El tipo de retorno es ?string: una sesión manipulada o un valor
        // corrupto no debe tumbar el render con un TypeError.
        return is_string($message) ? $message : null;
    }
}
