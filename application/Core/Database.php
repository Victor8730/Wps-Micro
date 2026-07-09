<?php

declare(strict_types=1);

namespace Core;

class Database
{
    /**
     * Application configuration.
     */
    private Config $config;

    /**
     * Create a database connection factory.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Create the configured PDO connection.
     */
    public function connect(): \PDO
    {
        $dsn = $this->buildDsn();

        return new \PDO($dsn, $this->username(), $this->password(), [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Build a PDO DSN from database configuration.
     */
    private function buildDsn(): string
    {
        $driver = (string) $this->config->get('database.driver', 'mysql');

        if ($driver === 'sqlite') {
            return 'sqlite:' . $this->config->get('database.database');
        }

        return sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $driver,
            $this->config->get('database.host', '127.0.0.1'),
            $this->config->get('database.port', '3306'),
            $this->config->get('database.database'),
            $this->config->get('database.charset', 'utf8mb4')
        );
    }

    /**
     * Return the configured database username.
     */
    private function username(): string
    {
        return (string) $this->config->get('database.username', '');
    }

    /**
     * Return the configured database password.
     */
    private function password(): string
    {
        return (string) $this->config->get('database.password', '');
    }
}
