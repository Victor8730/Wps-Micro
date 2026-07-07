<?php

declare(strict_types=1);

namespace Exceptions;

class NotExistMethodException extends \Exception
{
    /**
     * Create an exception for a missing controller method.
     */
    public function __construct(?object $controller, ?string $method)
    {
        $message = 'Method ' . $method . ' does not exist in controller ' . $controller;
        error_log("\n" . date("Y-m-d H:i:s") . " : Script with problem: " . $this->getFile() . " | Line with problem: " . $this->getLine() . " | " . $message,
            3,
            'errors.log');
        parent::__construct($message);
    }
}
