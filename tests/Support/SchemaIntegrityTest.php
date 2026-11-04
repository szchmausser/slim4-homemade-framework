<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\RememberToken;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * El esquema es la promesa mas facil de romper en silencio: las migraciones
 * declaran la FK, el modelo la asume y la base puede estar ignorandola.
 * Estos tests corren contra las migraciones reales (tests/bootstrap.php).
 */
final class SchemaIntegrityTest extends TestCase
{
    public function testForeignKeysAreEnforcedByTheConnection(): void
    {
        $pdo = Capsule::connection()->getPdo();

        self::assertSame(
            1,
            (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn(),
            'La conexion corre con foreign_keys apagadas: las FK de las migraciones son decorativas.'
        );
    }

    public function testRememberTokensDeclareTheUserForeignKey(): void
    {
        $pdo = Capsule::connection()->getPdo();
        $rows = $pdo->query("PRAGMA foreign_key_list('remember_tokens')")->fetchAll(PDO::FETCH_ASSOC);

        $matches = array_filter(
            $rows,
            static fn (array $row): bool => $row['table'] === 'users'
                && $row['from'] === 'user_id'
                && strtoupper((string) $row['on_delete']) === 'CASCADE'
        );

        self::assertCount(1, $matches, 'remember_tokens.user_id deberia declarar ON DELETE CASCADE hacia users.');
    }

    public function testRememberTokensCascadeWhenTheUserIsDeleted(): void
    {
        $user = User::create([
            'email'         => 'cascade-' . bin2hex(random_bytes(6)) . '@example.test',
            'password_hash' => 'not-a-real-hash',
        ]);

        RememberToken::create([
            'selector'         => bin2hex(random_bytes(12)),
            'user_id'          => $user->id,
            'hashed_validator' => 'not-a-real-hash',
            'expires_at'       => time() + 60,
        ]);

        self::assertSame(1, RememberToken::where('user_id', $user->id)->count());

        $user->delete();

        self::assertSame(
            0,
            RememberToken::where('user_id', $user->id)->count(),
            'Al borrar el usuario, el CASCADE deberia limpiar sus remember_tokens.'
        );
    }

    public function testTasksNoLongerCarryTheDeadDoneColumn(): void
    {
        $pdo = Capsule::connection()->getPdo();
        $columns = $pdo->query("PRAGMA table_info('tasks')")->fetchAll(PDO::FETCH_COLUMN, 1);

        self::assertContains('status', $columns);
        self::assertContains('completed_at', $columns);
        self::assertNotContains('done', $columns, 'tasks.done fue reemplazada por status (migracion 005).');
    }
}
