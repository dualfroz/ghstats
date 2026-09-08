<?php

declare(strict_types=1);

namespace Ghapi\Http;

final class Compat
{
    public static function stats(array $s): array
    {
        $counts = [];
        $locPerDay = [];
        $byYear = [];
        foreach ($s['days'] as [$date, $commits, $added, $removed]) {
            $counts[$date] = $commits;
            $locPerDay[] = ['date' => $date, 'added' => $added, 'removed' => $removed];
            $byYear[substr($date, 0, 4)] = ($byYear[substr($date, 0, 4)] ?? 0) + $commits;
        }
        ksort($byYear);
        $today = strtotime($s['today'] . ' 00:00:00 UTC');
        $window = static function (int $days) use ($counts, $today): array {
            $list = [];
            $total = 0;
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = gmdate('Y-m-d', $today - $i * 86400);
                $count = $counts[$date] ?? 0;
                $total += $count;
                $list[] = ['date' => $date, 'count' => $count];
            }
            return ['total' => $total, 'days' => $list];
        };

        $maxLines = max(1, ...array_map(static fn (array $l): int => $l['linesAdded'], $s['languages'] ?: [['linesAdded' => 1]]));
        $year = (int) substr($s['today'], 0, 4);
        $languages = [];
        $meta = [];
        foreach ($s['languages'] as $language) {
            $languages[] = [
                'name' => $language['name'],
                'lines' => $language['linesAdded'],
                'share' => $language['share'],
                'color' => $language['color'],
                'codeLines' => $language['lines'],
                'projects' => $language['projects'],
            ];
            $volume = sqrt($language['linesAdded'] / $maxLines);
            $tenure = min(max(1, $year - $language['firstYear'] + 1), 6) / 6;
            $meta[] = [
                'name' => $language['name'],
                'stars' => (int) max(1, min(5, round(1 + 4 * (0.6 * $volume + 0.4 * $tenure)))),
                'since' => $language['firstYear'],
            ];
        }

        return [
            'locTotal' => $s['totals']['lines'],
            'charsOfCode' => $s['totals']['charsOfCode'],
            'ownCommits' => $s['totals']['commits'],
            'reposOwn' => $s['totals']['repos'],
            'projects' => $s['totals']['projects'],
            'activeDays' => $s['totals']['activeDays'],
            'linesAdded' => $s['totals']['linesAdded'],
            'linesRemoved' => $s['totals']['linesRemoved'],
            'longestCommitChurn' => $s['totals']['longestCommitChurn'],
            'languages' => $languages,
            'languageMeta' => $meta,
            'commitsLast30d' => $window(30),
            'commitsLastYear' => $window(371),
            'byYear' => array_map(static fn (string $y, int $c): array => ['year' => $y, 'count' => $c], array_map('strval', array_keys($byYear)), array_values($byYear)),
            'generatedAt' => $s['generatedAt'],
            'locPerDay' => $locPerDay,
        ];
    }
}
