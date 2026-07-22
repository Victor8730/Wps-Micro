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
        self::assertSame('integration_items', $this->sqliteTable($db, 'integration_items'));

        self::assertSame([$migration], $migrator->rollback());
        self::assertFalse($this->sqliteTable($db, 'integration_items'));
    }

    public function testItMigratesAndRollsBackWithMariaDb(): void
    {
        $host = getenv('WPS_TEST_MARIADB_HOST');

        if (!is_string($host) || $host === '') {
            self::markTestSkipped('MariaDB integration environment is not configured.');
        }

        $config = new Config([
            'database' => [
                'driver' => 'mysql',
                'host' => $host,
                'port' => getenv('WPS_TEST_MARIADB_PORT') ?: '3306',
                'database' => getenv('WPS_TEST_MARIADB_DATABASE') ?: 'wps_micro_test',
                'username' => getenv('WPS_TEST_MARIADB_USERNAME') ?: 'wps_micro',
                'password' => getenv('WPS_TEST_MARIADB_PASSWORD') ?: 'secret',
                'charset' => 'utf8mb4',
                'migrations_path' => dirname(__DIR__) . '/Fixtures/migrations',
            ],
        ]);
        $db = (new Database($config))->connect();
        $migration = '2026_07_15_000001_create_integration_items_table';

        $this->resetMariaDb($db);

        try {
            $migrator = new Migrator($db, $config);

            self::assertSame([$migration], $migrator->migrate());
            self::assertSame([], $migrator->migrate());
            self::assertTrue($this->mariaDbTableExists($db, 'integration_items'));

            self::assertSame([$migration], $migrator->rollback());
            self::assertFalse($this->mariaDbTableExists($db, 'integration_items'));
        } finally {
            $this->resetMariaDb($db);
        }
    }

    private function sqliteTable(\PDO $db, string $name): string|false
    {
        $statement = $db->prepare(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name"
        );
        $statement->execute(['name' => $name]);

        return $statement->fetchColumn();
    }

    private function mariaDbTableExists(\PDO $db, string $name): bool
    {
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :name'
        );
        $statement->execute(['name' => $name]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function resetMariaDb(\PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS integration_items');
        $db->exec('DROP TABLE IF EXISTS migrations');
    }
}
