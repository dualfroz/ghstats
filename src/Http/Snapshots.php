<?php

declare(strict_types=1);

namespace Ghapi\Http;

use Ghapi\Collect\SnapshotBuilder;
use Ghapi\Support\Json;

final class Snapshots
{
    private ?array $index = null;
    private array $scopes = [];

    public function __construct(private readonly string $dir)
    {
    }

    public function index(): ?array
    {
        if ($this->index === null) {
            $index = Json::readFile($this->dir . '/index.json');
            $this->index = is_array($index) ? $index : [];
        }
        return $this->index === [] ? null : $this->index;
    }

    public function scopeFor(?string $owner): ?string
    {
        if ($owner === null || $owner === '' || strtolower($owner) === 'all') {
            return 'all';
        }
        foreach ($this->index()['owners'] ?? [] as $known) {
            if (strcasecmp((string) $known, $owner) === 0) {
                return 'owner-' . strtolower((string) $known);
            }
        }
        return null;
    }

    public function load(string $scope): ?array
    {
        if (!array_key_exists($scope, $this->scopes)) {
            if (!preg_match('/^(all|owner-[a-z0-9._-]+)$/', $scope)) {
                return null;
            }
            $data = Json::readFile($this->dir . '/scopes/' . $scope . '.json');
            if (is_array($data) && (int) ($data['schema'] ?? 0) !== SnapshotBuilder::SCHEMA) {
                $data = null;
            }
            $this->scopes[$scope] = is_array($data) ? $data : null;
        }
        return $this->scopes[$scope];
    }

    public function owners(): array
    {
        return $this->index()['owners'] ?? [];
    }

    public function version(): string
    {
        $file = $this->dir . '/index.json';
        return is_file($file) ? substr(hash('sha256', (string) filemtime($file) . ':' . (string) filesize($file)), 0, 16) : 'none';
    }
}
