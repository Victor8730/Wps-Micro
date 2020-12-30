<?php

declare(strict_types=1);

namespace Exceptions;

class FailedCopyException extends \Exception
{
    public function __construct($source)
    {
        $this->message = "Failed to copy from one location to another ->" . $source
            . "\nFile with problem: " . $this->getFile()
            . "\nLine with problem: " . $this->getLine();
        error_log("\n" . date("Y-m-d H:i:s") . " : Script with problem:" . $this->getFile() . ' / line:' . $this->getLine() . "/ Failed to copy " . $source ,
            3,
            "errors.log");
    }
}
