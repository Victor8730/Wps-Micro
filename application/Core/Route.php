<?php

declare(strict_types=1);

namespace Core;

class Route extends Router
{
    /**
     * Dispatch the current request through the new request/response pipeline.
     */
    public function initialize(): void
    {
        $request = Request::fromGlobals();
        $response = (new Dispatcher($this))->dispatch($request);
        $response->send();
    }

    /**
     * Render the 404 response and stop request processing.
     */
    public static function errorPage404(): void
    {
        (new Response('Page not found', 404))->send();

        exit;
    }
}
