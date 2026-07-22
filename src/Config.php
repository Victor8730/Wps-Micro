<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class Config
{
    /**
     * Application configuration values.
     */
    private array $items;

    /**
     * Create an immutable configuration repository.
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Return a configuration value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
