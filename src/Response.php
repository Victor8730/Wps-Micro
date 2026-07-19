<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class Response
{
    /**
     * Response body.
     */
    private string $content;

    /**
     * HTTP status code.
     */
    private int $statusCode;

    /**
     * HTTP response headers.
     */
    private array $headers;

    /**
     * Create an HTTP response.
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->headers = [];
        $this->setStatusCode($statusCode);

        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, $value);
        }
    }

    /**
     * Return the response body.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set the response body.
     */
    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Return the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set the HTTP status code.
     */
    public function setStatusCode(int $statusCode): self
    {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new \InvalidArgumentException('HTTP status code must be between 100 and 599.');
        }

        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Return all response headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Check whether a response header exists.
     */
    public function hasHeader(string $name): bool
    {
        return $this->headerName($name) !== null;
    }

    /**
     * Return a response header value.
     */
    public function getHeader(string $name): array|string|null
    {
        $headerName = $this->headerName($name);

        return $headerName === null ? null : $this->headers[$headerName];
    }

    /**
     * Set or replace a response header.
     *
     */
    public function setHeader(string $name, array|string $value): self
    {
        $name = $this->validateHeaderName($name);
        $headerName = $this->headerName($name) ?? $name;
        $this->headers[$headerName] = $this->normalizeHeaderValue($value);

        return $this;
    }

    /**
     * Append a value to a response header.
     */
    public function addHeader(string $name, string $value): self
    {
        $name = $this->validateHeaderName($name);
        $value = $this->validateHeaderValue($value);
        $headerName = $this->headerName($name) ?? $name;
        $existing = $this->headers[$headerName] ?? [];
        $values = is_array($existing) ? $existing : [$existing];
        $values[] = $value;
        $this->headers[$headerName] = $values;

        return $this;
    }

    /**
     * Send headers and content to the client.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                $values = is_array($value) ? $value : [$value];

                foreach ($values as $index => $item) {
                    $replace = strcasecmp($name, 'Set-Cookie') !== 0 && $index === 0;
                    header($name . ': ' . $item, $replace);
                }
            }
        }

        echo $this->content;
    }

    /**
     * Find the registered case-preserving header name.
     */
    private function headerName(string $name): ?string
    {
        foreach (array_keys($this->headers) as $registeredName) {
            if (strcasecmp($registeredName, $name) === 0) {
                return $registeredName;
            }
        }

        return null;
    }

    /**
     * Validate and return an HTTP header name.
     */
    private function validateHeaderName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+\-.^_`|~]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid HTTP header name.');
        }

        return $name;
    }

    /**
     * Normalize one or more HTTP header values.
     *
     */
    private function normalizeHeaderValue(array|string $value): array|string
    {
        if (!is_array($value)) {
            return $this->validateHeaderValue($value);
        }

        if ($value === []) {
            throw new \InvalidArgumentException('HTTP header values cannot be empty.');
        }

        $values = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new \InvalidArgumentException('HTTP header values must be strings.');
            }

            $values[] = $this->validateHeaderValue($item);
        }

        return $values;
    }

    /**
     * Reject header values that could split the response.
     */
    private function validateHeaderValue(string $value): string
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException('HTTP header values cannot contain line breaks.');
        }

        return $value;
    }
}
