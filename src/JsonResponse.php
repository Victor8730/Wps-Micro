<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class JsonResponse extends Response
{
    /**
     * Create a JSON response.
     */
    public function __construct(array $data = [], int $statusCode = 200, array $headers = [])
    {
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json; charset=utf-8';

        parent::__construct(json_encode($data, JSON_THROW_ON_ERROR), $statusCode, $headers);
    }
}
