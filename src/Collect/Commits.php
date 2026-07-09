<?php

declare(strict_types=1);

namespace Ghapi\Collect;

use DateTimeImmutable;

final class Commits
{
    public const T = 0;
    public const TZ = 1;
    public const ADDED = 2;
    public const REMOVED = 3;
    public const FILES = 4;

    public static function make(string $isoDate, int $added = 0, int $removed = 0, ?array $files = null): ?array
    {
        try {
            $date = new DateTimeImmutable($isoDate);
        } catch (\Exception) {
            return null;
        }
        return [$date->getTimestamp(), intdiv($date->getOffset(), 60), $added, $removed, $files];
    }

    public static function addFile(array &$files, string $path, int $added, int $removed): void
    {
        $language = Classifier::language($path) ?? '';
        $key = $language . '|' . Classifier::kind($path);
        $files[$key] ??= [0, 0];
        $files[$key][0] += $added;
        $files[$key][1] += $removed;
    }

    public static function withFiles(array $commit, array $files): array
    {
        $added = 0;
        $removed = 0;
        foreach ($files as [$a, $r]) {
            $added += $a;
            $removed += $r;
        }
        ksort($files, SORT_STRING);
        $commit[self::ADDED] = $added;
        $commit[self::REMOVED] = $removed;
        $commit[self::FILES] = $files;
        return $commit;
    }

    public static function localDate(array $commit): string
    {
        return gmdate('Y-m-d', $commit[self::T] + $commit[self::TZ] * 60);
    }

    public static function localHour(array $commit): int
    {
        return (int) gmdate('G', $commit[self::T] + $commit[self::TZ] * 60);
    }

    public static function localWeekday(array $commit): int
    {
        return (int) gmdate('N', $commit[self::T] + $commit[self::TZ] * 60);
    }

    public static function isPending(array $commit): bool
    {
        return $commit[self::FILES] === null;
    }

    public static function parseNumstat(string $line): ?array
    {
        $parts = explode("\t", $line, 3);
        if (count($parts) !== 3) {
            return null;
        }
        $added = $parts[0] === '-' ? 0 : (ctype_digit($parts[0]) ? (int) $parts[0] : null);
        $removed = $parts[1] === '-' ? 0 : (ctype_digit($parts[1]) ? (int) $parts[1] : null);
        if ($added === null || $removed === null) {
            return null;
        }
        return [$added, $removed, Classifier::normalizeNumstatPath($parts[2])];
    }
}
