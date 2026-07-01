<?php

declare(strict_types=1);

namespace Ghapi\Support;

final class Log
{
    private static bool $quiet = false;

    public static function quiet(bool $quiet = true): void
    {
        self::$quiet = $quiet;
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message);
    }

    public static function warn(string $message): void
    {
        self::write('WARN', $message);
    }

    private static function write(string $level, string $message): void
    {
        if (self::$quiet) {
            return;
        }
        $line = gmdate('Y-m-d H:i:s') . " {$level} {$message}";
        if (defined('STDERR')) {
            fwrite(STDERR, $line . "\n");
        } else {
            error_log('ghapi ' . $line);
        }
    }
}
