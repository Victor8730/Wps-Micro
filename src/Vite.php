<?php

declare(strict_types=1);

namespace WpsMicro\Core;

class Vite
{
    private Config $config;

    private ?array $manifest = null;

    /**
     * Create the Vite asset renderer.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Render development or production tags for an entry point.
     */
    public function tags(string $entry): string
    {
        $devServerUrl = rtrim((string) $this->config->get('vite.dev_server_url', ''), '/');

        if ($devServerUrl !== '') {
            return implode("\n", [
                $this->scriptTag($devServerUrl . '/@vite/client'),
                $this->scriptTag($devServerUrl . '/' . ltrim($entry, '/')),
            ]);
        }

        $manifest = $this->manifest();
        $chunk = $manifest[$entry] ?? null;

        if (!is_array($chunk) || !isset($chunk['file'])) {
            throw new \RuntimeException('Vite entry was not found in the manifest: ' . $entry);
        }

        $imports = $this->imports($manifest, $chunk);
        $stylesheets = [];
        $preloads = [];

        foreach ([$chunk, ...$imports] as $asset) {
            foreach ((array) ($asset['css'] ?? []) as $css) {
                $stylesheets[(string) $css] = true;
            }
        }

        foreach ($imports as $import) {
            if (isset($import['file'])) {
                $preloads[(string) $import['file']] = true;
            }
        }

        $tags = [];

        foreach (array_keys($stylesheets) as $stylesheet) {
            $tags[] = '<link rel="stylesheet" href="' . $this->escape($this->assetUrl($stylesheet)) . '">';
        }

        foreach (array_keys($preloads) as $preload) {
            $tags[] = '<link rel="modulepreload" href="' . $this->escape($this->assetUrl($preload)) . '">';
        }

        $tags[] = $this->scriptTag($this->assetUrl((string) $chunk['file']));

        return implode("\n", $tags);
    }

    /**
     * Load and cache the production manifest.
     */
    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $path = (string) $this->config->get('vite.manifest_path', '');

        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Vite manifest is not readable: ' . $path);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read the Vite manifest: ' . $path);
        }

        $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($manifest)) {
            throw new \RuntimeException('Vite manifest must contain a JSON object.');
        }

        return $this->manifest = $manifest;
    }

    /**
     * Resolve imported chunks recursively without duplicates.
     */
    private function imports(array $manifest, array $chunk, array &$seen = []): array
    {
        $imports = [];

        foreach ((array) ($chunk['imports'] ?? []) as $name) {
            $name = (string) $name;

            if (isset($seen[$name]) || !isset($manifest[$name]) || !is_array($manifest[$name])) {
                continue;
            }

            $seen[$name] = true;
            $import = $manifest[$name];
            $imports = [...$imports, ...$this->imports($manifest, $import, $seen), $import];
        }

        return $imports;
    }

    /**
     * Build a public URL for a compiled asset.
     */
    private function assetUrl(string $file): string
    {
        $baseUrl = rtrim((string) $this->config->get('app.url', ''), '/');
        $buildPath = trim((string) $this->config->get('vite.build_path', 'build'), '/');

        return $baseUrl . '/' . ($buildPath === '' ? '' : $buildPath . '/') . ltrim($file, '/');
    }

    /**
     * Render an escaped JavaScript module tag.
     */
    private function scriptTag(string $url): string
    {
        return '<script type="module" src="' . $this->escape($url) . '"></script>';
    }

    /**
     * Escape an HTML attribute value.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
