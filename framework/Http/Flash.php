<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Mensajes de un solo uso hasta la petición siguiente.
 *
 * Es el puente entre una mutación y el GET al que redirige: el controlador hace
 * `set()` antes del 303 y `Controller::render()` consume con `get()`, que borra la
 * clave para que el aviso no reaparezca al refrescar. También lo escriben
 * RequireAuthMiddleware y el failure handler del CSRF, que no tienen una vista
 * propia donde mostrar el aviso.
 */
class Flash
{
    /**
     * Deja un mensaje para la próxima lectura en el canal indicado (`error`,
     * `success`, `info`).
     *
     * Si ya había uno en ese canal, se pierde: el contrato es un aviso por canal.
     *
     * @param string $key canal del aviso, no su texto
     */
    public static function set(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Devuelve el mensaje del canal y lo borra en el mismo paso, o null si no hay.
     *
     * El `is_string` protege el render: una sesión manipulada o un valor corrupto
     * devuelve null en vez de lanzar un TypeError por el tipo de retorno.
     */
    public static function get(string $key): ?string
    {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);

        return is_string($message) ? $message : null;
    }
}
