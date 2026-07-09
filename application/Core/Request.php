<?php

declare(strict_types=1);

namespace Core;

class Request
{
    /**
     * HTTP request method.
     */
    private string $method;

    /**
     * Request path without query string.
     */
    private string $path;

    /**
     * Query string parameters.
     */
    private array $query;

    /**
     * Request body parameters.
     */
    private array $request;

    /**
     * Server parameters.
     */
    private array $server;

    /**
     * HTTP headers.
     */
    private array $headers;

    /**
     * Create a request value object.
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $request = [],
        array $server = [],
        array $headers = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path === '' ? '/' : $path;
        $this->query = $query;
        $this->request = $request;
        $this->server = $server;
        $this->headers = $headers;
    }

    /**
     * Build a request from PHP globals.
     */
    public static function fromGlobals(): self
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $path,
            $_GET,
            $_POST,
            $_SERVER,
            self::headersFromServer($_SERVER)
        );
    }

    /**
     * Return the HTTP method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Return the request path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return all query parameters.
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Return all request body parameters.
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Return all server parameters.
     */
    public function getServer(): array
    {
        return $this->server;
    }

    /**
     * Return all HTTP headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Return a header value by name.
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);

        return $this->headers[$key] ?? $default;
    }

    /**
     * Check whether the request was made with XMLHttpRequest.
     */
    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Convert server variables to normalized HTTP headers.
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') !== 0) {
                continue;
            }

            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = (string) $value;
        }

        return $headers;
    }
}
