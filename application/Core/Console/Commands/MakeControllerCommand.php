<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\Command;

class MakeControllerCommand extends GeneratorCommand implements Command
{
    /**
     * Return the console command name.
     */
    public function name(): string
    {
        return 'make:controller';
    }

    /**
     * Return the console command description.
     */
    public function description(): string
    {
        return 'Create a controller class';
    }

    /**
     * Execute the command.
     */
    public function handle(array $arguments): int
    {
        try {
            $class = 'Controller' . $this->studly($this->requiredName($arguments));
        } catch (\InvalidArgumentException $e) {
            echo $e->getMessage() . "\n";

            return 1;
        }

        $path = $this->rootPath . '/application/Controllers/' . $class . '.php';

        return $this->writeFile($path, $this->stub($class));
    }

    /**
     * Return the generated controller source.
     */
    private function stub(string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Response;

class {$class} extends Controller
{
    /**
     * Render the default page.
     */
    public function actionIndex(): Response
    {
        return \$this->render('home/home.twig');
    }
}
PHP;
    }
}
