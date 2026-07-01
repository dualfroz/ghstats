<?php

declare(strict_types=1);

namespace Ghapi\GitHub;

use Ghapi\Support\Http;
use Ghapi\Support\HttpResponse;
use Ghapi\Support\Json;
use Ghapi\Support\Log;
use RuntimeException;

class Client
{
    private const API = 'https://api.github.com';

    private array $remaining = ['core' => null, 'graphql' => null];
    private array $reset = ['core' => 0, 'graphql' => 0];
    private int $requests = 0;

    public function __construct(private readonly string $token)
    {
        if ($token === '') {
            throw new RuntimeException('GH_TOKEN is empty');
        }
    }

    public function graphql(string $query, array $variables = []): array
    {
        $payload = Json::encode(['query' => $query, 'variables' => (object) $variables]);
        for ($attempt = 1; ; $attempt++) {
            $response = $this->send('POST', self::API . '/graphql', ['Content-Type' => 'application/json'], $payload, 'graphql');
            $decoded = $response->json();
            $retryable = in_array($response->status, [0, 429, 500, 502, 503, 504], true)
                || ($response->status === 403 && $this->isSecondaryLimit($response));
            if ($retryable && $attempt < 6) {
                $this->backoff($response, $attempt);
                continue;
            }
            if ($response->status !== 200 || !is_array($decoded)) {
                throw new RuntimeException("GraphQL HTTP {$response->status}: " . substr($response->body, 0, 300));
            }
            if (!empty($decoded['errors']) && empty($decoded['data'])) {
                $message = Json::encode($decoded['errors']);
                if ($attempt < 4 && str_contains($message, 'timeout')) {
                    $this->backoff($response, $attempt);
                    continue;
                }
                throw new RuntimeException('GraphQL error: ' . substr($message, 0, 400));
            }
            return $decoded['data'] ?? [];
        }
    }

    public function rest(string $path, array $query = [], string $accept = 'application/vnd.github+json'): HttpResponse
    {
        $url = str_starts_with($path, 'https://') ? $path : self::API . $path;
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        for ($attempt = 1; ; $attempt++) {
            $response = $this->send('GET', $url, ['Accept' => $accept], null, 'core');
            $retryable = in_array($response->status, [0, 429, 500, 502, 503, 504], true)
                || ($response->status === 403 && $this->isSecondaryLimit($response));
            if ($retryable && $attempt < 6) {
                $this->backoff($response, $attempt);
                continue;
            }
            return $response;
        }
    }

    public function restJson(string $path, array $query = []): mixed
    {
        $response = $this->rest($path, $query);
        if ($response->status !== 200) {
            throw new RuntimeException("GET {$path} HTTP {$response->status}: " . substr($response->body, 0, 300));
        }
        return $response->json();
    }

    public function download(string $path, string $target, int $maxBytes): HttpResponse
    {
        $this->requests++;
        return Http::download(self::API . $path, [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ], $target, $maxBytes);
    }

    public function remaining(string $resource): ?int
    {
        return $this->remaining[$resource] ?? null;
    }

    public function resetAt(string $resource): int
    {
        return $this->reset[$resource] ?? 0;
    }

    public function requestCount(): int
    {
        return $this->requests;
    }

    public function refreshLimits(): array
    {
        $response = $this->send('GET', self::API . '/rate_limit', ['Accept' => 'application/vnd.github+json'], null, 'none');
        $data = $response->json();
        foreach (['core', 'graphql'] as $resource) {
            if (isset($data['resources'][$resource])) {
                $this->remaining[$resource] = (int) $data['resources'][$resource]['remaining'];
                $this->reset[$resource] = (int) $data['resources'][$resource]['reset'];
            }
        }
        return ['remaining' => $this->remaining, 'reset' => $this->reset];
    }

    protected function send(string $method, string $url, array $headers, ?string $body, string $resource): HttpResponse
    {
        $this->requests++;
        $headers['Authorization'] = 'Bearer ' . $this->token;
        $headers['X-GitHub-Api-Version'] = '2022-11-28';
        $response = Http::request($method, $url, $headers, $body, 90);
        if ($resource !== 'none' && $response->header('x-ratelimit-remaining') !== null) {
            $name = $response->header('x-ratelimit-resource') ?? $resource;
            if (isset($this->remaining[$name]) || array_key_exists($name, $this->remaining)) {
                $this->remaining[$name] = (int) $response->header('x-ratelimit-remaining');
                $this->reset[$name] = (int) $response->header('x-ratelimit-reset');
            }
        }
        return $response;
    }

    private function isSecondaryLimit(HttpResponse $response): bool
    {
        return $response->header('retry-after') !== null
            || str_contains(strtolower($response->body), 'secondary rate limit')
            || $response->header('x-ratelimit-remaining') === '0';
    }

    private function backoff(HttpResponse $response, int $attempt): void
    {
        $wait = (int) ($response->header('retry-after') ?? 0);
        if ($wait <= 0 && $response->header('x-ratelimit-remaining') === '0') {
            $wait = max(1, (int) $response->header('x-ratelimit-reset') - time() + 1);
        }
        if ($wait <= 0) {
            $wait = min(60, 2 ** $attempt);
        }
        if ($wait > 900) {
            throw new RuntimeException("GitHub asks to wait {$wait}s, giving up for this run");
        }
        Log::warn("GitHub HTTP {$response->status}, retrying in {$wait}s (attempt {$attempt})");
        sleep($wait);
    }
}
