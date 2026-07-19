<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class ViewRenderer
{
    /**
     * Lazy Twig environment resolver.
     */
    private \Closure $environmentResolver;

    /**
     * Resolved Twig environment.
     */
    private ?\Twig\Environment $environment = null;

    /**
     * Create a lazy view renderer.
     */
    public function __construct(callable $environmentResolver)
    {
        $this->environmentResolver = \Closure::fromCallable($environmentResolver);
    }

    /**
     * Render a Twig template with context data.
     */
    public function render(string $template, array $context = []): string
    {
        return $this->environment()->render($template, $context);
    }

    /**
     * Resolve and cache the Twig environment.
     */
    private function environment(): \Twig\Environment
    {
        if ($this->environment !== null) {
            return $this->environment;
        }

        $environment = ($this->environmentResolver)();

        if (!$environment instanceof \Twig\Environment) {
            throw new \RuntimeException('View renderer resolver must return a Twig environment.');
        }

        return $this->environment = $environment;
    }
}
