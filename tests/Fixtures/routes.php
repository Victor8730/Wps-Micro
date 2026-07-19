<?php

declare(strict_types=1);

use WpsMicro\Core\Router;
use WpsMicro\Tests\Fixtures\KernelController;

return static function (Router $router): void {
    $router->get('/hello/{name}', [KernelController::class, 'show']);
    $router->get('/json', [KernelController::class, 'status']);
};
