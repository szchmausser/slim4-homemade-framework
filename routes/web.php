<?php

declare(strict_types=1);

use Framework\Auth\Auth;
use Framework\Http\CsrfTokens;
use App\Http\Controllers\Auth\AuthController;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

return function (App $app) {
    // Ruta raiz: pagina de presentacion del stack, con status 200. Sin ella
    // GET / devuelve 404 y el boton "Volver al inicio" de errors/error.twig
    // vuelve a ser un link muerto. Pública a propósito (la pide la app, no ella).
    // El layout necesita csrf (form de logout) y usuario: como acá se renderiza
    // directo sin Controller, van explícitos con la misma fuente (CsrfTokens).
    $app->get('/', function ($request, $response) use ($app) {
        $container = $app->getContainer();

        // Guard se resuelve acá (en request), no al registrar: al construirse
        // exige sesión iniciada y en registro todavía no hay ninguna.
        return $container->get(Twig::class)->render($response, 'home.twig', [
            'active'     => 'home',
            'user_id'    => Auth::id(),
            'user_email' => Auth::user()?->getAttribute('email'),
            ...CsrfTokens::fields($request, $container->get(Guard::class)),
        ]);
    });

    $app->get('/login', [AuthController::class, 'show']);
    $app->post('/login', [AuthController::class, 'store']);
    $app->post('/logout', [AuthController::class, 'destroy']);
};
