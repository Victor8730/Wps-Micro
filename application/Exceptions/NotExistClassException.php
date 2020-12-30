<?php

declare(strict_types=1);

namespace Exceptions;

class NotExistClassException extends \Error
{
    public function __construct($class)
    {
        $this->message = "Class not exist ->" . $class
            . "\nFile with problem: " . $this->getFile()
            . "\nLine with problem: " . $this->getLine();
        error_log("\n" . date("Y-m-d H:i:s") . " : Script with problem:" . $this->getFile() . ' / line:' . $this->getLine() . "/ Class not exist in file " . $class . "!",
            3,
            "errors.log");
    }
}