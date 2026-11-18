<?php

declare(strict_types=1);

use App\Notas\Http\NotaController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/notas', function (RouteCollectorProxy $route) {
        $route->get('', [NotaController::class, 'index']);
        $route->post('', [NotaController::class, 'store']);
        $route->get('/{id}/edit', [NotaController::class, 'edit']);
        $route->post('/{id}', [NotaController::class, 'update']);
        $route->post('/{id}/delete', [NotaController::class, 'destroy']);
    })->add(RequireAuthMiddleware::class);
};
