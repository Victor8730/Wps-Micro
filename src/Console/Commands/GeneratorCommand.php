<?php

declare(strict_types=1);

namespace WpsMicro\Core\Console\Commands;

abstract class GeneratorCommand
{
    /**
     * Directory where generated files are written.
     */
    protected string $targetPath;

    /**
     * Create the generator command.
     */
    public function __construct(string $targetPath)
    {
        $this->targetPath = rtrim($targetPath, '/');
    }

    /**
     * Return the first CLI argument as a required name.
     */
    protected function requiredName(array $arguments): string
    {
        $name = $arguments[0] ?? '';

        if ($name === '') {
            throw new \InvalidArgumentException('Name argument is required.');
        }

        return $name;
    }

    /**
     * Convert a name to a studly class segment.
     */
    protected function studly(string $name): string
    {
        $name = str_replace(['-', '_', '/'], ' ', $name);
        $name = str_replace(' ', '', ucwords($name));

        return preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'Generated';
    }

    /**
     * Write a generated file.
     */
    protected function writeFile(string $path, string $content): int
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            echo 'Unable to create directory: ' . $directory . "\n";

            return 1;
        }

        if (is_file($path)) {
            echo 'File already exists: ' . $path . "\n";

            return 1;
        }

        if (file_put_contents($path, $content) === false) {
            echo 'Unable to write file: ' . $path . "\n";

            return 1;
        }

        echo 'Created: ' . $path . "\n";

        return 0;
    }
}
