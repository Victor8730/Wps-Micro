<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ProductionConfigTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMissingEnvironmentUsesSafeProductionDefaults(): void
    {
        foreach (['APP_ENV', 'APP_DEBUG', 'SESSION_SECURE', 'TWIG_AUTO_RELOAD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        $config = require dirname(__DIR__, 2) . '/Config/app.php';

        self::assertSame('production', $config['app']['env']);
        self::assertFalse($config['app']['debug']);
        self::assertTrue($config['session']['secure']);
        self::assertFalse($config['twig']['auto_reload']);
    }
}
