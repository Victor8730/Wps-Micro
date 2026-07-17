<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Config;
use Core\Vite;
use PHPUnit\Framework\TestCase;

final class ViteTest extends TestCase
{
    private ?string $manifestPath = null;

    protected function tearDown(): void
    {
        if ($this->manifestPath !== null && is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }
    }

    public function testItRendersDevelopmentClientAndEntryTags(): void
    {
        $vite = new Vite(new Config([
            'vite' => ['dev_server_url' => 'http://localhost:5173/'],
        ]));

        $tags = $vite->tags('resources/js/app.js');

        self::assertStringContainsString('http://localhost:5173/@vite/client', $tags);
        self::assertStringContainsString('http://localhost:5173/resources/js/app.js', $tags);
    }

    public function testItRendersProductionManifestAssets(): void
    {
        $this->manifestPath = tempnam(sys_get_temp_dir(), 'wps-vite-');
        file_put_contents($this->manifestPath, json_encode([
            'resources/js/app.js' => [
                'file' => 'assets/app-123.js',
                'css' => ['assets/app-123.css'],
                'imports' => ['_shared.js'],
            ],
            '_shared.js' => [
                'file' => 'assets/shared-456.js',
                'css' => ['assets/shared-456.css'],
            ],
        ], JSON_THROW_ON_ERROR));

        $vite = new Vite(new Config([
            'app' => ['url' => 'https://example.test'],
            'vite' => [
                'manifest_path' => $this->manifestPath,
                'build_path' => 'build',
            ],
        ]));

        $tags = $vite->tags('resources/js/app.js');

        self::assertStringContainsString('https://example.test/build/assets/app-123.css', $tags);
        self::assertStringContainsString('https://example.test/build/assets/shared-456.css', $tags);
        self::assertStringContainsString('rel="modulepreload"', $tags);
        self::assertStringContainsString('https://example.test/build/assets/app-123.js', $tags);
    }

    public function testItRejectsAnUnknownManifestEntry(): void
    {
        $this->manifestPath = tempnam(sys_get_temp_dir(), 'wps-vite-');
        file_put_contents($this->manifestPath, '{}');
        $vite = new Vite(new Config([
            'vite' => ['manifest_path' => $this->manifestPath],
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vite entry was not found');

        $vite->tags('resources/js/missing.js');
    }
}
