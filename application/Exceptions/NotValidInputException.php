<?php

declare(strict_types=1);

namespace Exceptions;

class NotValidInputException extends \Exception
{
    public function __construct($value)
    {
        $message = "POST or GET data is not valid -> " . $this->formatValue($value) . "\n";

        parent::__construct($message);
    }

    /**
     * Convert invalid input to a readable value.
     */
    private function formatValue($value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return gettype($value);
    }
}
