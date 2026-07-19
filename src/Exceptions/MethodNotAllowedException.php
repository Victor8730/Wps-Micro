<?php

declare(strict_types=1);

namespace WpsMicro\Core\Exceptions;

class MethodNotAllowedException extends \Exception
{
    /**
     * Methods accepted by the matched path.
     */
    private array $allowedMethods;

    /**
     * Create a 405 HTTP exception.
     */
    public function __construct(array $allowedMethods)
    {
        $this->allowedMethods = array_values(array_unique($allowedMethods));

        parent::__construct('Method not allowed', 405);
    }

    /**
     * Return methods accepted by the matched path.
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
