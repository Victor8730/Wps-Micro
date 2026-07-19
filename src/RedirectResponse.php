<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class RedirectResponse extends Response
{
    /**
     * Create a redirect response.
     */
    public function __construct(string $url, int $statusCode = 302, array $headers = [])
    {
        parent::__construct('', $statusCode, array_merge($headers, [
            'Location' => $url,
        ]));
    }
}
