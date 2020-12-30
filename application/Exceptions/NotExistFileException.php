<?php

declare(strict_types=1);

namespace Exceptions;

class NotExistFileException extends \Exception
{
    /**
     * NotExistFileException constructor.
     * @param string $file
     */
    public function __construct(string $file)
    {
        $this->message = 'File not exist ' . $file;
        error_log("\n" . date("Y-m-d H:i:s") . " : Script with problem: " . $this->getFile() . " Line with problem: " . $this->getLine() . " | file template '" . $file . "' not exist!",
            3,
            'errors.log');
    }
}