<?php

declare(strict_types=1);

use App\Http\Controllers\TaskController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Rutas del demo Tareas.
 *
 * Capa de rutas: el bootstrap carga todos los `routes/*.php` por convención
 * —`web.php` primero, el resto alfabético—, así que sumar una entidad es crear su
 * archivo, no editar el bootstrap. `PUT` y `DELETE` los dispara HTMX, y el CSRF
 * global exige token en cada mutación.
 *
 * Para tu entidad, copia el archivo y cambia el prefijo del grupo.
 */
return function (App $app) {
    // El middleware se monta sobre el grupo: protege las seis rutas de una vez y
    // es el lugar natural para la autorización de la entidad completa.
    $app->group('/tasks', function (RouteCollectorProxy $route) {
        $route->get('', [TaskController::class, 'index']);              // listado paginado
        $route->post('', [TaskController::class, 'store']);             // alta desde el formulario
        $route->get('/{id}/edit', [TaskController::class, 'edit']);     // fila en modo edición
        $route->post('/{id}/toggle', [TaskController::class, 'toggle']); // alternar pendiente/completado
        $route->put('/{id}', [TaskController::class, 'update']);        // guardar la edición
        $route->delete('/{id}', [TaskController::class, 'destroy']);    // borrar desde el modal
    })->add(RequireAuthMiddleware::class);
};
