<?php

declare(strict_types=1);

namespace WpsMicro\Core\Console\Commands;

use WpsMicro\Core\Console\Command;

class MakeControllerCommand extends GeneratorCommand implements Command
{
    /**
     * Namespace used by generated controllers.
     */
    private string $namespace;

    /**
     * Create the controller generator.
     */
    public function __construct(string $targetPath, string $namespace)
    {
        parent::__construct($targetPath);

        $this->namespace = trim($namespace, '\\');
    }

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

        $path = $this->targetPath . '/' . $class . '.php';

        return $this->writeFile($path, $this->stub($class));
    }

    /**
     * Return the generated controller source.
     */
    private function stub(string $class): string
    {
        $namespace = $this->namespace;

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use WpsMicro\Core\Controller;
use WpsMicro\Core\Response;

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
