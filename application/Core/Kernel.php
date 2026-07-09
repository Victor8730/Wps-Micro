<?php

declare(strict_types=1);

namespace Core;

class Kernel
{
    /**
     * Service container for application infrastructure.
     */
    private Container $container;

    /**
     * Create the application kernel.
     */
    public function __construct(Config $config, ?Container $container = null)
    {
        $this->container = $container ?? new Container();
        $this->container->instance(Config::class, $config);
        $this->registerServices();
    }

    /**
     * Create a kernel from a PHP configuration file.
     */
    public static function fromConfigFile(string $path): self
    {
        $config = require $path;

        if (!is_array($config)) {
            throw new \RuntimeException('Application configuration must return an array.');
        }

        return new self(new Config($config));
    }

    /**
     * Handle an HTTP request.
     */
    public function handle(Request $request): Response
    {
        /** @var Dispatcher $dispatcher */
        $dispatcher = $this->container->get(Dispatcher::class);

        return $dispatcher->dispatch($request);
    }

    /**
     * Return the configured service container.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Register framework infrastructure services.
     */
    private function registerServices(): void
    {
        $this->container->set(\Twig\Environment::class, static function (Container $container): \Twig\Environment {
            /** @var Config $config */
            $config = $container->get(Config::class);
            $cachePath = (string) $config->get('twig.cache_path');

            if (!is_dir($cachePath) && !mkdir($cachePath, 0775, true) && !is_dir($cachePath)) {
                throw new \RuntimeException('Unable to create Twig cache directory: ' . $cachePath);
            }

            $loader = new \Twig\Loader\FilesystemLoader((string) $config->get('twig.views_path'));

            return new \Twig\Environment($loader, [
                'cache' => $cachePath,
                'auto_reload' => (bool) $config->get('twig.auto_reload', true),
                'autoescape' => $config->get('twig.autoescape', 'html'),
            ]);
        });

        $this->container->set(Router::class, static function (Container $container): Router {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new Router($config);
        });

        $this->container->set(Database::class, static function (Container $container): Database {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new Database($config);
        });

        $this->container->set(\PDO::class, static function (Container $container): \PDO {
            /** @var Database $database */
            $database = $container->get(Database::class);

            return $database->connect();
        });

        $this->container->set(Dispatcher::class, static function (Container $container): Dispatcher {
            /** @var Router $router */
            $router = $container->get(Router::class);

            return new Dispatcher($router, $container);
        });
    }
}
