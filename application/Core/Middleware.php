<?php

declare(strict_types=1);

namespace Core;

interface Middleware
{
    /**
     * Handle an HTTP request before or after the next layer.
     */
    public function handle(Request $request, callable $next): Response;
}
