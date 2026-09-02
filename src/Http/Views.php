<?php

declare(strict_types=1);

namespace Ghapi\Http;

use Ghapi\Collect\SnapshotBuilder;
use Ghapi\Support\Labels;

final class Views
{
    public const GROUPS = ['year', 'month', 'week', 'day', 'hour', 'weekday', 'punchcard'];
    public const RANGES = ['all', '365d', '90d', '30d'];

    public static function strip(array $snapshot): array
    {
        unset($snapshot['repos']);
        foreach ($snapshot['sizes']['largest'] ?? [] as $index => $commit) {
            unset($snapshot['sizes']['largest'][$index]['repo'], $snapshot['sizes']['largest'][$index]['sha']);
        }
        foreach ($snapshot['frameworks'] ?? [] as $index => $framework) {
            unset($snapshot['frameworks'][$index]['repoNames']);
        }
        return $snapshot;
    }

    public static function summary(array $s, bool $public, Labels $labels): array
    {
        $oss = $s['opensource'];
        $summary = [
            'scope' => $s['scope'],
            'generatedAt' => $s['generatedAt'],
            'identity' => $s['identity'],
            'totals' => $s['totals'],
            'streak' => $s['streak'],
            'languages' => array_map(static fn (array $l): array => [
                'name' => $l['name'], 'color' => $l['color'], 'share' => round($l['share'], 4), 'linesAdded' => $l['linesAdded'],
            ], array_slice($s['languages'], 0, 8)),
            'frameworks' => array_map(static fn (array $f): array => ['name' => $f['name'], 'repos' => $f['repos']], array_slice($s['frameworks'], 0, 8)),
            'tests' => $s['tests'],
            'commitSize' => ['median' => $s['sizes']['median'], 'p90' => $s['sizes']['p90'], 'mean' => $s['sizes']['mean']],
            'lifetime' => self::lifetime($s, $labels),
            'openSource' => [
                'pullRequests' => $oss['pullRequests'],
                'contributed' => $oss['contributed'],
                'targeted' => $oss['targeted'],
                'ownPublic' => array_diff_key($oss['ownPublic'], ['names' => true]),
                'totals' => $oss['totals'],
            ],
            'collector' => $s['collector'] ?? null,
        ];
        if (!$public) {
            $summary['topRepos'] = array_map(static fn (array $r): array => [
                'name' => $r['name'], 'commits' => $r['commits'], 'linesAdded' => $r['linesAdded'], 'status' => $r['status'],
            ], array_slice($s['repos'], 0, 10));
        }
        return $summary;
    }

    public static function commits(array $s, string $group, string $range, Labels $labels, ?string $from = null, ?string $to = null): array
    {
        $base = ['scope' => $s['scope'], 'generatedAt' => $s['generatedAt'], 'group' => $group, 'range' => $range];
        if (in_array($group, ['hour', 'weekday', 'punchcard'], true)) {
            $grid = $s['punch'][$range] ?? $s['punch']['all'];
            $total = array_sum(array_map('array_sum', $grid));
            if ($group === 'punchcard') {
                $cells = [];
                $peak = 0;
                foreach ($grid as $weekday => $hours) {
                    foreach ($hours as $hour => $count) {
                        $peak = max($peak, $count);
                        if ($count > 0) {
                            $cells[] = ['weekday' => $weekday + 1, 'hour' => $hour, 'commits' => $count];
                        }
                    }
                }
                return $base + ['timezone' => 'author local time', 'total' => $total, 'peak' => $peak, 'cells' => $cells];
            }
            $series = [];
            if ($group === 'hour') {
                for ($hour = 0; $hour < 24; $hour++) {
                    $count = array_sum(array_column($grid, $hour));
                    $series[] = ['hour' => $hour, 'commits' => $count, 'share' => $total > 0 ? round($count / $total, 4) : 0.0];
                }
            } else {
                foreach ($grid as $weekday => $hours) {
                    $count = array_sum($hours);
                    $series[] = ['weekday' => $weekday + 1, 'name' => $labels->get('weekday.' . ($weekday + 1)), 'commits' => $count, 'share' => $total > 0 ? round($count / $total, 4) : 0.0];
                }
            }
            $peak = $series === [] ? null : array_reduce($series, static fn (?array $carry, array $item): array => $carry === null || $item['commits'] > $carry['commits'] ? $item : $carry);
            return $base + ['timezone' => 'author local time', 'total' => $total, 'peak' => $peak, 'series' => $series];
        }

        $days = self::filterDays($s['days'], $range, $s['today'], $from, $to);
        $buckets = [];
        foreach ($days as [$date, $commits, $added, $removed]) {
            $key = self::periodKey($date, $group);
            $buckets[$key] ??= ['period' => $key, 'commits' => 0, 'linesAdded' => 0, 'linesRemoved' => 0, 'activeDays' => 0];
            $buckets[$key]['commits'] += $commits;
            $buckets[$key]['linesAdded'] += $added;
            $buckets[$key]['linesRemoved'] += $removed;
            $buckets[$key]['activeDays']++;
        }
        ksort($buckets);
        $series = array_values($buckets);
        return $base + [
            'from' => $days[0][0] ?? null,
            'to' => $days === [] ? null : $days[count($days) - 1][0],
            'total' => array_sum(array_column($series, 'commits')),
            'activeDays' => count($days),
            'series' => $series,
        ];
    }

