<?php

declare(strict_types=1);

namespace Ghapi\Collect;

use DateTimeImmutable;
use DateTimeZone;
use Ghapi\Config;
use Ghapi\Support\Files;
use Ghapi\Support\Json;
use Ghapi\Support\LanguageColors;

final class SnapshotBuilder
{
    public const SCHEMA = 2;
    public const RANGES = ['all' => null, '365d' => 365, '90d' => 90, '30d' => 30];

    private const ROLE_SUFFIXES = [
        'backend', 'frontend', 'api', 'web', 'app', 'discord', 'bot', 'panel', 'server', 'client', 'config', 'mobile',
        'ios', 'android', 'desktop', 'tui', 'cli', 'docs', 'site', 'landing', 'admin', 'core', 'common', 'shared',
        'infra', 'worker', 'gateway',
    ];

    private const SIZE_BUCKETS = [
        ['key' => 'xs', 'min' => 0, 'max' => 9],
        ['key' => 's', 'min' => 10, 'max' => 49],
        ['key' => 'm', 'min' => 50, 'max' => 199],
        ['key' => 'l', 'min' => 200, 'max' => 999],
        ['key' => 'xl', 'min' => 1000, 'max' => 4999],
        ['key' => 'xxl', 'min' => 5000, 'max' => null],
    ];

    public function __construct(
        private readonly Config $config,
        private readonly StateStore $store,
        private readonly ?int $now = null,
    ) {
    }

    public function currentTotals(): ?array
    {
        $current = Json::readFile($this->config->string('snapshotDir') . '/scopes/all.json');
        if (!is_array($current) || !is_array($current['totals'] ?? null)) {
            return null;
        }
        return $current['totals'] + ['generatedAt' => $current['generatedAt'] ?? null];
    }

    public function build(array $meta = []): array
    {
        $now = $this->now ?? time();
        $timezone = new DateTimeZone($this->config->string('timezone') ?: 'UTC');
        $today = (new DateTimeImmutable('@' . $now))->setTimezone($timezone)->format('Y-m-d');
        $generatedAt = gmdate('c', $now);
        $remoteIndex = [];
        foreach (($this->store->load('repositories')['repositories'] ?? []) as $remote) {
            $remoteIndex[strtolower($remote['name'])] = $remote;
        }

        $repos = [];
        foreach ($this->store->allRepoNames() as $name => $file) {
            $state = $this->store->loadRepo($name);
            $info = ($remoteIndex[strtolower($name)] ?? []) + ($state['meta'] ?? []);
            if (($info['fork'] ?? false) || $state['commits'] === []) {
                continue;
            }
            $repos[] = ['name' => $info['name'] ?? $state['name'], 'info' => $info, 'state' => $state];
        }

        $owners = [];
        foreach ($repos as $repo) {
            $owner = explode('/', $repo['name'])[0];
            $owners[strtolower($owner)] = $owner;
        }
        $scopes = ['all' => $repos];
        foreach ($owners as $key => $owner) {
            $scopes['owner-' . $key] = array_values(array_filter($repos, static fn (array $repo): bool => strcasecmp(explode('/', $repo['name'])[0], $owner) === 0));
        }

        $openSource = $this->openSource($repos, $remoteIndex);
        $dir = $this->config->string('snapshotDir');
        Files::ensureDir($dir . '/scopes');
        $index = [
            'schema' => self::SCHEMA,
            'generatedAt' => $generatedAt,
            'scopes' => [],
            'owners' => array_values($owners),
            'collector' => [
                'lastRefreshAt' => $meta['lastRefreshAt'] ?? null,
                'lastFullAt' => $meta['lastFullAt'] ?? null,
                'lastRunAt' => $meta['lastRunAt'] ?? null,
                'lastRunSeconds' => $meta['lastRunSeconds'] ?? null,
                'pendingDetails' => $meta['pendingDetails'] ?? null,
                'rateLimit' => $meta['rateLimit'] ?? null,
                'lastReport' => $meta['lastReport'] ?? null,
            ],
        ];
        $all = null;
        foreach ($scopes as $scope => $scopeRepos) {
            $snapshot = $this->aggregate($scope, $scopeRepos, $now, $today, $generatedAt);
            $snapshot['opensource'] = $openSource;
            $snapshot['collector'] = [
                'lastRefreshAt' => $meta['lastRefreshAt'] ?? null,
                'lastRunAt' => $meta['lastRunAt'] ?? null,
                'pendingDetails' => $snapshot['totals']['pendingDetails'],
            ];
            Json::writeFile($dir . '/scopes/' . $scope . '.json', $snapshot);
            $index['scopes'][$scope] = ['commits' => $snapshot['totals']['commits'], 'repos' => $snapshot['totals']['repos']];
            if ($scope === 'all') {
                $all = $snapshot;
            }
        }
        foreach (glob($dir . '/scopes/*.json') ?: [] as $file) {
            if (!isset($scopes[basename($file, '.json')])) {
                @unlink($file);
            }
        }
        Json::writeFile($dir . '/index.json', $index);
        return $all ?? ['generatedAt' => $generatedAt, 'totals' => []];
    }

