<?php

declare(strict_types=1);

use App\Contacts\Http\ContactController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/contactos', function (RouteCollectorProxy $route) {
        $route->get('', [ContactController::class, 'index']);
        $route->get('/datos', [ContactController::class, 'data']);
        $route->post('', [ContactController::class, 'store']);
        $route->get('/{id}/edit', [ContactController::class, 'edit']);
        $route->post('/{id}', [ContactController::class, 'update']);
        $route->post('/{id}/delete', [ContactController::class, 'destroy']);
    })->add(RequireAuthMiddleware::class);
};