    public static function loc(array $s, string $group, string $range, Labels $labels, ?string $from = null, ?string $to = null): array
    {
        $commits = self::commits($s, in_array($group, ['year', 'month', 'week', 'day'], true) ? $group : 'year', $range, $labels, $from, $to);
        $added = array_sum(array_column($commits['series'], 'linesAdded'));
        $removed = array_sum(array_column($commits['series'], 'linesRemoved'));
        $kinds = [];
        foreach ($s['kinds'] as $kind => $values) {
            $kinds[] = ['kind' => $kind, 'label' => $labels->get('kind.' . $kind)] + $values;
        }
        return [
            'scope' => $s['scope'],
            'generatedAt' => $s['generatedAt'],
            'group' => $commits['group'],
            'range' => $range,
            'totals' => [
                'linesAdded' => $added,
                'linesRemoved' => $removed,
                'linesNet' => $added - $removed,
                'removedToAdded' => $added > 0 ? round($removed / $added, 4) : 0.0,
                'allTime' => [
                    'linesAdded' => $s['totals']['linesAdded'],
                    'linesRemoved' => $s['totals']['linesRemoved'],
                    'codeLinesAdded' => $s['totals']['codeLinesAdded'],
                    'linesNet' => $s['totals']['linesNet'],
                    'charsOfCode' => $s['totals']['charsOfCode'],
                    'longestCommitChurn' => $s['totals']['longestCommitChurn'],
                ],
            ],
            'refactors' => $s['sizes']['refactors'],
            'kinds' => $kinds,
            'series' => array_map(static fn (array $row): array => [
                'period' => $row['period'], 'linesAdded' => $row['linesAdded'], 'linesRemoved' => $row['linesRemoved'],
                'linesNet' => $row['linesAdded'] - $row['linesRemoved'], 'commits' => $row['commits'],
            ], $commits['series']),
        ];
    }

    public static function languages(array $s, int $limit): array
    {
        return [
            'scope' => $s['scope'],
            'generatedAt' => $s['generatedAt'],
            'total' => count($s['languages']),
            'totals' => ['codeLinesAdded' => $s['totals']['codeLinesAdded'], 'linesNet' => $s['totals']['linesNet'], 'charsOfCode' => $s['totals']['charsOfCode']],
            'languages' => array_slice($s['languages'], 0, $limit),
        ];
    }

    public static function frameworks(array $s, bool $public, string $ecosystem): array
    {
        $rows = array_values(array_filter($s['frameworks'], static fn (array $f): bool => $ecosystem === '' || strcasecmp($f['ecosystem'], $ecosystem) === 0));
        if ($public) {
            $rows = array_map(static fn (array $f): array => array_diff_key($f, ['repoNames' => true]), $rows);
        }
        $ecosystems = [];
        foreach ($s['frameworks'] as $framework) {
            $ecosystems[$framework['ecosystem']] = ($ecosystems[$framework['ecosystem']] ?? 0) + 1;
        }
        arsort($ecosystems);
        return ['scope' => $s['scope'], 'generatedAt' => $s['generatedAt'], 'total' => count($rows), 'ecosystems' => $ecosystems, 'frameworks' => $rows];
    }

