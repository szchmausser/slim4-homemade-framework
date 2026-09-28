<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Construye la app y el schema UNA sola vez para toda la suite.
Tests\Support\AppFactory::make();
