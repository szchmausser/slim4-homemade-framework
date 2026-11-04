<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * El piso de PHP es una promesa del proyecto: si el manifiesto, el lock y el
 * runtime se desalinean, el clon deja de instalar en Termux (platform_check
 * falla antes de arrancar). Este test fija los tres lados para que el desvío
 * falle en la suite y no en la máquina del usuario.
 */
final class RuntimeRequirementsTest extends TestCase
{
    private const REQUIRED_PHP = '>=8.4.1';
    private const REQUIRED_PHP_ID = 80401;

    public function testRuntimeSatisfiesDeclaredFloor(): void
    {
        self::assertGreaterThanOrEqual(
            self::REQUIRED_PHP_ID,
            PHP_VERSION_ID,
            'El runtime no cumple el piso declarado (' . self::REQUIRED_PHP . ').'
        );
    }

    public function testComposerManifestDeclaresTheSameFloor(): void
    {
        $manifest = $this->readJson('composer.json');

        self::assertSame(self::REQUIRED_PHP, $manifest['require']['php'] ?? null);
    }

    public function testLockedPlatformMatchesTheFloor(): void
    {
        $lock = $this->readJson('composer.lock');

        self::assertSame(self::REQUIRED_PHP, $lock['platform']['php'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $file): array
    {
        $path = dirname(__DIR__, 2) . '/' . $file;
        $raw = file_get_contents($path);

        self::assertIsString($raw, "No se pudo leer {$file}.");

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
