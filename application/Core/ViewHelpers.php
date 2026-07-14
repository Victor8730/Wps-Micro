<?php

declare(strict_types=1);

namespace Core;

class ViewHelpers
{
    /**
     * Application configuration.
     */
    private Config $config;

    /**
     * CSRF helper.
     */
    private Csrf $csrf;

    /**
     * Session storage.
     */
    private Session $session;

    /**
     * Cached validation errors for the current render.
     */
    private ?array $errors = null;

    /**
     * Cached old input for the current render.
     */
    private ?array $oldInput = null;

    /**
     * Create view helpers.
     */
    public function __construct(Config $config, Csrf $csrf, Session $session)
    {
        $this->config = $config;
        $this->csrf = $csrf;
        $this->session = $session;
    }

    /**
     * Register Twig helper functions.
     */
    public function register(\Twig\Environment $twig): void
    {
        $twig->addFunction(new \Twig\TwigFunction('asset', [$this, 'asset']));
        $twig->addFunction(new \Twig\TwigFunction('url', [$this, 'url']));
        $twig->addFunction(new \Twig\TwigFunction('csrf_token', [$this->csrf, 'token']));
        $twig->addFunction(new \Twig\TwigFunction('csrf_field', [$this->csrf, 'field'], ['is_safe' => ['html']]));
        $twig->addFunction(new \Twig\TwigFunction('old', [$this, 'old']));
        $twig->addFunction(new \Twig\TwigFunction('flash', [$this, 'flash']));
        $twig->addFunction(new \Twig\TwigFunction('errors', [$this, 'errors']));
        $twig->addFunction(new \Twig\TwigFunction('error', [$this, 'error']));
    }

    /**
     * Return a public asset URL.
     */
    public function asset(string $path): string
    {
        return rtrim($this->baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Return an application URL.
     */
    public function url(string $path = ''): string
    {
        return rtrim($this->baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Return old input from the previous request.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function old(string $key, $default = null)
    {
        $old = $this->oldInput();

        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }

    /**
     * Pull a flash message.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function flash(string $key, $default = null)
    {
        return $this->session->pullFlash($key, $default);
    }

    /**
     * Return validation errors.
     */
    public function errors(): array
    {
        if ($this->errors === null) {
            $errors = $this->session->pullFlash('errors', []);
            $this->errors = is_array($errors) ? $errors : [];
        }

        return $this->errors;
    }

    /**
     * Return the first validation error for a field.
     */
    public function error(string $key, ?string $default = null): ?string
    {
        $errors = $this->errors();

        return isset($errors[$key][0]) ? (string) $errors[$key][0] : $default;
    }

    /**
     * Return cached old input.
     */
    private function oldInput(): array
    {
        if ($this->oldInput === null) {
            $old = $this->session->pullFlash('old_input', []);
            $this->oldInput = is_array($old) ? $old : [];
        }

        return $this->oldInput;
    }

    /**
     * Return the configured base URL.
     */
    private function baseUrl(): string
    {
        return (string) $this->config->get('app.url', '');
    }
}
