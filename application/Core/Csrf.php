<?php

declare(strict_types=1);

namespace Core;

class Csrf
{
    /**
     * Session storage.
     */
    private Session $session;

    /**
     * Create a CSRF helper.
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Return the current CSRF token.
     */
    public function token(): string
    {
        $token = $this->session->get('_csrf_token');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->set('_csrf_token', $token);

        return $token;
    }

    /**
     * Return a hidden CSRF input field.
     */
    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Check whether the request token is valid.
     */
    public function validate(Request $request): bool
    {
        $token = $request->input('_token');

        if (!is_string($token)) {
            $token = $request->getHeader('X-CSRF-Token');
        }

        return is_string($token) && hash_equals($this->token(), $token);
    }
}
