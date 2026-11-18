<?php

declare(strict_types=1);

use App\Tasks\Http\TaskController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/tareas', function (RouteCollectorProxy $route) {
        $route->get('', [TaskController::class, 'index']);
        $route->post('', [TaskController::class, 'store']);
        $route->get('/{id}/edit', [TaskController::class, 'edit']);
        $route->post('/{id}/toggle', [TaskController::class, 'toggle']);
        $route->put('/{id}', [TaskController::class, 'update']);
        $route->delete('/{id}', [TaskController::class, 'destroy']);
    })->add(RequireAuthMiddleware::class);
};
