<?php

declare(strict_types=1);

use Core\Env;

$rootPath = dirname(__DIR__, 2);
$path = static function (string $key, string $default) use ($rootPath): string {
    $value = (string) Env::get($key, $default);

    if (strpos($value, '/') === 0) {
        return $value;
    }

    return $rootPath . '/' . ltrim($value, '/');
};

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'WPS Micro'),
        'env' => Env::get('APP_ENV', 'local'),
        'debug' => Env::bool('APP_DEBUG', true),
        'url' => Env::get('APP_URL', 'http://localhost:8000'),
    ],
    'router' => [
        'default_controller' => Env::get('ROUTER_DEFAULT_CONTROLLER', 'home'),
        'default_action' => Env::get('ROUTER_DEFAULT_ACTION', 'index'),
        'routes_path' => $path('ROUTES_PATH', 'application/Routes/web.php'),
    ],
    'middleware' => [
        'global' => [],
        'route' => [
            \Core\Middleware\CsrfMiddleware::class,
        ],
    ],
    'twig' => [
        'views_path' => $path('TWIG_VIEWS_PATH', 'application/Views'),
        'cache_path' => $path('TWIG_CACHE_PATH', 'application/Cache'),
        'auto_reload' => Env::bool('TWIG_AUTO_RELOAD', Env::bool('APP_DEBUG', true)),
        'autoescape' => Env::get('TWIG_AUTOESCAPE', 'html'),
    ],
    'database' => [
        'driver' => Env::get('DB_DRIVER', 'mysql'),
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'wps_micro'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
        'migrations_path' => $path('DB_MIGRATIONS_PATH', 'application/Database/migrations'),
    ],
];
