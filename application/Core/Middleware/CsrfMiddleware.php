<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Csrf;
use Core\Middleware;
use Core\Request;
use Core\Response;
use Exceptions\CsrfTokenMismatchException;

class CsrfMiddleware implements Middleware
{
    /**
     * CSRF token manager.
     */
    private Csrf $csrf;

    /**
     * Create the middleware.
     */
    public function __construct(Csrf $csrf)
    {
        $this->csrf = $csrf;
    }

    /**
     * Validate CSRF tokens for unsafe methods.
     *
     * @throws CsrfTokenMismatchException
     */
    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        if (!$this->csrf->validate($request)) {
            throw new CsrfTokenMismatchException();
        }

        return $next($request);
    }
}
