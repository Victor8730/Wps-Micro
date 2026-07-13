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
        $old = $this->session->get('_old_input', []);

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
     * Return the configured base URL.
     */
    private function baseUrl(): string
    {
        return (string) $this->config->get('app.url', '');
    }
}
