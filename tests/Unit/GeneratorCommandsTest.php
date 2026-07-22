<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Console\Commands\MakeControllerCommand;
use WpsMicro\Core\Console\Commands\MakeMigrationCommand;
use WpsMicro\Core\Console\Commands\MakeModelCommand;

final class GeneratorCommandsTest extends TestCase
{
    private string $tempPath;

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/wps-micro-generators-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempPath);
    }

    public function testGeneratorsUseConfiguredPathsAndNamespaces(): void
    {
        $controllers = $this->tempPath . '/app/Controllers';
        $models = $this->tempPath . '/app/Models';
        $migrations = $this->tempPath . '/database/migrations';

        $this->runCommand(new MakeControllerCommand($controllers, 'App\\Controllers'), ['Product']);
        $this->runCommand(new MakeModelCommand($models, 'App\\Models'), ['Product']);
        $this->runCommand(new MakeMigrationCommand($migrations), ['create_products_table']);

        $controller = (string) file_get_contents($controllers . '/ControllerProduct.php');
        $model = (string) file_get_contents($models . '/Product.php');
        $migrationFiles = glob($migrations . '/*_create_products_table.php') ?: [];

        self::assertStringContainsString('namespace App\\Controllers;', $controller);
        self::assertStringContainsString('use WpsMicro\\Core\\Controller;', $controller);
        self::assertStringContainsString('namespace App\\Models;', $model);
        self::assertStringContainsString('use WpsMicro\\Core\\Model;', $model);
        self::assertCount(1, $migrationFiles);
        self::assertStringContainsString(
            'use WpsMicro\\Core\\Migration;',
            (string) file_get_contents($migrationFiles[0])
        );
    }

    private function runCommand(object $command, array $arguments): void
    {
        ob_start();

        try {
            self::assertSame(0, $command->handle($arguments));
        } finally {
            ob_end_clean();
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
