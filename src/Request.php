<?php

declare(strict_types=1);

namespace WpsMicro\Core;

use WpsMicro\Core\Exceptions\BadRequestException;

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
     *
     * @var array<array-key, mixed>
     */
    private array $query;

    /**
     * Parsed request body parameters.
     *
     * @var array<array-key, mixed>
     */
    private array $request;

    /**
     * Server parameters.
     *
     * @var array<string, mixed>
     */
    private array $server;

    /**
     * Normalized HTTP headers.
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Uploaded files.
     *
     * @var array<string, mixed>
     */
    private array $files;

    /**
     * Request cookies.
     *
     * @var array<string, mixed>
     */
    private array $cookies;

    /**
     * Raw request body.
     */
    private string $content;

    /**
     * Parsed JSON payload.
     *
     * @var null|array<array-key, mixed>
     */
    private ?array $json;

    /**
     * Create a request value object.
     *
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $request
     * @param array<string, mixed>  $server
     * @param array<string, string> $headers
     * @param array<string, mixed>  $files
     * @param array<string, mixed>  $cookies
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $request = [],
        array $server = [],
        array $headers = [],
        array $files = [],
        array $cookies = [],
        string $content = '',
    ) {
        $this->headers = self::normalizeHeaders($headers);
        [$request, $json] = $this->parseBody($method, $request, $content);

        $this->method = $this->normalizeMethod($method, $request);
        $this->path = $this->normalizePath($path);
        $this->query = $query;
        $this->request = $request;
        $this->server = $server;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->content = $content;
        $this->json = $json;
    }

    /**
     * Build a request from PHP globals.
     */
    public static function fromGlobals(): self
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $content = file_get_contents('php://input');

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $path,
            $_GET,
            $_POST,
            $_SERVER,
            self::headersFromServer($_SERVER),
            $_FILES,
            $_COOKIE,
            $content === false ? '' : $content,
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
     *
     * @return array<array-key, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Return a query parameter or all query parameters.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Return all parsed request body parameters.
     *
     * @return array<array-key, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Return a body parameter or all body parameters.
     */
    public function body(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->request;
        }

        return $this->request[$key] ?? $default;
    }

    /**
     * Return all input data.
     *
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->request);
    }

    /**
     * Return an input value from request body first, then query string.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->request)) {
            return $this->request[$key];
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Return only selected input values.
     *
     * @param list<string> $keys
     *
     * @return array<array-key, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Check whether an input key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->request) || array_key_exists($key, $this->query);
    }

    /**
     * Check whether an input value is not empty.
     */
    public function filled(string $key): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        $value = $this->input($key);

        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * Return a JSON value or the complete JSON payload.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->json ?? [];
        }

        return $this->json[$key] ?? $default;
    }

    /**
     * Check whether the request content type is JSON.
     */
    public function isJson(): bool
    {
        $contentType = $this->contentType();

        return $contentType === 'application/json' || str_ends_with($contentType, '+json');
    }

    /**
     * Check whether the client expects a JSON response.
     */
    public function expectsJson(): bool
    {
        $accept = strtolower($this->getHeader('Accept', '') ?? '');

        return $this->isAjax() || str_contains($accept, '/json') || str_contains($accept, '+json');
    }

    /**
     * Return all uploaded files.
     *
     * @return array<string, mixed>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Return an uploaded file by field name.
     */
    public function file(string $key, mixed $default = null): mixed
    {
        return $this->files[$key] ?? $default;
    }

    /**
     * Return all request cookies.
     *
     * @return array<string, mixed>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Return a cookie value.
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Return the raw request body.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Return all server parameters.
     *
     * @return array<string, mixed>
     */
    public function getServer(): array
    {
        return $this->server;
    }

    /**
     * Return all HTTP headers.
     *
     * @return array<string, string>
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
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Check whether the request was made with XMLHttpRequest.
     */
    public function isAjax(): bool
    {
        return strcasecmp($this->getHeader('X-Requested-With', '') ?? '', 'XMLHttpRequest') === 0;
    }

    /**
     * Parse JSON and URL-encoded request bodies.
     *
     * @param array<array-key, mixed> $request
     *
     * @return array{0: array<array-key, mixed>, 1: null|array<array-key, mixed>}
     */
    private function parseBody(string $method, array $request, string $content): array
    {
        if ($this->isJson()) {
            if ($content === '') {
                return [$request, []];
            }

            try {
                $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new BadRequestException('The JSON request body is invalid.', $this->expectsJson());
            }

            if (!is_array($json)) {
                throw new BadRequestException(
                    'The JSON request body must contain an object or array.',
                    $this->expectsJson(),
                );
            }

            return [$request === [] ? $json : $request, $json];
        }

        $method = strtoupper($method);

        if (
            $request === []
            && $content !== ''
            && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $this->contentType() === 'application/x-www-form-urlencoded'
        ) {
            parse_str($content, $request);
        }

        return [$request, null];
    }

    /**
     * Return the normalized media type without parameters.
     */
    private function contentType(): string
    {
        $contentType = strtolower($this->getHeader('Content-Type', '') ?? '');

        return trim(explode(';', $contentType, 2)[0]);
    }

    /**
     * Convert server variables to normalized HTTP headers.
     *
     * @param array<string, mixed> $server
     *
     * @return array<string, string>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'] as $key) {
            if (isset($server[$key])) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string) $server[$key];
            }
        }

        if (!isset($headers['authorization']) && isset($server['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = (string) $server['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $headers;
    }

    /**
     * Normalize explicitly provided HTTP headers.
     *
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = (string) $value;
        }

        return $normalized;
    }

    /**
     * Normalize the request path.
     */
    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';

        return $path;
    }

    /**
     * Normalize the HTTP method and support form method overrides.
     *
     * @param array<array-key, mixed> $request
     */
    private function normalizeMethod(string $method, array $request): string
    {
        $method = strtoupper($method);

        if ($method === 'POST' && isset($request['_method'])) {
            $override = strtoupper((string) $request['_method']);
            $allowed = ['PUT', 'PATCH', 'DELETE'];

            if (in_array($override, $allowed, true)) {
                return $override;
            }
        }

        return $method;
    }
}
