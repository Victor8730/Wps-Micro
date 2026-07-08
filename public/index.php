<?php

declare(strict_types=1);

session_start();

try {
    require_once dirname(__DIR__) . '/application/bootstrap.php';
} catch (\Throwable $e) {
    error_log(
        sprintf(
            "[%s] %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ),
        3,
        dirname(__DIR__) . '/errors.log'
    );

    http_response_code(500);
    echo 'Internal Server Error';
}
