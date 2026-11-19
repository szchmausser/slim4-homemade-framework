<?php

declare(strict_types=1);

use App\Contacts\Http\ContactController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Rutas del demo Contactos: seis entradas y un controlador.
 *
 * El bootstrap carga todos los `routes/*.php` por convención, así que agregar una
 * entidad es crear su archivo, no editar `bootstrap/app.php`. El middleware se
 * monta sobre el grupo y protege las seis rutas de una sola vez.
 *
 * Para tu entidad copia las seis y cambia el prefijo; el CSRF es global, así que
 * cada `POST` necesita el token de `components/form/csrf.twig`.
 */
return function (App $app) {
    $app->group('/contactos', function (RouteCollectorProxy $route) {
        $route->get('', [ContactController::class, 'index']);                 // página con la tabla
        $route->get('/datos', [ContactController::class, 'data']);            // JSON del modo remoto
        $route->post('', [ContactController::class, 'store']);                // alta desde el modal
        $route->get('/{id}/edit', [ContactController::class, 'edit']);        // formulario de edición
        $route->post('/{id}', [ContactController::class, 'update']);          // guardar la edición
        $route->post('/{id}/delete', [ContactController::class, 'destroy']);  // borrar
    })->add(RequireAuthMiddleware::class);
};
