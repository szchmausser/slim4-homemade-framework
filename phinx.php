<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
    ],
    'environments' => [
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name'    => __DIR__ . '/database/database',
            'suffix'  => '.sqlite',
        ],
    ],
];