    public static function tests(array $s, bool $public): array
    {
        $byLanguage = [];
        foreach ($s['languages'] as $language) {
            $sum = $language['testLinesAdded'] + $language['codeLinesAdded'];
            if ($sum === 0) {
                continue;
            }
            $byLanguage[] = [
                'name' => $language['name'],
                'testLinesAdded' => $language['testLinesAdded'],
                'codeLinesAdded' => $language['codeLinesAdded'],
                'ratio' => round($language['testLinesAdded'] / $sum, 4),
            ];
        }
        $out = ['scope' => $s['scope'], 'generatedAt' => $s['generatedAt'], 'method' => 'lines added in test files (paths and names like tests/, *_test.go, *.spec.ts, FooTest.java) against other source files'] + $s['tests'] + ['byLanguage' => $byLanguage];
        if (!$public) {
            $out['byRepo'] = [];
            foreach ($s['repos'] as $repo) {
                $sum = $repo['testLinesAdded'] + $repo['codeLinesAdded'];
                if ($sum > 0) {
                    $out['byRepo'][] = ['name' => $repo['name'], 'testLinesAdded' => $repo['testLinesAdded'], 'codeLinesAdded' => $repo['codeLinesAdded'], 'ratio' => round($repo['testLinesAdded'] / $sum, 4)];
                }
            }
            usort($out['byRepo'], static fn (array $a, array $b): int => $b['ratio'] <=> $a['ratio']);
        }
        return $out;
    }

    public static function sizes(array $s, bool $public, Labels $labels): array
    {
        $sizes = $s['sizes'];
        foreach ($sizes['buckets'] as $index => $bucket) {
            $sizes['buckets'][$index]['label'] = $labels->get('size.' . $bucket['key']);
            $sizes['buckets'][$index]['share'] = $sizes['commits'] > 0 ? round($bucket['commits'] / $sizes['commits'], 4) : 0.0;
        }
        if ($public) {
            $sizes['largest'] = array_map(static fn (array $c): array => array_diff_key($c, ['repo' => true, 'sha' => true]), $sizes['largest']);
        }
        return ['scope' => $s['scope'], 'generatedAt' => $s['generatedAt'], 'metric' => 'lines added + lines removed per commit'] + $sizes;
    }

    public static function compare(array $s, string $period, Labels $labels): array
    {
        $today = $s['today'];
        $t = strtotime($today . ' 00:00:00 UTC');
        if ($period === 'week') {
            $currentStart = $t - ((int) gmdate('N', $t) - 1) * 86400;
            $previousStart = $currentStart - 7 * 86400;
            $previousEnd = $currentStart - 86400;
        } else {
            $currentStart = strtotime(gmdate('Y-m-01', $t) . ' 00:00:00 UTC');
            $previousStart = strtotime(gmdate('Y-m-01', $currentStart - 86400) . ' 00:00:00 UTC');
            $previousEnd = $currentStart - 86400;
        }
        $elapsed = intdiv($t - $currentStart, 86400);
        $previousToDateEnd = min($previousEnd, $previousStart + $elapsed * 86400);
        $window = static function (int $from, int $to) use ($s): array {
            $out = ['from' => gmdate('Y-m-d', $from), 'to' => gmdate('Y-m-d', $to), 'commits' => 0, 'linesAdded' => 0, 'linesRemoved' => 0, 'activeDays' => 0];
            $fromDate = $out['from'];
            $toDate = $out['to'];
            foreach ($s['days'] as [$date, $commits, $added, $removed]) {
                if ($date >= $fromDate && $date <= $toDate) {
                    $out['commits'] += $commits;
                    $out['linesAdded'] += $added;
                    $out['linesRemoved'] += $removed;
                    $out['activeDays']++;
                }
            }
            return $out;
        };
        $current = $window($currentStart, $t);
        $previous = $window($previousStart, $previousEnd);
        $previousToDate = $window($previousStart, $previousToDateEnd);
        $delta = static function (array $now, array $then): array {
            $out = [];
            foreach (['commits', 'linesAdded', 'linesRemoved', 'activeDays'] as $key) {
                $out[$key] = [
                    'change' => $now[$key] - $then[$key],
                    'percent' => $then[$key] > 0 ? round(($now[$key] - $then[$key]) / $then[$key] * 100, 1) : null,
                ];
            }
            return $out;
        };
        return [
            'scope' => $s['scope'],
            'generatedAt' => $s['generatedAt'],
            'period' => $period,
            'label' => $labels->get('period.' . $period),
            'note' => 'current period runs up to today; previousToDate covers the same number of days from the start of the previous period',
            'current' => $current,
            'previous' => $previous,
            'previousToDate' => $previousToDate,
            'vsPrevious' => $delta($current, $previous),
            'vsPreviousToDate' => $delta($current, $previousToDate),
        ];
    }

