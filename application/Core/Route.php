<?php

declare(strict_types=1);

namespace Core;

class Route extends Router
{
    /**
     * Create a backward-compatible router instance.
     */
    public function __construct(?Config $config = null)
    {
        parent::__construct($config ?? new Config([
            'router' => [
                'default_controller' => 'home',
                'default_action' => 'index',
            ],
        ]));
    }

    /**
     * Dispatch the current request through the new request/response pipeline.
     */
    public function initialize(): void
    {
        $kernel = Kernel::fromConfigFile(Base::PATH_ROOT . '/' . Base::PATH_APPLICATION . '/Config/app.php');
        $kernel->handle(Request::fromGlobals())->send();
    }

    /**
     * Render the 404 response and stop request processing.
     */
    public static function errorPage404(): void
    {
        (new Response('Page not found', 404))->send();

        exit;
    }
}
