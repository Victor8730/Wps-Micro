<?php

declare(strict_types=1);

namespace WpsMicro\Core\Console\Commands;

use WpsMicro\Core\Console\Command;
use WpsMicro\Core\Middleware;
use WpsMicro\Core\Router;

class RouteListCommand implements Command
{
    /**
     * Router containing the application routes.
     */
    private Router $router;

    /**
     * Create the route list command.
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Return the console command name.
     */
    public function name(): string
    {
        return 'route:list';
    }

    /**
     * Return the console command description.
     */
    public function description(): string
    {
        return 'List registered application routes';
    }

    /**
     * Print registered routes as a compact table.
     *
     * @param list<string> $arguments
     */
    public function handle(array $arguments): int
    {
        $rows = array_map(
            fn (array $route): array => [
                $route['method'],
                $route['path'],
                $route['name'] ?? '-',
                $this->handlerName($route['handler']),
                $this->middlewareNames($route['middleware']),
            ],
            $this->router->getRoutes(),
        );

        if ($rows === []) {
            echo "No routes registered.\n";

            return 0;
        }

        $headers = ['Method', 'Path', 'Name', 'Handler', 'Middleware'];
        $widths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $column => $value) {
                $widths[$column] = max($widths[$column], strlen($value));
            }
        }

        $this->printRow($headers, $widths);
        $this->printRow(array_map(static fn (int $width): string => str_repeat('-', $width), $widths), $widths);

        foreach ($rows as $row) {
            $this->printRow($row, $widths);
        }

        return 0;
    }

    /**
     * Return a readable controller action name.
     *
     * @param array{0: string, 1: string}|string $handler
     */
    private function handlerName(array|string $handler): string
    {
        return is_array($handler)
            ? implode('@', array_map(static fn (mixed $part): string => (string) $part, $handler))
            : $handler;
    }

    /**
     * Return comma-separated middleware class names.
     *
     * @param list<Middleware|string> $middleware
     */
    private function middlewareNames(array $middleware): string
    {
        if ($middleware === []) {
            return '-';
        }

        return implode(', ', array_map(
            static fn (Middleware|string $item): string => is_string($item) ? $item : $item::class,
            $middleware,
        ));
    }

    /**
     * Print one padded table row.
     *
     * @param list<string> $columns
     * @param list<int>    $widths
     */
    private function printRow(array $columns, array $widths): void
    {
        $cells = [];

        foreach ($columns as $index => $column) {
            $cells[] = str_pad($column, $widths[$index]);
        }

        echo rtrim(implode('  ', $cells))."\n";
    }
}
