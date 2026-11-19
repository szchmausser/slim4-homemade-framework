<?php

declare(strict_types=1);

use App\Notas\Http\NotaController;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

/**
 * Rutas del demo Notas.
 *
 * El archivo devuelve una función que recibe la aplicación de Slim. El bootstrap
 * carga todos los `routes/*.php` por convención —`web.php` primero, el resto en
 * orden alfabético—, así que agregar una entidad es crear su archivo, no editar el
 * bootstrap.
 *
 * Cinco rutas y un controlador. La edición y el borrado también entran por `POST`
 * porque un formulario HTML solo envía `GET` y `POST`: el método no puede describir
 * la intención, la describe la URL. El CSRF es global, así que cada `POST` necesita
 * el token de `components/form/csrf.twig`.
 */
return function (App $app) {
    // El middleware se monta sobre el grupo: protege las cinco rutas de una sola vez
    // en vez de repetirlo en cada una. Es el lugar natural para la autorización de
    // una entidad completa.
    $app->group('/notas', function (RouteCollectorProxy $route) {
        $route->get('', [NotaController::class, 'index']);       // listado paginado
        $route->post('', [NotaController::class, 'store']);      // alta desde el modal
        $route->get('/{id}/edit', [NotaController::class, 'edit']); // formulario de edición
        $route->post('/{id}', [NotaController::class, 'update']); // guardar la edición
        $route->post('/{id}/delete', [NotaController::class, 'destroy']); // borrar
    })->add(RequireAuthMiddleware::class);
};
