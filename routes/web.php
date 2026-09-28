<?php

declare(strict_types=1);

use App\Http\Controllers\TaskController;
use Slim\App;
use Slim\Views\Twig;

return function (App $app) {
    // Ruta raiz: pagina de presentacion del stack, con status 200. Sin ella
    // GET / devuelve 404 y el boton "Volver al inicio" de errors/error.twig
    // vuelve a ser un link muerto. El formulario de altas vive solo en /tareas.
    $twig = $app->getContainer()->get(Twig::class);

    $app->get('/', function ($request, $response) use ($twig) {
        return $twig->render($response, 'home.twig');
    });

    $app->group('/tareas', function ($group) {
        $group->get('', [TaskController::class, 'index']);
        $group->post('', [TaskController::class, 'store']);
        $group->get('/{id}/edit', [TaskController::class, 'edit']);
        $group->put('/{id}', [TaskController::class, 'update']);
        $group->delete('/{id}', [TaskController::class, 'destroy']);
    });
};
