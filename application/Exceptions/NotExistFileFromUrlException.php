<?php

declare(strict_types=1);

namespace Exceptions;

class NotExistFileFromUrlException extends \Exception
{
    /**
     * Create an exception for a missing remote file.
     */
    public function __construct(string $file)
    {
        parent::__construct('File does not exist ' . $file);
    }
}
