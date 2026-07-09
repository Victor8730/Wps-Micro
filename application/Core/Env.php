<?php

declare(strict_types=1);

namespace Core;

class Env
{
    /**
     * Load environment variables from a dotenv file.
     */
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            self::loadLine($line);
        }
    }

    /**
     * Return an environment variable value.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return $value;
    }

    /**
     * Return an environment variable as a boolean.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Parse and register one dotenv line.
     */
    private static function loadLine(string $line): void
    {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            return;
        }

        if (strpos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }

        $separatorPosition = strpos($line, '=');

        if ($separatorPosition === false) {
            return;
        }

        $key = trim(substr($line, 0, $separatorPosition));
        $value = trim(substr($line, $separatorPosition + 1));

        if ($key === '' || self::has($key)) {
            return;
        }

        $value = self::normalizeValue($value);

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    /**
     * Check whether the variable is already available.
     */
    private static function has(string $key): bool
    {
        return isset($_ENV[$key]) || isset($_SERVER[$key]) || getenv($key) !== false;
    }

    /**
     * Remove optional quotes from a dotenv value.
     */
    private static function normalizeValue(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        $last = substr($value, -1);

        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
