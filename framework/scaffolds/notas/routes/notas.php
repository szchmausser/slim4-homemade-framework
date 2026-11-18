<?php

declare(strict_types=1);

use App\Notas\Http\NotaController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/notas', function (RouteCollectorProxy $group) {
        $group->get('', [NotaController::class, 'index']);
        $group->post('', [NotaController::class, 'store']);
        $group->get('/{id}/edit', [NotaController::class, 'edit']);
        $group->post('/{id}', [NotaController::class, 'update']);
        $group->post('/{id}/delete', [NotaController::class, 'destroy']);
    })->add(RequireAuthMiddleware::class);
};
