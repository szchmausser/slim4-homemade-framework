<?php

declare(strict_types=1);

use App\Contacts\Http\ContactController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/contactos', function (RouteCollectorProxy $group) {
        $group->get('', [ContactController::class, 'index']);
        $group->get('/datos', [ContactController::class, 'data']);
        $group->post('', [ContactController::class, 'store']);
        $group->get('/{id}/edit', [ContactController::class, 'edit']);
        $group->post('/{id}', [ContactController::class, 'update']);
        $group->post('/{id}/delete', [ContactController::class, 'destroy']);
    })->add(RequireAuthMiddleware::class);
};
