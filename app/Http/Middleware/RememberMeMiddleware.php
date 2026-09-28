<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RememberMe;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Resume la sesión desde la cookie remember-me, si hay una y no hay sesión.
 * Corre globalmente justo después de SessionMiddleware (ver config/middleware.php).
 * La rotación/limpieza viaja en ESTA misma respuesta: aplicada en otro lado,
 * el token nuevo nunca llegaría al navegador.
 */
final class RememberMeMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $outgoing = empty($_SESSION['user_id'])
            ? RememberMe::resumeFromCookie()
            : null;

        $response = $handler->handle($request);

        return $outgoing === null
            ? $response
            : $response->withAddedHeader('Set-Cookie', $outgoing);
    }
}
