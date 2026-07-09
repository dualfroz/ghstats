<?php

declare(strict_types=1);

namespace Ghapi\Collect;

use Ghapi\Support\Files;
use Ghapi\Support\Json;

final class StateStore
{
    public function __construct(private readonly string $dir)
    {
        Files::ensureDir($dir . '/repos', 0750);
    }

    public function dir(): string
    {
        return $this->dir;
    }

    public function repoPath(string $nameWithOwner): string
    {
        [$owner, $name] = explode('/', strtolower($nameWithOwner), 2);
        return $this->dir . '/repos/' . self::safe($owner) . '/' . self::safe($name) . '.json';
    }

    public function loadRepo(string $nameWithOwner): array
    {
        $state = Json::readFile($this->repoPath($nameWithOwner));
        if (!is_array($state)) {
            $state = [];
        }
        return $state + [
            'name' => $nameWithOwner,
            'meta' => [],
            'heads' => [],
            'commits' => [],
            'tree' => null,
            'walkedAt' => null,
        ];
    }

    public function saveRepo(array $state): void
    {
        Json::writeFile($this->repoPath($state['name']), $state);
    }

    public function allRepoNames(): array
    {
        $names = [];
        foreach (glob($this->dir . '/repos/*/*.json') ?: [] as $file) {
            $state = Json::readFile($file);
            if (is_array($state) && isset($state['name'])) {
                $names[$state['name']] = $file;
            }
        }
        ksort($names, SORT_STRING | SORT_FLAG_CASE);
        return $names;
    }

    public function removeRepo(string $nameWithOwner): void
    {
        @unlink($this->repoPath($nameWithOwner));
    }

    public function load(string $name): ?array
    {
        $value = Json::readFile($this->dir . '/' . $name . '.json');
        return is_array($value) ? $value : null;
    }

    public function save(string $name, array $value): void
    {
        Json::writeFile($this->dir . '/' . $name . '.json', $value);
    }

    private static function safe(string $segment): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', $segment) ?? $segment;
    }
}
