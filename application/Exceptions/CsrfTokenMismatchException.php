<?php

declare(strict_types=1);

namespace Exceptions;

class CsrfTokenMismatchException extends \Exception
{
    /**
     * Create the exception.
     */
    public function __construct()
    {
        parent::__construct('CSRF token mismatch.');
    }
}
