<?php

declare(strict_types=1);

use App\Tasks\Http\TaskController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/tareas', function (RouteCollectorProxy $group) {
        $group->get('', [TaskController::class, 'index']);
        $group->post('', [TaskController::class, 'store']);
        $group->get('/{id}/edit', [TaskController::class, 'edit']);
        $group->post('/{id}/toggle', [TaskController::class, 'toggle']);
        $group->put('/{id}', [TaskController::class, 'update']);
        $group->delete('/{id}', [TaskController::class, 'destroy']);
    })->add(RequireAuthMiddleware::class);
};
