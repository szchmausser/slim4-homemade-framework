<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Validator;
use Phinx\Seed\AbstractSeed;

/**
 * Admin inicial. Los seeds son para DATOS (las migraciones son para SCHEMA):
 * corren con `seed:run`, quedan registrados y se pueden repetir sin duplicar.
 *
 * El secret sale de entorno real o de `.env` (ojo: Dotenv immutable NO usa
 * putenv, así que `.env` llega a $_ENV pero nunca a getenv: se miran ambos).
 * Nunca de argumentos (visibles en `ps`) ni hardcodeado (viaja en git).
 * Por eso el bootstrap se carga ANTES de leer (Dotenv puebla $_ENV ahí).
 */
final class AdminSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../bootstrap/app.php';

        // $_ENV primero (ahí cae `.env` vía Dotenv), getenv después (entorno
        // real). Con Dotenv immutable el real gana en ambos si está en los dos.
        $email = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: null;
        $password = $_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: null;

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required|min:8']
        );

        if ($email === null || $validator->fails()) {
            throw new \RuntimeException(
                'ADMIN_EMAIL y ADMIN_PASSWORD (8+ caracteres) son obligatorios: ' .
                'ADMIN_EMAIL=x ADMIN_PASSWORD=... vendor/bin/phinx seed:run -s AdminSeeder'
            );
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]
        );

        echo "OK admin {$user->email} (id {$user->id})" . PHP_EOL;
    }
}
