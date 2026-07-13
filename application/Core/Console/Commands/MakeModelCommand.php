<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeModelCommand extends GeneratorCommand implements Command
{
    /**
     * Return the console command name.
     */
    public function name(): string
    {
        return 'make:model';
    }

    /**
     * Return the console command description.
     */
    public function description(): string
    {
        return 'Create a model class';
    }

    /**
     * Execute the command.
     */
    public function handle(array $arguments): int
    {
        try {
            $class = $this->studly($this->requiredName($arguments));
        } catch (\InvalidArgumentException $e) {
            echo $e->getMessage() . "\n";

            return 1;
        }

        $path = $this->rootPath . '/application/Models/' . $class . '.php';

        return $this->writeFile($path, $this->stub($class));
    }

    /**
     * Return the generated model source.
     */
    private function stub(string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Models;

use Core\Model;

class {$class} extends Model
{
}
PHP;
    }
}
