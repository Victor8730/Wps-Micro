<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class Session
{
    private const FLASH_KEY = '_flash';

    /**
     * Session and cookie options.
     */
    private array $options;

    /**
     * Indicates whether flash data was aged for this request.
     */
    private bool $flashAged = false;

    /**
     * Create a lazy PHP session store.
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'name' => 'WPSMICROSESSID',
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'http_only' => true,
            'same_site' => 'Lax',
        ], $options);
    }

    /**
     * Start the PHP session when needed.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->ageFlashData();

            return;
        }

        if (session_status() === PHP_SESSION_DISABLED) {
            throw new \RuntimeException('PHP sessions are disabled.');
        }

        if (PHP_SAPI !== 'cli' && headers_sent($file, $line)) {
            throw new \RuntimeException(
                sprintf('Unable to start the session after output was sent in %s:%d.', $file, $line)
            );
        }

        $this->configure();

        if (!session_start()) {
            throw new \RuntimeException('Unable to start the PHP session.');
        }

        $this->ageFlashData();
    }

    /**
     * Check whether the PHP session is active.
     */
    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Return the current session identifier.
     */
    public function id(): string
    {
        $this->start();

        return session_id();
    }

    /**
     * Check whether a session key exists.
     */
    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    /**
     * Return a session value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Store a session value.
     */
    public function set(string $key, mixed $value): void
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
     */
    public function flash(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[self::FLASH_KEY]['new'][$key] = $value;
    }

    /**
     * Return a flash value without removing it.
     */
    public function peekFlash(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[self::FLASH_KEY]['old'][$key] ?? $default;
    }

    /**
     * Pull a flash value and remove it from the session.
     */
    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->peekFlash($key, $default);
        unset($_SESSION[self::FLASH_KEY]['old'][$key]);
        $this->cleanupFlashData();

        return $value;
    }

    /**
     * Return all public session values.
     */
    public function all(): array
    {
        $this->start();
        $values = $_SESSION;
        unset($values[self::FLASH_KEY]);

        return $values;
    }

    /**
     * Regenerate the session identifier.
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->start();

        if (!session_regenerate_id($deleteOldSession)) {
            throw new \RuntimeException('Unable to regenerate the session identifier.');
        }
    }

    /**
     * Clear all data and regenerate the session identifier.
     */
    public function invalidate(): void
    {
        $this->start();
        $_SESSION = [];
        $this->flashAged = true;
        $this->regenerate();
    }

    /**
     * Destroy the session and remove its cookie.
     */
    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (filter_var(ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN)) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }

        if (!session_destroy()) {
            throw new \RuntimeException('Unable to destroy the PHP session.');
        }

        $this->flashAged = false;
    }

    /**
     * Persist session data and release its lock.
     */
    public function close(): void
    {
        if (!$this->isStarted()) {
            return;
        }

        if (!session_write_close()) {
            throw new \RuntimeException('Unable to close the PHP session.');
        }
    }

    /**
     * Configure the PHP session before it starts.
     */
    private function configure(): void
    {
        ini_set('session.use_strict_mode', '1');
        $lifetime = (int) $this->options['lifetime'];

        if ($lifetime > 0) {
            ini_set('session.gc_maxlifetime', (string) $lifetime);
        }

        session_name((string) $this->options['name']);

        if (!filter_var(ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => (string) $this->options['path'],
            'domain' => (string) $this->options['domain'],
            'secure' => (bool) $this->options['secure'],
            'httponly' => (bool) $this->options['http_only'],
            'samesite' => $this->sameSite(),
        ]);
    }

    /**
     * Expire old flash data and promote new values.
     */
    private function ageFlashData(): void
    {
        if ($this->flashAged) {
            return;
        }

        $new = $_SESSION[self::FLASH_KEY]['new'] ?? [];
        $_SESSION[self::FLASH_KEY] = [
            'old' => is_array($new) ? $new : [],
            'new' => [],
        ];
        $this->flashAged = true;
        $this->cleanupFlashData();
    }

    /**
     * Remove empty internal flash storage.
     */
    private function cleanupFlashData(): void
    {
        $old = $_SESSION[self::FLASH_KEY]['old'] ?? [];
        $new = $_SESSION[self::FLASH_KEY]['new'] ?? [];

        if ($old === [] && $new === []) {
            unset($_SESSION[self::FLASH_KEY]);
        }
    }

    /**
     * Return a valid SameSite cookie policy.
     */
    private function sameSite(): string
    {
        $sameSite = ucfirst(strtolower((string) $this->options['same_site']));

        return in_array($sameSite, ['Lax', 'Strict', 'None'], true) ? $sameSite : 'Lax';
    }
}
