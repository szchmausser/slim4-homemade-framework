<?php

declare(strict_types=1);

namespace Tests\Support;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

/**
 * Los logs no pueden crecer sin límite en el teléfono. El logger resuelto
 * tiene que rotar por día y respetar LOG_MAX_FILES; el test restaura $_ENV
 * para no filtrar la configuración a otros tests.
 */
final class LoggingTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $env;

    protected function setUp(): void
    {
        $this->env = $_ENV;
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
    }

    public function test_logger_rota_diariamente_y_respeta_log_max_files(): void
    {
        $_ENV['LOG_MAX_FILES'] = '7';

        $handler = $this->loggerHandler();

        self::assertSame(7, $this->maxFiles($handler));

        $url = $handler->getUrl();
        self::assertIsString($url);
        self::assertStringEndsWith('/app-' . date('Y-m-d') . '.log', $url);
    }

    public function test_log_max_files_por_defecto_es_14(): void
    {
        unset($_ENV['LOG_MAX_FILES']);

        self::assertSame(14, $this->maxFiles($this->loggerHandler()));
    }

    private function loggerHandler(): RotatingFileHandler
    {
        $definitions = require dirname(__DIR__, 2) . '/config/container.php';
        $logger = $definitions[LoggerInterface::class]();

        self::assertInstanceOf(Logger::class, $logger);
        $handlers = $logger->getHandlers();
        self::assertCount(1, $handlers);
        self::assertInstanceOf(RotatingFileHandler::class, $handlers[0]);

        return $handlers[0];
    }

    private function maxFiles(RotatingFileHandler $handler): int
    {
        return (new ReflectionProperty(RotatingFileHandler::class, 'maxFiles'))->getValue($handler);
    }
}
