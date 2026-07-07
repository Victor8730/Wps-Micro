<?php

declare(strict_types=1);


namespace Exceptions;


class NotValidDataFromUrlException extends \Exception
{
    public function __construct()
    {
        $message = 'The data received from the URL is incorrect, check the data';
        error_log("\n" . date("Y-m-d H:i:s") . " : Script with problem: " . $this->getFile() . " | Line with problem: " . $this->getLine() . " | " . $message,
            3,
            'errors.log');
        parent::__construct($message);
    }
}
