<?php

declare(strict_types=1);

namespace Middleware;

use Core\JsonResponse;
use Core\Middleware;
use Core\RedirectResponse;
use Core\Request;
use Core\Response;
use Core\Session;

class AuthMiddleware implements Middleware
{
    /**
     * Session storage used to resolve the current user.
     */
    private Session $session;

    /**
     * Create the authentication middleware.
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Allow authenticated requests to continue through the pipeline.
     */
    public function handle(Request $request, callable $next): Response
    {
        if ($this->session->has('user_id')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        return new RedirectResponse('/login');
    }
}
