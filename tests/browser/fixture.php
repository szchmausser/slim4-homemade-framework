<?php

declare(strict_types=1);

// Prepara la base para la suite de navegador. Uso: php tests/browser/fixture.php
//
// Deja un usuario conocido (las credenciales de `.env.example` no sirven: el
// admin real no existe en una base recién migrada) y una nota con título fijo,
// que es la que afirma el spec del listado. Idempotente: se puede correr dos
// veces sin duplicar filas.

use App\Models\User;

$root = dirname(__DIR__, 2);
chdir($root);

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';

$email = getenv('E2E_EMAIL') ?: 'e2e@ejemplo.com';
$password = getenv('E2E_PASSWORD') ?: 'secreto123';

$user = User::query()->where('email', $email)->first();

if ($user === null) {
    User::create([
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
} elseif (!password_verify($password, (string) $user->getAttribute('password_hash'))) {
    $user->setAttribute('password_hash', password_hash($password, PASSWORD_DEFAULT));
    $user->save();
}

echo "Usuario de prueba: {$email}\n";

$notaClass = 'App\\Notas\\Models\\Nota';

if (!class_exists($notaClass)) {
    echo "Demo notas no instalado: se omite la nota sembrada.\n";
    exit(0);
}

$titulo = 'Nota sembrada e2e';
$existe = $notaClass::query()->where('titulo', $titulo)->exists();

if (!$existe) {
    $notaClass::create([
        'titulo' => $titulo,
        'cuerpo' => 'Fila fija para el spec del listado.',
    ]);
}

echo "Nota sembrada: {$titulo}\n";
