<?php

declare(strict_types=1);

namespace WpsMicro\Core\Console\Commands;

use WpsMicro\Core\Console\Command;
use WpsMicro\Core\Container;
use WpsMicro\Core\Migrator;

class MigrateCommand implements Command
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
        return 'migrate';
    }

    /**
     * Return the console command description.
     */
    public function description(): string
    {
        return 'Apply pending database migrations';
    }

    /**
     * Execute the command.
     */
    public function handle(array $arguments): int
    {
        /** @var Migrator $migrator */
        $migrator = $this->container->get(Migrator::class);
        $applied = $migrator->migrate();

        if ($applied === []) {
            echo "Nothing to migrate.\n";

            return 0;
        }

        foreach ($applied as $migration) {
            echo 'Migrated: ' . $migration . "\n";
        }

        return 0;
    }
}
