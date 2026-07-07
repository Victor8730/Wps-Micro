<?php

declare(strict_types=1);

namespace Exceptions;

class NotExistFileException extends \Exception
{
    /**
     * Create an exception for a missing file.
     */
    public function __construct(string $file)
    {
        $message = 'File does not exist ' . $file;
        error_log("\n" . date("Y-m-d H:i:s") . " : Script with problem: " . $this->getFile() . " Line with problem: " . $this->getLine() . " | file template '" . $file . "' does not exist!",
            3,
            'errors.log');
        parent::__construct($message);
    }
}
