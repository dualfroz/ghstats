<?php

declare(strict_types=1);

namespace Ghapi\Discord;

use Ghapi\Config;
use Ghapi\Http\Request;
use Ghapi\Http\Response;
use Ghapi\Http\Snapshots;
use Ghapi\Http\Views;
use Ghapi\Support\Json;
use Ghapi\Support\Labels;

final class Interactions
{
    private const COLOR = 0x7aa2f7;
    private const EPHEMERAL = 64;

    public function __construct(
        private readonly Config $config,
        private readonly Snapshots $snapshots,
    ) {
    }

    public function handle(Request $request): Response
    {
        $publicKey = $this->config->string('discordPublicKey');
        $signature = (string) $request->header('x-signature-ed25519');
        $timestamp = (string) $request->header('x-signature-timestamp');
        if ($publicKey === '' || !self::verify($publicKey, $signature, $timestamp, $request->body)) {
            return Response::error(401, 'invalid_signature', 'Invalid request signature');
        }
        $interaction = Json::decode($request->body);
        if (!is_array($interaction)) {
            return Response::error(400, 'bad_request', 'Malformed interaction');
        }
        $type = (int) ($interaction['type'] ?? 0);
        if ($type === 1) {
            return Response::json(['type' => 1]);
        }
        $userId = (string) ($interaction['member']['user']['id'] ?? $interaction['user']['id'] ?? '');
        $owner = $userId !== '' && hash_equals($this->config->string('discordUserId'), $userId);
        $labels = Labels::of(str_starts_with((string) ($interaction['locale'] ?? 'en'), 'pl') ? 'pl' : 'en');
        $data = $interaction['data'] ?? [];
        $options = [];
        $focused = null;
        foreach ($data['options'] ?? [] as $option) {
            $options[$option['name']] = $option['value'] ?? null;
            if ($option['focused'] ?? false) {
                $focused = $option['name'];
            }
        }
        if ($type === 4) {
            return Response::json(['type' => 8, 'data' => ['choices' => $this->autocomplete((string) ($data['name'] ?? ''), (string) $focused, (string) ($options[$focused] ?? ''), $owner)]]);
        }
        if ($type !== 2) {
            return Response::error(400, 'unsupported', 'Unsupported interaction type');
        }
        $message = $this->command((string) ($data['name'] ?? ''), $options, $owner, $labels);
        if (!empty($options['hidden']) || ($message['ephemeral'] ?? false)) {
            $message['flags'] = self::EPHEMERAL;
        }
        unset($message['ephemeral']);
        $message['allowed_mentions'] = ['parse' => []];
        return Response::json(['type' => 4, 'data' => $message]);
    }

    public static function verify(string $publicKeyHex, string $signatureHex, string $timestamp, string $body): bool
    {
        if (!preg_match('/^[0-9a-f]{128}$/i', $signatureHex) || !preg_match('/^[0-9a-f]{64}$/i', $publicKeyHex) || $timestamp === '') {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 300 && ctype_digit($timestamp)) {
            return false;
        }
        return sodium_crypto_sign_verify_detached((string) hex2bin($signatureHex), $timestamp . $body, (string) hex2bin($publicKeyHex));
    }

    private function autocomplete(string $command, string $option, string $typed, bool $owner): array
    {
        $values = [];
        if ($option === 'owner') {
            $values = array_merge(['all'], $this->snapshots->owners());
        } elseif ($command === 'repo' && $option === 'name') {
            foreach ($this->snapshots->load('all')['repos'] ?? [] as $repo) {
                if ($owner || !$repo['private']) {
                    $values[] = $repo['name'];
                }
            }
        }
        $typed = strtolower($typed);
        $matches = array_values(array_filter($values, static fn (string $value): bool => $typed === '' || str_contains(strtolower($value), $typed)));
        return array_map(static fn (string $value): array => ['name' => substr($value, 0, 100), 'value' => substr($value, 0, 100)], array_slice($matches, 0, 25));
    }

