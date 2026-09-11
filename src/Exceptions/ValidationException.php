<?php

declare(strict_types=1);

namespace WpsMicro\Core\Exceptions;

class ValidationException extends \Exception
{
    /**
     * Validation errors grouped by field name.
     *
     * @var array<string, list<string>>
     */
    private array $errors;

    /**
     * Create the exception.
     *
     * @param array<string, list<string>> $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct('The given data was invalid.');
        $this->errors = $errors;
    }

    /**
     * Return validation errors.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
