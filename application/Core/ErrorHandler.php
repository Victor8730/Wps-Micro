<?php

declare(strict_types=1);

namespace Core;

class ErrorHandler
{
    /**
     * Application configuration.
     */
    private Config $config;

    /**
     * Create the error handler.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Render an exception response.
     */
    public function render(\Throwable $exception): Response
    {
        if (!$this->isDebug()) {
            return new Response('Server error', 500);
        }

        return new Response($this->debugHtml($exception), 500, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Check whether debug output is enabled.
     */
    private function isDebug(): bool
    {
        return (bool) $this->config->get('app.debug', false);
    }

    /**
     * Build a small debug exception page.
     */
    private function debugHtml(\Throwable $exception): string
    {
        $title = get_class($exception);
        $message = $exception->getMessage();
        $location = $exception->getFile() . ':' . $exception->getLine();
        $trace = $exception->getTraceAsString();

        return '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>' . $this->escape($title) . '</title>
    <style>
        body { background: #f7f7f8; color: #1f2933; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; }
        main { max-width: 1040px; margin: 48px auto; padding: 0 24px; }
        h1 { font-size: 28px; margin: 0 0 12px; }
        .panel { background: #fff; border: 1px solid #dddfe5; border-radius: 8px; padding: 20px; }
        .muted { color: #626b7a; margin-bottom: 20px; }
        pre { background: #111827; color: #e5e7eb; overflow: auto; padding: 18px; border-radius: 8px; line-height: 1.5; }
    </style>
</head>
<body>
    <main>
        <div class="panel">
            <h1>' . $this->escape($title) . '</h1>
            <div class="muted">' . $this->escape($location) . '</div>
            <p>' . $this->escape($message) . '</p>
            <pre>' . $this->escape($trace) . '</pre>
        </div>
    </main>
</body>
</html>';
    }

    /**
     * Escape debug output.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
