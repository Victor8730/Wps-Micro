<?php

declare(strict_types=1);

namespace Core;

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
        $this->statusCode = $statusCode;
        $this->headers = $headers;
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
     * Set a response header.
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

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
                header($name . ': ' . $value);
            }
        }

        echo $this->content;
    }
}
