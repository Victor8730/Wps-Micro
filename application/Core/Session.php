<?php

declare(strict_types=1);

namespace Core;

class Session
{
    /**
     * Start the PHP session when needed.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();
    }

    /**
     * Return a session value.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Store a session value.
     *
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        $this->start();

        $_SESSION[$key] = $value;
    }

    /**
     * Remove a session value.
     */
    public function forget(string $key): void
    {
        $this->start();

        unset($_SESSION[$key]);
    }

    /**
     * Store a flash value for the next request.
     *
     * @param mixed $value
     */
    public function flash(string $key, $value): void
    {
        $this->set('_flash.' . $key, $value);
    }

    /**
     * Pull a flash value and remove it from the session.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function pullFlash(string $key, $default = null)
    {
        $key = '_flash.' . $key;
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    /**
     * Return all session values.
     */
    public function all(): array
    {
        $this->start();

        return $_SESSION;
    }
}
