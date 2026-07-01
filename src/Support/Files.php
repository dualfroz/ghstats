<?php

declare(strict_types=1);

namespace Ghapi\Support;

use RuntimeException;

final class Files
{
    public static function ensureDir(string $dir, int $mode = 0755): void
    {
        if (!is_dir($dir) && !@mkdir($dir, $mode, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create directory {$dir}");
        }
    }

    public static function writeAtomic(string $path, string $contents, int $mode = 0644): void
    {
        self::ensureDir(dirname($path));
        $temp = $path . '.tmp-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (@file_put_contents($temp, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write {$temp}");
        }
        @chmod($temp, $mode);
        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException("Cannot move {$temp} to {$path}");
        }
    }

    public static function removeTree(string $path): void
    {
        if ($path === '' || $path === '/' || !file_exists($path)) {
            return;
        }
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}
