<?php

declare(strict_types=1);

namespace WpsMicro\Core\Console;

class Application
{
    /**
     * Registered console commands.
     */
    private array $commands = [];

    /**
     * Register a console command.
     */
    public function add(Command $command): self
    {
        $this->commands[$command->name()] = $command;

        return $this;
    }

    /**
     * Run the requested console command.
     */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? null;

        if ($name === null || $name === 'list') {
            $this->listCommands();

            return 0;
        }

        if (!isset($this->commands[$name])) {
            echo 'Command not found: ' . $name . "\n\n";
            $this->listCommands();

            return 1;
        }

        return $this->commands[$name]->handle(array_slice($argv, 2));
    }

    /**
     * Print available commands.
     */
    private function listCommands(): void
    {
        echo "Available commands:\n";

        foreach ($this->commands as $command) {
            echo '  ' . str_pad($command->name(), 24) . $command->description() . "\n";
        }
    }
}
