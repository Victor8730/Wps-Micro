<?php

declare(strict_types=1);

use Core\Router;
use Tests\Fixtures\KernelController;

return static function (Router $router): void {
    $router->get('/hello/{name}', [KernelController::class, 'show']);
    $router->get('/json', [KernelController::class, 'status']);
};
