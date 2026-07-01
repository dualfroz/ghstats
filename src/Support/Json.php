<?php

declare(strict_types=1);

namespace Ghapi\Support;

use RuntimeException;

final class Json
{
    public const FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    public static function encode(mixed $value, bool $pretty = false): string
    {
        $encoded = json_encode($value, self::FLAGS | ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            throw new RuntimeException('JSON encode failed: ' . json_last_error_msg());
        }
        return $encoded;
    }

    public static function decode(string $raw): mixed
    {
        return json_decode($raw, true, 512, JSON_BIGINT_AS_STRING);
    }

    public static function readFile(string $path): mixed
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        return self::decode($raw);
    }

    public static function writeFile(string $path, mixed $value, bool $pretty = false): void
    {
        Files::writeAtomic($path, self::encode($value, $pretty));
    }
}