    private function command(string $name, array $options, bool $owner, Labels $l): array
    {
        $scope = $this->snapshots->scopeFor(isset($options['owner']) ? (string) $options['owner'] : null);
        $snapshot = $scope === null ? null : $this->snapshots->load($scope);
        if ($name === 'help') {
            return $this->help($l, $owner);
        }
        if ($snapshot === null) {
            return ['content' => $scope === null ? ($l->lang === 'pl' ? 'Nie znam takiego konta.' : 'Unknown account.') : $l->get('nodata'), 'ephemeral' => true];
        }
        $public = !$owner;
        if ($public) {
            $snapshot = Views::strip($snapshot) + ['repos' => array_values(array_filter($snapshot['repos'], static fn (array $r): bool => !$r['private']))];
        }
        $pl = $l->lang === 'pl';
        $n = static fn (int|float $value): string => $l->number($value);
        $scopeLabel = $scope === 'all' ? ($pl ? 'wszystkie konta' : 'all accounts') : substr((string) $scope, 6);
        $footer = ['text' => ($pl ? 'Dane z ' : 'Data from ') . substr((string) $snapshot['generatedAt'], 0, 16) . ' UTC · ' . $scopeLabel . ($public ? ($pl ? ' · widok publiczny' : ' · public view') : '')];

        switch ($name) {
            case 'summary':
                $t = $snapshot['totals'];
                $oss = $snapshot['opensource'];
                $fields = [
                    self::field($pl ? 'Commity' : 'Commits', $n($t['commits'])),
                    self::field($pl ? 'Dni z pracą' : 'Active days', $n($t['activeDays'])),
                    self::field($pl ? 'Seria' : 'Streak', $n($snapshot['streak']['current']) . ' / ' . $n($snapshot['streak']['longest'])),
                    self::field($pl ? 'Linie dodane' : 'Lines added', $n($t['linesAdded'])),
                    self::field($pl ? 'Linie usunięte' : 'Lines removed', $n($t['linesRemoved'])),
                    self::field($pl ? 'Kod żyjący' : 'Living code', $n($t['codeLinesLive'])),
                    self::field($pl ? 'Repozytoria' : 'Repositories', $n($t['repos'])),
                    self::field($pl ? 'Języki' : 'Languages', $n($t['languages'])),
                    self::field($pl ? 'Frameworki' : 'Frameworks', $n($t['frameworks'])),
                    self::field('Open source', $n($oss['contributed']['repos']) . ($pl ? ' repo · ★ ' : ' repos · ★ ') . $l->compact($oss['contributed']['stars'])),
                    self::field($pl ? 'Mediana commita' : 'Median commit', $n($snapshot['sizes']['median']) . ($pl ? ' linii' : ' lines')),
                    self::field($pl ? 'Od' : 'Since', (string) $t['firstYear']),
                ];
                $top = implode(', ', array_map(static fn (array $lang): string => $lang['name'] . ' ' . $l->percent($lang['share']), array_slice($snapshot['languages'], 0, 5)));
                return ['embeds' => [['title' => $pl ? 'Podsumowanie' : 'Summary', 'description' => $top, 'color' => self::COLOR, 'fields' => $fields, 'footer' => $footer]]];

            case 'loc':
                $range = in_array($options['range'] ?? 'all', Views::RANGES, true) ? (string) ($options['range'] ?? 'all') : 'all';
                $loc = Views::loc($snapshot, $range === 'all' ? 'year' : 'month', $range, $l);
                $lines = [];
                foreach (array_slice($loc['series'], -12) as $row) {
                    $lines[] = '`' . str_pad($row['period'], 7) . '` +' . $n($row['linesAdded']) . ' / -' . $n($row['linesRemoved']);
                }
                return ['embeds' => [[
                    'title' => ($pl ? 'Linie kodu' : 'Lines of code') . ' · ' . $range,
                    'color' => self::COLOR,
                    'description' => implode("\n", $lines),
                    'fields' => [
                        self::field($pl ? 'Dodane' : 'Added', $n($loc['totals']['linesAdded'])),
                        self::field($pl ? 'Usunięte' : 'Removed', $n($loc['totals']['linesRemoved'])),
                        self::field($pl ? 'Netto' : 'Net', $n($loc['totals']['linesNet'])),
                        self::field($pl ? 'Kod żyjący dziś' : 'Living code today', $n($loc['totals']['allTime']['codeLinesLive'])),
                        self::field($pl ? 'Znaki kodu' : 'Characters of code', $n($loc['totals']['allTime']['charsOfCode'])),
                        self::field($pl ? 'Commity sprzątające' : 'Clean-up commits', $n($loc['refactors']['commits'])),
                    ],
                    'footer' => $footer,
                ]]];

            case 'commits':
                $group = in_array($options['group'] ?? 'year', ['year', 'month', 'week', 'hour', 'weekday'], true) ? (string) ($options['group'] ?? 'year') : 'year';
                $range = in_array($options['range'] ?? 'all', Views::RANGES, true) ? (string) ($options['range'] ?? 'all') : 'all';
                $view = Views::commits($snapshot, $group, $range, $l);
                $rows = $view['series'];
                $max = max(1, ...array_map(static fn (array $r): int => $r['commits'], $rows ?: [['commits' => 1]]));
                $lines = [];
                foreach (in_array($group, ['hour', 'weekday'], true) ? $rows : array_slice($rows, -15) as $row) {
                    $label = match ($group) {
                        'hour' => sprintf('%02d:00', $row['hour']),
                        'weekday' => $row['name'],
                        default => $row['period'],
                    };
                    $lines[] = '`' . str_pad((string) $label, 12) . '` ' . str_repeat('█', (int) round($row['commits'] / $max * 14)) . ' ' . $n($row['commits']);
                }
                return ['embeds' => [['title' => ($pl ? 'Commity' : 'Commits') . " · {$group} · {$range}", 'color' => self::COLOR, 'description' => substr(implode("\n", $lines), 0, 4000), 'fields' => [self::field($pl ? 'Razem' : 'Total', $n($view['total']))], 'footer' => $footer]]];

            case 'langs':
                $limit = max(1, min(25, (int) ($options['limit'] ?? 10)));
                $lines = [];
                foreach (array_slice($snapshot['languages'], 0, $limit) as $index => $lang) {
                    $lines[] = '**' . ($index + 1) . '. ' . $lang['name'] . '** ' . $l->percent($lang['share']) . ' · ' . $n($lang['linesAdded']) . ($pl ? ' linii · od ' : ' lines · since ') . $lang['firstYear'];
                }
                return ['embeds' => [['title' => $pl ? 'Języki' : 'Languages', 'color' => self::COLOR, 'description' => implode("\n", $lines), 'footer' => $footer]]];

            case 'oss':
                $oss = $snapshot['opensource'];
                $lines = [];
                foreach (array_slice(array_values(array_filter($oss['projects'], static fn (array $p): bool => $p['merged'] > 0)), 0, 10) as $project) {
                    $lines[] = '[' . $project['name'] . '](' . $project['url'] . ') ★ ' . $l->compact($project['stars']) . ' · ' . $project['merged'] . ' merged';
                }
                return ['embeds' => [[
                    'title' => 'Open source',
                    'color' => self::COLOR,
                    'description' => implode("\n", $lines),
                    'fields' => [
                        self::field($pl ? 'PR zmergowane' : 'Merged PRs', $n($oss['pullRequests']['merged'] ?? 0)),
                        self::field($pl ? 'PR w review' : 'PRs in review', $n($oss['pullRequests']['open'] ?? 0)),
                        self::field($pl ? 'Projekty' : 'Projects', $n($oss['contributed']['repos'])),
                        self::field($pl ? 'Gwiazdki projektów' : 'Project stars', $n($oss['contributed']['stars'])),
                        self::field($pl ? 'Linie przyjęte' : 'Lines merged', $n($oss['contributed']['linesAdded'])),
                        self::field($pl ? 'Mediana do merge' : 'Median time to merge', $n($oss['contributed']['medianHoursToMerge']) . ' h'),
                    ],
                    'footer' => $footer,
                ]]];

            case 'repo':
                $repo = Views::repo($snapshot, (string) ($options['name'] ?? ''), $public);
                if ($repo === null) {
                    return ['content' => $pl ? 'Nie znalazłem takiego repozytorium.' : 'Repository not found.', 'ephemeral' => true];
                }
                $languages = implode(', ', array_map(static fn (array $lang): string => $lang['name'] . ' ' . $l->percent($lang['share']), array_slice($repo['languages'], 0, 5)));
                return ['embeds' => [[
                    'title' => $repo['name'],
                    'description' => trim(($repo['description'] ?? '') . "\n" . $languages),
                    'color' => self::COLOR,
                    'fields' => [
                        self::field($pl ? 'Commity' : 'Commits', $n($repo['commits'])),
                        self::field($pl ? 'Linie' : 'Lines', '+' . $n($repo['linesAdded']) . ' / -' . $n($repo['linesRemoved'])),
                        self::field($pl ? 'Kod żyjący' : 'Living code', $repo['codeLines'] === null ? '-' : $n($repo['codeLines'])),
                        self::field($pl ? 'Pierwszy commit' : 'First commit', substr((string) $repo['firstCommit'], 0, 10)),
                        self::field($pl ? 'Ostatni commit' : 'Last commit', substr((string) $repo['lastCommit'], 0, 10)),
                        self::field($pl ? 'Status' : 'Status', $l->get('status.' . $repo['status'])),
                        self::field($pl ? 'Testy' : 'Tests', $repo['testLinesAdded'] + $repo['codeLinesAdded'] > 0 ? $l->percent($repo['testLinesAdded'] / ($repo['testLinesAdded'] + $repo['codeLinesAdded'])) : '-'),
                        self::field('Frameworks', $repo['frameworks'] === [] ? '-' : implode(', ', array_slice($repo['frameworks'], 0, 8))),
                    ],
                    'footer' => $footer,
                ]]];

            case 'compare':
                $period = ($options['period'] ?? 'week') === 'month' ? 'month' : 'week';
                $view = Views::compare($snapshot, $period, $l);
                $fields = [];
                foreach (['commits' => $pl ? 'Commity' : 'Commits', 'linesAdded' => $pl ? 'Linie dodane' : 'Lines added', 'linesRemoved' => $pl ? 'Linie usunięte' : 'Lines removed', 'activeDays' => $pl ? 'Dni z pracą' : 'Active days'] as $key => $label) {
                    $delta = $view['vsPreviousToDate'][$key];
                    $fields[] = self::field($label, $n($view['current'][$key]) . ' · ' . ($pl ? 'wtedy ' : 'then ') . $n($view['previousToDate'][$key]) . ($delta['percent'] === null ? '' : ' (' . ($delta['percent'] > 0 ? '+' : '') . $delta['percent'] . '%)'));
                }
                $title = $pl ? ($period === 'week' ? 'Ten tydzień wobec poprzedniego' : 'Ten miesiąc wobec poprzedniego') : ($period === 'week' ? 'This week vs last week' : 'This month vs last month');
                return ['embeds' => [['title' => $title, 'description' => $view['current']['from'] . ' → ' . $view['current']['to'] . ' · ' . $view['previousToDate']['from'] . ' → ' . $view['previousToDate']['to'], 'color' => self::COLOR, 'fields' => $fields, 'footer' => $footer]]];

            case 'frameworks':
                $lines = [];
                foreach (array_slice($snapshot['frameworks'], 0, 25) as $framework) {
                    $lines[] = '**' . $framework['name'] . '** · ' . $framework['repos'] . ' repo';
                }
                return ['embeds' => [['title' => $pl ? 'Frameworki i narzędzia' : 'Frameworks and tools', 'color' => self::COLOR, 'description' => implode("\n", $lines) ?: $l->get('frameworks.empty'), 'footer' => $footer]]];

            case 'sizes':
                $sizes = Views::sizes($snapshot, $public, $l);
                $lines = [];
                foreach ($sizes['buckets'] as $bucket) {
                    $lines[] = '`' . str_pad($bucket['label'], 18) . '` ' . str_repeat('█', (int) round($bucket['share'] * 30)) . ' ' . $l->percent($bucket['share']);
                }
                return ['embeds' => [['title' => $pl ? 'Rozmiar commitów' : 'Commit size', 'color' => self::COLOR, 'description' => implode("\n", $lines), 'fields' => [
                    self::field($pl ? 'Mediana' : 'Median', $n($sizes['median'])),
                    self::field('p90', $n($sizes['p90'])),
                    self::field($pl ? 'Średnia' : 'Mean', $n($sizes['mean'])),
                    self::field($pl ? 'Sprzątanie' : 'Clean-ups', $l->percent($sizes['refactors']['share'])),
                ], 'footer' => $footer]]];

            case 'tests':
                $tests = Views::tests($snapshot, $public);
                $lines = [];
                foreach (array_slice($tests['byLanguage'], 0, 10) as $row) {
                    $lines[] = '**' . $row['name'] . '** ' . $l->percent($row['ratio']);
                }
                return ['embeds' => [['title' => $pl ? 'Testy wobec kodu' : 'Tests vs code', 'color' => self::COLOR, 'description' => implode("\n", $lines), 'fields' => [
                    self::field($pl ? 'Udział testów' : 'Test share', $l->percent($tests['ratio'])),
                    self::field($pl ? 'Linie testów' : 'Test lines', $n($tests['testLinesAdded'])),
                    self::field($pl ? 'Repo z testami' : 'Repos with tests', $n($tests['reposWithTests'])),
                ], 'footer' => $footer]]];
        }
        return ['content' => $pl ? 'Nieznana komenda.' : 'Unknown command.', 'ephemeral' => true];
    }

    private function help(Labels $l, bool $owner): array
    {
        $pl = $l->lang === 'pl';
        $lines = [];
        foreach (Commands::definitions() as $command) {
            $lines[] = '`/' . $command['name'] . '` ' . ($pl ? $command['description_localizations']['pl'] : $command['description']);
        }
        $lines[] = '';
        $lines[] = $owner
            ? ($pl ? 'Widzisz pełne dane, łącznie z nazwami prywatnych repozytoriów.' : 'You see full data, including private repository names.')
            : ($pl ? 'Widzisz dane publiczne.' : 'You see public data.');
        $lines[] = 'API: ' . rtrim($this->config->string('publicBaseUrl'), '/') . '/v2/public';
        return ['embeds' => [['title' => 'ghapi', 'description' => implode("\n", $lines), 'color' => self::COLOR]], 'ephemeral' => true];
    }

    private static function field(string $name, string $value): array
    {
        return ['name' => $name, 'value' => $value === '' ? '-' : $value, 'inline' => true];
    }
}
