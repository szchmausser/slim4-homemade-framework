<?php

declare(strict_types=1);

// `composer serve` está retirado a propósito. Composer ejecuta cada script como
// subproceso con `process-timeout` (300 s por defecto) y lo mata al vencer:
// "The process ... exceeded the timeout of 300 seconds". Un servidor web no
// puede vivir dentro de un script de Composer, así que este guard existe sólo
// para que el comando viejo falle con la instrucción correcta en vez de morir
// a los 5 minutos sin explicación.
//
// Servidor de desarrollo: php -S 0.0.0.0:8080 -t public

fwrite(STDERR, <<<'TXT'

`composer serve` fue retirado.

Composer corre cada script como subproceso con `process-timeout` (300 s por
defecto) y lo mata al vencer:

    The process "php -S 0.0.0.0:8080 -t public" exceeded the timeout of 300 seconds

Un servidor web no puede vivir dentro de un script de un solo tiro. Usá el
comando directo:

    php -S 0.0.0.0:8080 -t public

TXT . PHP_EOL);

exit(1);
