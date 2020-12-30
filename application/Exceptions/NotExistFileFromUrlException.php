<?php

declare(strict_types=1);

namespace Exceptions;

class NotExistFileFromUrlException extends \Exception
{
    /**
     * NotExistFileFromUrlException constructor.
     * @param string $file
     */
    public function __construct(string $file)
    {
        $this->message = 'File not exist ' . $file;
    }
}