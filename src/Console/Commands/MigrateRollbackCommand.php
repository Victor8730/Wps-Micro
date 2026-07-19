<?php

declare(strict_types=1);

namespace WpsMicro\Core\Console\Commands;

use WpsMicro\Core\Console\Command;
use WpsMicro\Core\Container;
use WpsMicro\Core\Migrator;

class MigrateRollbackCommand implements Command
{
    /**
     * Service container.
     */
    private Container $container;

    /**
     * Create the command.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Return the console command name.
     */
    public function name(): string
    {
        return 'migrate:rollback';
    }

    /**
     * Return the console command description.
     */
    public function description(): string
    {
        return 'Roll back applied database migrations';
    }

    /**
     * Execute the command.
     */
    public function handle(array $arguments): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->container->get(Migrator::class);
        $rolledBack = $migrator->rollback($this->steps($arguments));

        if ($rolledBack === []) {
            echo "Nothing to roll back.\n";

            return 0;
        }

        foreach ($rolledBack as $migration) {
            echo 'Rolled back: ' . $migration . "\n";
        }

        return 0;
    }

    /**
     * Resolve the migration step count from CLI arguments.
     */
    private function steps(array $arguments): int
    {
        foreach ($arguments as $argument) {
            if (strpos($argument, '--steps=') !== 0) {
                continue;
            }

            return max(1, (int) substr($argument, 8));
        }

        return 1;
    }
}
