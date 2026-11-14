<?php

declare(strict_types=1);

// Los warnings/notices no se imprimen en HTML: filtran rutas absolutas y pueden
// romper la sesión o el stream de una respuesta. Van al log, no al navegador.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// El SAPI agrega X-Powered-By aunque la respuesta sea PSR-7. Quitarlo no cambia
// el comportamiento y reduce la huella del stack.
header_remove('X-Powered-By');

$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
