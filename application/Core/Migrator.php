<?php

declare(strict_types=1);

namespace Core;

class Migrator
{
    /**
     * Database connection.
     */
    private \PDO $db;

    /**
     * Migration directory path.
     */
    private string $path;

    /**
     * Create a database migrator.
     */
    public function __construct(\PDO $db, Config $config)
    {
        $this->db = $db;
        $this->path = (string) $config->get('database.migrations_path');
    }

    /**
     * Apply pending migrations and return their names.
     */
    public function migrate(): array
    {
        $this->ensureMigrationTable();

        $ran = $this->ranMigrations();
        $applied = [];

        foreach ($this->migrationFiles() as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = require $file;

            if (!$migration instanceof Migration) {
                throw new \RuntimeException('Migration must return a Migration instance: ' . $file);
            }

            $migration->up($this->db);
            $this->recordMigration($name);
            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * Roll back applied migrations and return their names.
     */
    public function rollback(int $steps = 1): array
    {
        $this->ensureMigrationTable();
        $rolledBack = [];
        $steps = max(1, $steps);

        foreach ($this->lastMigrations($steps) as $name) {
            $file = $this->migrationFile($name);

            if ($file === null) {
                throw new \RuntimeException('Migration file not found: ' . $name);
            }

            $migration = require $file;

            if (!$migration instanceof Migration) {
                throw new \RuntimeException('Migration must return a Migration instance: ' . $file);
            }

            $migration->down($this->db);
            $this->removeMigration($name);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Ensure the migration tracking table exists.
     */
    private function ensureMigrationTable(): void
    {
        $driver = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->db->exec(
                'CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )'
            );

            return;
        }

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    /**
     * Return already applied migration names.
     */
    private function ranMigrations(): array
    {
        $statement = $this->db->query('SELECT migration FROM migrations ORDER BY id ASC');

        return $statement === false ? [] : $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Return last applied migration names.
     */
    private function lastMigrations(int $steps): array
    {
        $statement = $this->db->prepare('SELECT migration FROM migrations ORDER BY id DESC LIMIT :steps');
        $statement->bindValue(':steps', $steps, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Return migration files sorted by name.
     */
    private function migrationFiles(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $files = glob(rtrim($this->path, '/') . '/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Return a migration file path by migration name.
     */
    private function migrationFile(string $name): ?string
    {
        $file = rtrim($this->path, '/') . '/' . $name . '.php';

        return is_file($file) ? $file : null;
    }

    /**
     * Record an applied migration.
     */
    private function recordMigration(string $name): void
    {
        $statement = $this->db->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
        $statement->execute(['migration' => $name]);
    }

    /**
     * Remove an applied migration record.
     */
    private function removeMigration(string $name): void
    {
        $statement = $this->db->prepare('DELETE FROM migrations WHERE migration = :migration');
        $statement->execute(['migration' => $name]);
    }
}