    public function aggregate(string $scope, array $repos, int $now, string $today, string $generatedAt): array
    {
        $emptyGrid = static fn (): array => array_fill(0, 7, array_fill(0, 24, 0));
        $days = [];
        $punch = [];
        foreach (array_keys(self::RANGES) as $range) {
            $punch[$range] = $emptyGrid();
        }
        $languages = [];
        $kinds = array_fill_keys(Classifier::KINDS, ['added' => 0, 'removed' => 0, 'commits' => 0]);
        $sizes = [];
        $largest = [];
        $refactors = ['commits' => 0, 'linesRemoved' => 0];
        $totals = ['commits' => 0, 'linesAdded' => 0, 'linesRemoved' => 0, 'pendingDetails' => 0, 'longestCommitChurn' => 0];
        $repoRows = [];
        $frameworks = [];
        $treeLanguages = [];
        $projects = [];
        $stars = 0;
        $firstT = null;
        $lastT = null;
        $todayTs = (new DateTimeImmutable($today . ' 00:00:00', new DateTimeZone('UTC')))->getTimestamp();

        foreach ($repos as $repo) {
            $info = $repo['info'];
            $name = $repo['name'];
            $row = [
                'name' => $name,
                'owner' => explode('/', $name)[0],
                'private' => (bool) ($info['private'] ?? true),
                'archived' => (bool) ($info['archived'] ?? false),
                'stars' => (int) ($info['stars'] ?? 0),
                'forks' => (int) ($info['forks'] ?? 0),
                'description' => $info['description'] ?? null,
                'homepage' => $info['homepage'] ?? null,
                'license' => $info['license'] ?? null,
                'primaryLanguage' => $info['primaryLanguage'] ?? null,
                'defaultBranch' => $info['defaultBranch'] ?? null,
                'createdAt' => $info['createdAt'] ?? null,
                'pushedAt' => $info['pushedAt'] ?? null,
                'commits' => 0,
                'linesAdded' => 0,
                'linesRemoved' => 0,
                'testLinesAdded' => 0,
                'codeLinesAdded' => 0,
                'firstCommit' => null,
                'lastCommit' => null,
                'lifetimeDays' => 0,
                'status' => 'inactive',
                'languages' => [],
                'codeLines' => null,
                'frameworks' => [],
                'pendingDetails' => 0,
                'months' => [],
            ];
            $stars += $row['stars'];
            $projects[self::projectKey($name)] = true;
            $repoFirst = null;
            $repoLast = null;
            $repoLanguages = [];
            $repoMonths = [];

            foreach ($repo['state']['commits'] as $sha => $commit) {
                $t = $commit[Commits::T];
                $date = Commits::localDate($commit);
                $weekday = Commits::localWeekday($commit) - 1;
                $hour = Commits::localHour($commit);
                $added = $commit[Commits::ADDED];
                $removed = $commit[Commits::REMOVED];
                $churn = $added + $removed;

                $totals['commits']++;
                $row['commits']++;
                $days[$date] ??= [0, 0, 0];
                $days[$date][0]++;
                $days[$date][1] += $added;
                $days[$date][2] += $removed;
                $ageDays = intdiv($todayTs - (new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp(), 86400);
                foreach (self::RANGES as $range => $limit) {
                    if ($limit === null || $ageDays < $limit) {
                        $punch[$range][$weekday][$hour]++;
                    }
                }
                $firstT = $firstT === null ? $t : min($firstT, $t);
                $lastT = $lastT === null ? $t : max($lastT, $t);
                $repoFirst = $repoFirst === null ? $t : min($repoFirst, $t);
                $repoLast = $repoLast === null ? $t : max($repoLast, $t);
                $month = substr($date, 0, 7);
                $repoMonths[$month] ??= [$month, 0, 0, 0];
                $repoMonths[$month][1]++;
                $repoMonths[$month][2] += $added;
                $repoMonths[$month][3] += $removed;

                if (Commits::isPending($commit)) {
                    $totals['pendingDetails']++;
                    $row['pendingDetails']++;
                    continue;
                }

                $totals['linesAdded'] += $added;
                $totals['linesRemoved'] += $removed;
                $totals['longestCommitChurn'] = max($totals['longestCommitChurn'], $churn);
                $row['linesAdded'] += $added;
                $row['linesRemoved'] += $removed;
                $sizes[] = $churn;
                if ($removed > $added && $removed >= 20) {
                    $refactors['commits']++;
                    $refactors['linesRemoved'] += $removed;
                }
                $largest[] = ['repo' => $name, 'sha' => (string) $sha, 'date' => $date, 'added' => $added, 'removed' => $removed];
                if (count($largest) > 40) {
                    usort($largest, static fn (array $a, array $b): int => ($b['added'] + $b['removed']) <=> ($a['added'] + $a['removed']));
                    $largest = array_slice($largest, 0, 10);
                }

                $year = (int) substr($date, 0, 4);
                $touchedKinds = [];
                $touchedLanguages = [];
                foreach ($commit[Commits::FILES] as $key => [$fileAdded, $fileRemoved]) {
                    [$language, $kind] = explode('|', (string) $key, 2);
                    $kinds[$kind]['added'] += $fileAdded;
                    $kinds[$kind]['removed'] += $fileRemoved;
                    $touchedKinds[$kind] = true;
                    if ($language === '') {
                        continue;
                    }
                    $languages[$language] ??= ['added' => 0, 'removed' => 0, 'commits' => 0, 'repos' => [], 'firstYear' => $year, 'lastYear' => $year, 'testAdded' => 0, 'codeAdded' => 0];
                    $entry = &$languages[$language];
                    $entry['added'] += $fileAdded;
                    $entry['removed'] += $fileRemoved;
                    $entry['repos'][$name] = true;
                    $entry['firstYear'] = min($entry['firstYear'], $year);
                    $entry['lastYear'] = max($entry['lastYear'], $year);
                    if ($kind === 'test') {
                        $entry['testAdded'] += $fileAdded;
                        $row['testLinesAdded'] += $fileAdded;
                    } else {
                        $entry['codeAdded'] += $fileAdded;
                        $row['codeLinesAdded'] += $fileAdded;
                    }
                    unset($entry);
                    $touchedLanguages[$language] = true;
                    $repoLanguages[$language] = ($repoLanguages[$language] ?? 0) + $fileAdded;
                }
                foreach (array_keys($touchedKinds) as $kind) {
                    $kinds[$kind]['commits']++;
                }
                foreach (array_keys($touchedLanguages) as $language) {
                    $languages[$language]['commits']++;
                }
            }

            $row['firstCommit'] = $repoFirst === null ? null : gmdate('c', $repoFirst);
            $row['lastCommit'] = $repoLast === null ? null : gmdate('c', $repoLast);
            $row['lifetimeDays'] = $repoFirst === null ? 0 : (int) floor(($repoLast - $repoFirst) / 86400) + 1;
            $idle = $repoLast === null ? PHP_INT_MAX : intdiv($now - $repoLast, 86400);
            $row['status'] = $idle <= 30 ? 'active' : ($idle <= 180 ? 'maintained' : ($idle <= 365 ? 'dormant' : 'inactive'));
            ksort($repoMonths);
            $row['months'] = array_values($repoMonths);
            arsort($repoLanguages);
            $sum = array_sum($repoLanguages) ?: 1;
            foreach ($repoLanguages as $language => $lines) {
                $row['languages'][] = ['name' => $language, 'linesAdded' => $lines, 'share' => round($lines / $sum, 4)];
            }

            $tree = $repo['state']['tree'] ?? null;
            if (is_array($tree)) {
                foreach ($tree['frameworks'] ?? [] as $framework) {
                    $frameworks[$framework['name']] ??= ['name' => $framework['name'], 'ecosystem' => $framework['ecosystem'], 'repoNames' => []];
                    $frameworks[$framework['name']]['repoNames'][] = $name;
                    $row['frameworks'][] = $framework['name'];
                }
            }
            if (is_array($tree) && is_array($tree['languages'] ?? null)) {
                $row['codeLines'] = 0;
                foreach ($tree['languages'] as $language => $stats) {
                    $treeLanguages[$language] ??= ['code' => 0, 'bytes' => 0, 'repos' => []];
                    $treeLanguages[$language]['code'] += (int) $stats['code'];
                    $treeLanguages[$language]['bytes'] += (int) $stats['bytes'];
                    if ((int) $stats['code'] > 0) {
                        $treeLanguages[$language]['repos'][$name] = true;
                    }
                    $row['codeLines'] += (int) $stats['code'];
                }
            } elseif (!empty($info['githubLanguages'])) {
                foreach ($info['githubLanguages'] as $language) {
                    $treeLanguages[$language['name']] ??= ['code' => 0, 'bytes' => 0, 'repos' => []];
                    $treeLanguages[$language['name']]['bytes'] += (int) $language['bytes'];
                    $treeLanguages[$language['name']]['repos'][$name] = true;
                }
            }
            $repoRows[] = $row;
        }

        ksort($days);
        $activeDates = array_keys($days);
        $totalAddedByLanguage = array_sum(array_map(static fn (array $l): int => $l['added'], $languages)) ?: 0;
        $languageRows = [];
        foreach ($languages as $language => $entry) {
            $languageRows[] = [
                'name' => $language,
                'color' => LanguageColors::for($language),
                'linesAdded' => $entry['added'],
                'linesRemoved' => $entry['removed'],
                'linesNet' => $entry['added'] - $entry['removed'],
                'share' => $totalAddedByLanguage > 0 ? $entry['added'] / $totalAddedByLanguage : 0.0,
                'commits' => $entry['commits'],
                'repos' => count($entry['repos']),
                'firstYear' => $entry['firstYear'],
                'lastYear' => $entry['lastYear'],
                'codeLines' => (int) ($treeLanguages[$language]['code'] ?? 0),
                'bytes' => (int) ($treeLanguages[$language]['bytes'] ?? 0),
                'projects' => count($treeLanguages[$language]['repos'] ?? []) ?: count($entry['repos']),
                'testLinesAdded' => $entry['testAdded'],
                'codeLinesAdded' => $entry['codeAdded'],
            ];
        }
        usort($languageRows, static fn (array $a, array $b): int => [$b['linesAdded'], $a['name']] <=> [$a['linesAdded'], $b['name']]);

        $codeLinesLive = array_sum(array_map(static fn (array $l): int => $l['code'], $treeLanguages));
        $bytesLive = array_sum(array_map(static fn (array $l): int => $l['bytes'], $treeLanguages));
        $frameworkRows = array_values(array_map(static function (array $framework): array {
            sort($framework['repoNames'], SORT_STRING | SORT_FLAG_CASE);
            return $framework + ['repos' => count($framework['repoNames'])];
        }, $frameworks));
        usort($frameworkRows, static fn (array $a, array $b): int => [$b['repos'], $a['name']] <=> [$a['repos'], $b['name']]);
        usort($repoRows, static fn (array $a, array $b): int => [$b['commits'], $a['name']] <=> [$a['commits'], $b['name']]);
        usort($largest, static fn (array $a, array $b): int => ($b['added'] + $b['removed']) <=> ($a['added'] + $a['removed']));

        $codeAdded = $kinds['code']['added'];
        $testAdded = $kinds['test']['added'];
        $lifetime = ['active' => 0, 'maintained' => 0, 'dormant' => 0, 'inactive' => 0, 'archived' => 0];
        $lifetimes = [];
        foreach ($repoRows as $row) {
            $lifetime[$row['status']]++;
            if ($row['archived']) {
                $lifetime['archived']++;
            }
            $lifetimes[] = $row['lifetimeDays'];
        }
        $streak = self::streak($activeDates, $today);
        $firstYear = $firstT === null ? null : (int) gmdate('Y', $firstT);

        return [
            'schema' => self::SCHEMA,
            'scope' => $scope,
            'generatedAt' => $generatedAt,
            'today' => $today,
            'timezone' => $this->config->string('timezone'),
            'identity' => ['login' => $this->config->string('login'), 'name' => $this->config->string('name')],
            'totals' => [
                'commits' => $totals['commits'],
                'activeDays' => count($activeDates),
                'linesAdded' => $totals['linesAdded'],
                'linesRemoved' => $totals['linesRemoved'],
                'linesNet' => $totals['linesAdded'] - $totals['linesRemoved'],
                'codeLinesAdded' => $totalAddedByLanguage,
                'codeLinesLive' => $codeLinesLive,
                'charsOfCode' => $bytesLive,
                'languages' => count($languageRows),
                'repos' => count($repoRows),
                'publicRepos' => count(array_filter($repoRows, static fn (array $r): bool => !$r['private'])),
                'projects' => count($projects),
                'stars' => $stars,
                'frameworks' => count($frameworkRows),
                'longestCommitChurn' => $totals['longestCommitChurn'],
                'firstCommit' => $firstT === null ? null : gmdate('c', $firstT),
                'lastCommit' => $lastT === null ? null : gmdate('c', $lastT),
                'firstYear' => $firstYear,
                'yearsActive' => $firstYear === null ? 0 : (int) substr($today, 0, 4) - $firstYear + 1,
                'commitsPerActiveDay' => count($activeDates) > 0 ? round($totals['commits'] / count($activeDates), 2) : 0.0,
                'pendingDetails' => $totals['pendingDetails'],
            ],
            'streak' => $streak,
            'days' => array_map(static fn (string $date, array $d): array => [$date, $d[0], $d[1], $d[2]], array_keys($days), array_values($days)),
            'punch' => $punch,
            'languages' => $languageRows,
            'kinds' => $kinds,
            'tests' => [
                'testLinesAdded' => $testAdded,
                'codeLinesAdded' => $codeAdded,
                'ratio' => $codeAdded + $testAdded > 0 ? round($testAdded / ($codeAdded + $testAdded), 4) : 0.0,
                'reposWithTests' => count(array_filter($repoRows, static fn (array $r): bool => $r['testLinesAdded'] > 0)),
            ],
            'sizes' => self::sizes($sizes, array_slice($largest, 0, 10), $refactors),
            'frameworks' => $frameworkRows,
            'lifetime' => $lifetime + [
                'medianDays' => self::percentile($lifetimes, 0.5),
                'longestDays' => $lifetimes === [] ? 0 : max($lifetimes),
            ],
            'repos' => $repoRows,
        ];
    }

    public static function streak(array $sortedDates, string $today): array
    {
        $result = ['current' => 0, 'longest' => 0, 'currentStart' => null, 'currentEnd' => null, 'longestStart' => null, 'longestEnd' => null];
        if ($sortedDates === []) {
            return $result;
        }
        $set = array_flip($sortedDates);
        $runStart = $sortedDates[0];
        $previous = null;
        foreach ($sortedDates as $date) {
            if ($previous !== null && self::shift($previous, 1) !== $date) {
                $runStart = $date;
            }
            $length = self::daysBetween($runStart, $date) + 1;
            if ($length > $result['longest']) {
                $result['longest'] = $length;
                $result['longestStart'] = $runStart;
                $result['longestEnd'] = $date;
            }
            $previous = $date;
        }
        $anchor = isset($set[$today]) ? $today : (isset($set[self::shift($today, -1)]) ? self::shift($today, -1) : null);
        if ($anchor !== null) {
            $start = $anchor;
            while (isset($set[self::shift($start, -1)])) {
                $start = self::shift($start, -1);
            }
            $result['current'] = self::daysBetween($start, $anchor) + 1;
            $result['currentStart'] = $start;
            $result['currentEnd'] = $anchor;
        }
        return $result;
    }

    public static function projectKey(string $nameWithOwner): string
    {
        $name = strtolower(basename($nameWithOwner));
        $parts = explode('-', $name);
        if (count($parts) > 1 && in_array(end($parts), self::ROLE_SUFFIXES, true)) {
            array_pop($parts);
        }
        return implode('-', $parts);
    }

    private static function sizes(array $sizes, array $largest, array $refactors): array
    {
        sort($sizes);
        $count = count($sizes);
        $buckets = [];
        foreach (self::SIZE_BUCKETS as $bucket) {
            $buckets[] = $bucket + ['commits' => count(array_filter($sizes, static fn (int $s): bool => $s >= $bucket['min'] && ($bucket['max'] === null || $s <= $bucket['max'])))];
        }
        return [
            'commits' => $count,
            'mean' => $count > 0 ? round(array_sum($sizes) / $count, 1) : 0.0,
            'median' => self::percentile($sizes, 0.5),
            'p75' => self::percentile($sizes, 0.75),
            'p90' => self::percentile($sizes, 0.9),
            'p99' => self::percentile($sizes, 0.99),
            'max' => $count > 0 ? $sizes[$count - 1] : 0,
            'buckets' => $buckets,
            'largest' => $largest,
            'refactors' => $refactors + ['share' => $count > 0 ? round($refactors['commits'] / $count, 4) : 0.0],
        ];
    }

    public static function percentile(array $values, float $fraction): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);
        $index = (int) ceil($fraction * count($values)) - 1;
        return (int) $values[max(0, min(count($values) - 1, $index))];
    }

    private function openSource(array $repos, array $remoteIndex): array
    {
        $raw = $this->store->load('opensource') ?? [];
        $login = strtolower($this->config->string('login'));
        $ownedOwners = array_map('strtolower', $this->config->list('owners'));
        $pulls = [];
        $projects = [];
        $counts = ['total' => 0, 'merged' => 0, 'open' => 0, 'closed' => 0, 'draft' => 0];
        $mergeHours = [];
        $languages = [];
        foreach ($raw['pulls'] ?? [] as $pull) {
            $repo = $pull['repository'] ?? null;
            if ($repo === null || ($repo['isPrivate'] ?? false) || in_array(strtolower((string) ($repo['owner']['login'] ?? '')), $ownedOwners, true)) {
                continue;
            }
            $state = $pull['merged'] ? 'merged' : strtolower((string) $pull['state']);
            $counts['total']++;
            $counts[$state] = ($counts[$state] ?? 0) + 1;
            if ($pull['isDraft'] ?? false) {
                $counts['draft']++;
            }
            $key = $repo['nameWithOwner'];
            $projects[$key] ??= [
                'name' => $key,
                'url' => $repo['url'],
                'description' => $repo['description'],
                'stars' => (int) $repo['stargazerCount'],
                'forks' => (int) $repo['forkCount'],
                'language' => $repo['primaryLanguage']['name'] ?? null,
                'color' => $repo['primaryLanguage']['color'] ?? null,
                'pullRequests' => 0, 'merged' => 0, 'open' => 0, 'closed' => 0,
                'commits' => 0, 'linesAdded' => 0, 'linesRemoved' => 0,
                'firstAt' => $pull['createdAt'], 'lastMergedAt' => null,
            ];
            $project = &$projects[$key];
            $project['pullRequests']++;
            $project[$state]++;
            $project['firstAt'] = min($project['firstAt'], $pull['createdAt']);
            if ($pull['merged']) {
                $project['commits'] += (int) $pull['commits']['totalCount'];
                $project['linesAdded'] += (int) $pull['additions'];
                $project['linesRemoved'] += (int) $pull['deletions'];
                $project['lastMergedAt'] = max((string) $project['lastMergedAt'], (string) $pull['mergedAt']);
                $mergeHours[] = (int) round((strtotime((string) $pull['mergedAt']) - strtotime((string) $pull['createdAt'])) / 3600);
                if ($project['language'] !== null) {
                    $languages[$project['language']] = ($languages[$project['language']] ?? 0) + 1;
                }
            }
            unset($project);
            $pulls[] = [
                'repo' => $key,
                'number' => (int) $pull['number'],
                'title' => $pull['title'],
                'url' => $pull['url'],
                'state' => $state,
                'draft' => (bool) ($pull['isDraft'] ?? false),
                'createdAt' => $pull['createdAt'],
                'mergedAt' => $pull['mergedAt'],
                'linesAdded' => (int) $pull['additions'],
                'linesRemoved' => (int) $pull['deletions'],
                'commits' => (int) $pull['commits']['totalCount'],
                'stars' => (int) $repo['stargazerCount'],
            ];
        }
        $projects = array_values($projects);
        usort($projects, static fn (array $a, array $b): int => [$b['merged'], $b['stars']] <=> [$a['merged'], $a['stars']]);
        usort($pulls, static fn (array $a, array $b): int => strcmp((string) $b['createdAt'], (string) $a['createdAt']));
        $merged = array_values(array_filter($projects, static fn (array $p): bool => $p['merged'] > 0));
        arsort($languages);

        $ownPublic = ['repos' => 0, 'stars' => 0, 'forks' => 0, 'commits' => 0, 'linesAdded' => 0, 'linesRemoved' => 0, 'names' => []];
        $counted = [];
        foreach ($repos as $repo) {
            $counted[strtolower($repo['name'])] = $repo;
        }
        foreach ($remoteIndex as $key => $remote) {
            if ($remote['private'] || $remote['fork']) {
                continue;
            }
            $ownPublic['repos']++;
            $ownPublic['stars'] += (int) $remote['stars'];
            $ownPublic['forks'] += (int) $remote['forks'];
            $ownPublic['names'][] = $remote['name'];
            foreach ($counted[$key]['state']['commits'] ?? [] as $commit) {
                $ownPublic['commits']++;
                $ownPublic['linesAdded'] += $commit[Commits::ADDED];
                $ownPublic['linesRemoved'] += $commit[Commits::REMOVED];
            }
        }

        $contributed = [
            'repos' => count($merged),
            'stars' => array_sum(array_column($merged, 'stars')),
            'commits' => array_sum(array_column($merged, 'commits')),
            'linesAdded' => array_sum(array_column($merged, 'linesAdded')),
            'linesRemoved' => array_sum(array_column($merged, 'linesRemoved')),
            'medianHoursToMerge' => self::percentile($mergeHours, 0.5),
        ];
        return [
            'login' => $login,
            'fetchedAt' => $raw['fetchedAt'] ?? null,
            'followers' => (int) ($raw['profile']['followers']['totalCount'] ?? 0),
            'pullRequests' => $counts,
            'contributed' => $contributed,
            'targeted' => ['repos' => count($projects), 'stars' => array_sum(array_column($projects, 'stars'))],
            'ownPublic' => $ownPublic,
            'totals' => [
                'repos' => $contributed['repos'] + $ownPublic['repos'],
                'stars' => $contributed['stars'] + $ownPublic['stars'],
                'commits' => $contributed['commits'] + $ownPublic['commits'],
                'linesAdded' => $contributed['linesAdded'] + $ownPublic['linesAdded'],
                'linesRemoved' => $contributed['linesRemoved'] + $ownPublic['linesRemoved'],
            ],
            'languages' => array_map(static fn (string $name, int $count): array => ['name' => $name, 'mergedPullRequests' => $count], array_keys($languages), array_values($languages)),
            'projects' => $projects,
            'pulls' => array_slice($pulls, 0, 50),
        ];
    }

    private static function shift(string $date, int $days): string
    {
        return gmdate('Y-m-d', strtotime($date . ' 00:00:00 UTC') + $days * 86400);
    }

    private static function daysBetween(string $from, string $to): int
    {
        return intdiv(strtotime($to . ' 00:00:00 UTC') - strtotime($from . ' 00:00:00 UTC'), 86400);
    }
}
