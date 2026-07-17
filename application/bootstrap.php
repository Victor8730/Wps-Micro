<?php

declare(strict_types=1);

namespace Core;

require_once __DIR__ . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

return Kernel::fromConfigFile(__DIR__ . '/Config/app.php');
