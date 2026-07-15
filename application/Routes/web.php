<?php

declare(strict_types=1);

use Controllers\ControllerHome;
use Core\Router;

return static function (Router $router): void {
    $router->get('/', [ControllerHome::class, 'actionIndex']);
    $router->post('/', [ControllerHome::class, 'actionIndex']);
};
