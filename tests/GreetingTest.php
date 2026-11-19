<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/greeting.php';

final class GreetingTest extends TestCase
{
    public function testGreetingMatchesExactly(): void
    {
        self::assertSame('Hello from PHP in Docker! 🐳', getGreeting());
    }

    public function testGreetingContainsPhp(): void
    {
        self::assertMatchesRegularExpression('/\bPHP\b/', getGreeting());
    }

    public function testGreetingContainsDocker(): void
    {
        self::assertMatchesRegularExpression('/\bDocker\b/', getGreeting());
    }

    public function testApplicationPrintsGreetingAndExitsSuccessfully(): void
    {
        $command = [PHP_BINARY, __DIR__ . '/../src/index.php'];
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes);
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, (string) $stderr);
        self::assertSame('', $stderr);
        self::assertSame('Hello from PHP in Docker! 🐳' . PHP_EOL, $stdout);
    }
}
