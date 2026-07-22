<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Config;

final class ConfigTest extends TestCase
{
    public function testItReadsNestedValuesWithDotNotation(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'WPS Micro',
            ],
        ]);

        self::assertSame('WPS Micro', $config->get('app.name'));
        self::assertSame('fallback', $config->get('app.missing', 'fallback'));
    }
}
