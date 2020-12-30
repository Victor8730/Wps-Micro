<?php

declare(strict_types=1);

namespace Exceptions;

class NotValidInputException extends \Exception
{
    public function __construct(?string $string)
    {
        $this->message = "Post or Get data not valid ->".$string."\n";
    }
}