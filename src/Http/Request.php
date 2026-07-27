<?php

declare(strict_types=1);

namespace Ghapi\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $path = '/' . trim(preg_replace('#/+#', '/', $path) ?? '/', '/');
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $body = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH'], true)
            ? (string) file_get_contents('php://input', false, null, 0, 1 << 20)
            : '';
        return new self(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), $path, $_GET, $headers, $body);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? null;
        return is_string($value) ? trim($value) : $default;
    }

    public function choice(string $key, array $allowed, string $default): string
    {
        $value = strtolower($this->string($key));
        return in_array($value, $allowed, true) ? $value : $default;
    }

    public function int(string $key, int $default, int $min, int $max): int
    {
        $value = $this->query[$key] ?? null;
        if (!is_string($value) || !preg_match('/^-?\d+$/', $value)) {
            return $default;
        }
        return max($min, min($max, (int) $value));
    }

    public function flag(string $key): bool
    {
        if (!array_key_exists($key, $this->query)) {
            return false;
        }
        $value = $this->query[$key];
        return !is_string($value) || $value === '' || in_array(strtolower($value), ['1', 'true', 'yes'], true);
    }

    public function lang(): string
    {
        $lang = $this->choice('lang', ['pl', 'en'], '');
        if ($lang !== '') {
            return $lang;
        }
        return 'en';
    }
}
