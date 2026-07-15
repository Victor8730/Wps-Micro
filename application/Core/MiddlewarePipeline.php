<?php

declare(strict_types=1);

namespace Core;

class MiddlewarePipeline
{
    /**
     * Service container.
     */
    private Container $container;

    /**
     * Create a middleware pipeline.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Run the request through middleware and into the destination callback.
     */
    public function handle(Request $request, array $middleware, callable $destination): Response
    {
        $destination = function (Request $request) use ($destination): Response {
            $this->container->instance(Request::class, $request);

            return $destination($request);
        };

        $next = array_reduce(
            array_reverse($middleware),
            function (callable $next, $middleware): callable {
                return function (Request $request) use ($middleware, $next): Response {
                    $this->container->instance(Request::class, $request);

                    return $this->resolve($middleware)->handle($request, $next);
                };
            },
            $destination
        );

        return $next($request);
    }

    /**
     * Resolve a middleware class or instance.
     *
     * @param mixed $middleware
     */
    private function resolve($middleware): Middleware
    {
        if ($middleware instanceof Middleware) {
            return $middleware;
        }

        if (is_string($middleware)) {
            $middleware = $this->container->make($middleware);
        }

        if (!$middleware instanceof Middleware) {
            throw new \RuntimeException('Middleware must implement ' . Middleware::class);
        }

        return $middleware;
    }
}
