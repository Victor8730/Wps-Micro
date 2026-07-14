<?php

declare(strict_types=1);

namespace Exceptions;

class ValidationException extends \Exception
{
    /**
     * Validation errors grouped by field name.
     */
    private array $errors;

    /**
     * Create the exception.
     */
    public function __construct(array $errors)
    {
        parent::__construct('The given data was invalid.');
        $this->errors = $errors;
    }

    /**
     * Return validation errors.
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