    public static function openSource(array $s, int $limit): array
    {
        $oss = $s['opensource'];
        $oss['projects'] = array_slice($oss['projects'], 0, $limit);
        $oss['pulls'] = array_slice($oss['pulls'], 0, $limit);
        return ['generatedAt' => $s['generatedAt']] + $oss;
    }

    public static function repos(array $s, bool $public, Request $request, Labels $labels): array
    {
        $rows = $s['repos'];
        if ($public) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => !$r['private']));
        }
        $status = $request->choice('status', ['active', 'maintained', 'dormant', 'inactive'], '');
        $visibility = $request->choice('visibility', ['public', 'private'], '');
        $language = $request->string('language');
        $rows = array_values(array_filter($rows, static function (array $r) use ($status, $visibility, $language): bool {
            if ($status !== '' && $r['status'] !== $status) {
                return false;
            }
            if ($visibility !== '' && ($visibility === 'private') !== $r['private']) {
                return false;
            }
            if ($language !== '') {
                foreach ($r['languages'] as $entry) {
                    if (strcasecmp($entry['name'], $language) === 0) {
                        return true;
                    }
                }
                return false;
            }
            return true;
        }));
        $sort = $request->choice('sort', ['commits', 'lines', 'recent', 'stars', 'lifetime', 'name'], 'commits');
        usort($rows, static fn (array $a, array $b): int => match ($sort) {
            'lines' => $b['linesAdded'] <=> $a['linesAdded'],
            'recent' => strcmp((string) $b['lastCommit'], (string) $a['lastCommit']),
            'stars' => $b['stars'] <=> $a['stars'],
            'lifetime' => $b['lifetimeDays'] <=> $a['lifetimeDays'],
            'name' => strcasecmp($a['name'], $b['name']),
            default => $b['commits'] <=> $a['commits'],
        });
        $limit = $request->int('limit', 200, 1, 500);
        return [
            'scope' => $s['scope'],
            'generatedAt' => $s['generatedAt'],
            'total' => count($rows),
            'lifetime' => self::lifetime($s, $labels),
            'repos' => array_map(static fn (array $r): array => array_diff_key($r, ['months' => true]), array_slice($rows, 0, $limit)),
        ];
    }

    public static function repo(array $s, string $name, bool $public): ?array
    {
        foreach ($s['repos'] as $repo) {
            if (strcasecmp($repo['name'], $name) === 0 && (!$public || !$repo['private'])) {
                return ['generatedAt' => $s['generatedAt']] + $repo;
            }
        }
        return null;
    }

    public static function lifetime(array $s, Labels $labels): array
    {
        $lifetime = $s['lifetime'];
        $lifetime['labels'] = [];
        foreach (['active', 'maintained', 'dormant', 'inactive'] as $status) {
            $lifetime['labels'][$status] = $labels->get('status.' . $status);
        }
        return $lifetime;
    }

    public static function filterDays(array $days, string $range, string $today, ?string $from, ?string $to): array
    {
        $limit = SnapshotBuilder::RANGES[$range] ?? null;
        $lower = $limit === null ? '0000-00-00' : gmdate('Y-m-d', strtotime($today . ' 00:00:00 UTC') - ($limit - 1) * 86400);
        if ($from !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $lower = max($lower, $from);
        }
        $upper = ($to !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) ? $to : '9999-99-99';
        return array_values(array_filter($days, static fn (array $d): bool => $d[0] >= $lower && $d[0] <= $upper));
    }

    public static function periodKey(string $date, string $group): string
    {
        return match ($group) {
            'year' => substr($date, 0, 4),
            'month' => substr($date, 0, 7),
            'week' => gmdate('o-\WW', strtotime($date . ' 00:00:00 UTC')),
            default => $date,
        };
    }
}
