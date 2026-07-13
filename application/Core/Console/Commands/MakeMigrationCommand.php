<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeMigrationCommand extends GeneratorCommand implements Command
{
    /**
     * Return the console command name.
     */
    public function name(): string
    {
        return 'make:migration';
    }

    /**
     * Return the console command description.
     */
    public function description(): string
    {
        return 'Create a migration file';
    }

    /**
     * Execute the command.
     */
    public function handle(array $arguments): int
    {
        try {
            $name = $this->snake($this->requiredName($arguments));
        } catch (\InvalidArgumentException $e) {
            echo $e->getMessage() . "\n";

            return 1;
        }

        $file = date('Y_m_d_His') . '_' . $name . '.php';
        $path = $this->rootPath . '/application/Database/migrations/' . $file;

        return $this->writeFile($path, $this->stub());
    }

    /**
     * Convert a migration name to snake case.
     */
    private function snake(string $name): string
    {
        $name = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name) ?: $name;
        $name = strtolower(str_replace(['-', ' '], '_', $name));

        return preg_replace('/[^a-z0-9_]/', '', $name) ?: 'new_migration';
    }

    /**
     * Return the generated migration source.
     */
    private function stub(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Core\Migration;

return new class extends Migration {
    /**
     * Apply the migration.
     */
    public function up(PDO $db): void
    {
    }

    /**
     * Roll back the migration.
     */
    public function down(PDO $db): void
    {
    }
};
PHP;
    }
}
