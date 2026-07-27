<?php

declare(strict_types=1);

namespace Ghapi\Http;

use Ghapi\Support\Json;

final class Response
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }

    public static function json(mixed $data, int $status = 200, bool $pretty = false): self
    {
        return new self($status, ['Content-Type' => 'application/json; charset=utf-8'], Json::encode($data, $pretty));
    }

    public static function svg(string $svg): self
    {
        return new self(200, ['Content-Type' => 'image/svg+xml; charset=utf-8'], $svg);
    }

    public static function error(int $status, string $code, string $message): self
    {
        $response = self::json(['error' => ['code' => $code, 'message' => $message]], $status);
        $response->headers['Cache-Control'] = 'no-store';
        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(string $method = 'GET'): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        header('X-Content-Type-Options: nosniff');
        if ($method !== 'HEAD') {
            echo $this->body;
        }
    }
}
