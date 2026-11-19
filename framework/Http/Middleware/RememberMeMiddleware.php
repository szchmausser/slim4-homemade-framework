<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Auth\RememberMe;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Resuelve la sesión desde la cookie remember-me cuando la petición no trae
 * usuario.
 *
 * Corre justo después de SessionMiddleware —la sesión tiene que estar abierta para
 * que `Auth::login()` escriba en ella— y antes de las rutas protegidas. La rotación
 * produce un `Set-Cookie` nuevo, y solo sirve si viaja en esta misma respuesta:
 * aplicado en otro lado, el token nuevo nunca llegaría al navegador.
 */
final class RememberMeMiddleware implements MiddlewareInterface
{
    /**
     * Intenta el resume antes de delegar y adjunta el `Set-Cookie` al final.
     *
     * El header se calcula antes de que exista la respuesta, así que se suma a la
     * que devuelve el handler.
     */
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
