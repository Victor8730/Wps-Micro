<?php

declare(strict_types=1);

namespace WpsMicro\Core\Exceptions;

class BadRequestException extends \Exception
{
    /**
     * Whether the client expects a JSON response.
     */
    private bool $expectsJson;

    /**
     * Create a 400 HTTP exception.
     */
    public function __construct(string $message = 'Bad request', bool $expectsJson = false)
    {
        parent::__construct($message, 400);

        $this->expectsJson = $expectsJson;
    }

    /**
     * Check whether the error should be rendered as JSON.
     */
    public function expectsJson(): bool
    {
        return $this->expectsJson;
    }
}
