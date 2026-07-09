<?php

declare(strict_types=1);

namespace Core;

require_once __DIR__ . '/vendor/autoload.php';

$request = Request::fromGlobals();
$response = (new Dispatcher(new Router()))->dispatch($request);
$response->send();
