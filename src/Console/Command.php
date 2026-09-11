<?php

declare(strict_types=1);

namespace WpsMicro\Core\Console;

interface Command
{
    /**
     * Return the console command name.
     */
    public function name(): string;

    /**
     * Return the console command description.
     */
    public function description(): string;

    /**
     * Execute the command.
     *
     * @param list<string> $arguments
     */
    public function handle(array $arguments): int;
}
