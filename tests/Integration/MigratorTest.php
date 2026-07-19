<?php

declare(strict_types=1);

namespace WpsMicro\Tests\Integration;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use WpsMicro\Core\Config;
use WpsMicro\Core\Database;
use WpsMicro\Core\Migrator;

#[RequiresPhpExtension('pdo_sqlite')]
final class MigratorTest extends TestCase
{
    public function testItMigratesAndRollsBackWithSqlite(): void
    {
        $config = new Config([
            'database' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'migrations_path' => dirname(__DIR__) . '/Fixtures/migrations',
            ],
        ]);
        $db = (new Database($config))->connect();
        $migrator = new Migrator($db, $config);
        $migration = '2026_07_15_000001_create_integration_items_table';

        self::assertSame([$migration], $migrator->migrate());
        self::assertSame([], $migrator->migrate());
        self::assertSame('integration_items', $this->table($db, 'integration_items'));

        self::assertSame([$migration], $migrator->rollback());
        self::assertFalse($this->table($db, 'integration_items'));
    }

    private function table(\PDO $db, string $name): string|false
    {
        $statement = $db->prepare(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $name]);

        return $statement->fetchColumn();
    }
}
