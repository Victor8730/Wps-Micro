<?php

declare(strict_types=1);

namespace WpsMicro\Core\Exceptions;

class HttpNotFoundException extends \Exception
{
    /**
     * Create a 404 HTTP exception.
     */
    public function __construct(string $message = 'Page not found')
    {
        parent::__construct($message, 404);
    }
}
