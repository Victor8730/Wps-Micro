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
        $this->registerRoutes();
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
        try {
            /** @var Dispatcher $dispatcher */
            $dispatcher = $this->container->get(Dispatcher::class);

            return $dispatcher->dispatch($request);
        } catch (\Throwable $exception) {
            /** @var ErrorHandler $handler */
            $handler = $this->container->get(ErrorHandler::class);

            return $handler->render($exception, $request);
        }
    }

    /**
     * Build and handle a request from PHP globals.
     */
    public function handleGlobals(): Response
    {
        try {
            return $this->handle(Request::fromGlobals());
        } catch (\Throwable $exception) {
            /** @var ErrorHandler $handler */
            $handler = $this->container->get(ErrorHandler::class);

            return $handler->render($exception);
        }
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

            $twig = new \Twig\Environment($loader, [
                'cache' => $cachePath,
                'auto_reload' => (bool) $config->get('twig.auto_reload', true),
                'autoescape' => $config->get('twig.autoescape', 'html'),
            ]);

            /** @var ViewHelpers $helpers */
            $helpers = $container->get(ViewHelpers::class);
            $helpers->register($twig);

            return $twig;
        });

        $this->container->set(Router::class, static function (): Router {
            return new Router();
        });

        $this->container->set(MiddlewarePipeline::class, static function (Container $container): MiddlewarePipeline {
            return new MiddlewarePipeline($container);
        });

        $this->container->set(ErrorHandler::class, static function (Container $container): ErrorHandler {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new ErrorHandler($config);
        });

        $this->container->set(Session::class, static function (Container $container): Session {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new Session((array) $config->get('session', []));
        });

        $this->container->set(Csrf::class, static function (Container $container): Csrf {
            /** @var Session $session */
            $session = $container->get(Session::class);

            return new Csrf($session);
        });

        $this->container->set(Vite::class, static function (Container $container): Vite {
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new Vite($config);
        });

        $this->container->set(ViewHelpers::class, static function (Container $container): ViewHelpers {
            /** @var Config $config */
            $config = $container->get(Config::class);
            /** @var Csrf $csrf */
            $csrf = $container->get(Csrf::class);
            /** @var Session $session */
            $session = $container->get(Session::class);
            /** @var Vite $vite */
            $vite = $container->get(Vite::class);

            return new ViewHelpers($config, $csrf, $session, $vite);
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

        $this->container->set(Migrator::class, static function (Container $container): Migrator {
            /** @var \PDO $db */
            $db = $container->get(\PDO::class);
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new Migrator($db, $config);
        });

        $this->container->set(Dispatcher::class, static function (Container $container): Dispatcher {
            /** @var Router $router */
            $router = $container->get(Router::class);
            /** @var MiddlewarePipeline $pipeline */
            $pipeline = $container->get(MiddlewarePipeline::class);
            /** @var ErrorHandler $errorHandler */
            $errorHandler = $container->get(ErrorHandler::class);
            /** @var Config $config */
            $config = $container->get(Config::class);

            return new Dispatcher(
                $router,
                $container,
                $pipeline,
                $errorHandler,
                (array) $config->get('middleware.global', []),
                (array) $config->get('middleware.route', []),
                (array) $config->get('errors.not_found', [])
            );
        });
    }

    /**
     * Register application routes.
     */
    private function registerRoutes(): void
    {
        $routesPath = (string) $this->container->get(Config::class)->get('router.routes_path', '');

        if ($routesPath === '' || !is_file($routesPath)) {
            return;
        }

        /** @var Router $router */
        $router = $this->container->get(Router::class);
        $routes = require $routesPath;

        if (is_callable($routes)) {
            $routes($router);
        }
    }
}
