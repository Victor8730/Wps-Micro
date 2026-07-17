<?php

declare(strict_types=1);

try {
    /** @var \Core\Kernel $kernel */
    $kernel = require dirname(__DIR__) . '/application/bootstrap.php';
    $kernel->handleGlobals()->send();
} catch (\Throwable $exception) {
    error_log(sprintf(
        '%s in %s:%d',
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));

    http_response_code(500);
    echo 'Internal Server Error';
}
