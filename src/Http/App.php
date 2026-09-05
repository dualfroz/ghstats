<?php

declare(strict_types=1);

namespace Ghapi\Http;

use Ghapi\Cards\Cards;
use Ghapi\Cards\Theme;
use Ghapi\Config;
use Ghapi\Discord\Interactions;
use Ghapi\Discord\ProfileFeed;
use Ghapi\Discord\ProfileCard;
use Ghapi\Support\Labels;

final class App
{
    private const LEGACY_CARDS = ['stats', 'top-langs', 'streak', 'activity', 'heatmap', 'loc', 'by-year', 'top-repos', 'punchcard', 'achievements'];

    private Snapshots $snapshots;

    public function __construct(private readonly Config $config, private readonly string $rootDir)
    {
        $this->snapshots = new Snapshots($config->string('snapshotDir'));
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->route($request);
        } catch (\Throwable $failure) {
            error_log('ghapi: ' . $failure->getMessage() . ' @ ' . $failure->getFile() . ':' . $failure->getLine());
            $response = Response::error(500, 'internal', 'Something went wrong');
        }
        return $this->finish($request, $response);
    }

    private function route(Request $request): Response
    {
        $path = $request->path;
        if ($request->method === 'OPTIONS') {
            return new Response(204, ['Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS', 'Access-Control-Allow-Headers' => 'Authorization, If-None-Match', 'Access-Control-Max-Age' => '86400'], '');
        }
        if ($path === '/v2/discord/interactions') {
            return $request->method === 'POST'
                ? (new Interactions($this->config, $this->snapshots))->handle($request)
                : Response::error(405, 'method_not_allowed', 'Use POST');
        }
        if (!in_array($request->method, ['GET', 'HEAD'], true)) {
            return Response::error(405, 'method_not_allowed', 'Only GET is supported');
        }

        if ($path === '/' || $path === '/v2' || $path === '/v2/public') {
            return $this->cached($request, 'index', true, fn (): Response => Response::json($this->describe($path !== '/v2/public'), 200, true));
        }
        if ($path === '/v2/health' || $path === '/healthz') {
            return $this->health();
        }
        if ($path === '/data/stats') {
            return $this->cached($request, 'compat', true, fn (): Response => $this->withSnapshot($request, true, fn (array $s): Response => Response::json(Compat::stats($s))));
        }
        if (in_array(ltrim($path, '/'), self::LEGACY_CARDS, true)) {
            return $this->card($request, ltrim($path, '/'), true);
        }
        if ($path === '/discord' || $path === '/v2/public/cards/discord' || $path === '/v2/public/cards/discord.svg') {
            return $this->discordCard($request);
        }

        if (preg_match('#^/v2/(public/)?(.+)$#', $path, $match)) {
            $public = $match[1] !== '';
            $endpoint = $match[2];
            if (!$public && !$this->authorized($request)) {
                return Response::error(401, 'unauthorized', 'This endpoint needs an API key: Authorization: Bearer <key>. Public data lives under /v2/public.')
                    ->withHeader('WWW-Authenticate', 'Bearer realm="ghapi"');
            }
            if (preg_match('#^cards/([a-z-]+?)(\.svg)?$#', $endpoint, $card)) {
                return $this->card($request, $card[1], $public);
            }
            return $this->cached($request, $endpoint, $public, fn (): Response => $this->withSnapshot($request, $public, fn (array $s): ?Response => $this->endpoint($endpoint, $s, $public, $request)));
        }
        return Response::error(404, 'not_found', 'No such endpoint. See /v2');
    }

    private function endpoint(string $endpoint, array $s, bool $public, Request $request): ?Response
    {
        $labels = Labels::of($request->lang());
        $pretty = $request->flag('pretty');
        $range = $request->choice('range', Views::RANGES, 'all');
        $from = $request->string('from') ?: null;
        $to = $request->string('to') ?: null;
        $data = match ($endpoint) {
            'summary' => Views::summary($s, $public, $labels),
            'commits' => Views::commits($s, $request->choice('group', Views::GROUPS, 'year'), $range, $labels, $from, $to),
            'commits/hours' => Views::commits($s, 'hour', $range, $labels),
            'commits/weekdays' => Views::commits($s, 'weekday', $range, $labels),
            'commits/punchcard' => Views::commits($s, 'punchcard', $range, $labels),
            'commits/size', 'commits/sizes' => Views::sizes($s, $public, $labels),
            'streak' => ['scope' => $s['scope'], 'generatedAt' => $s['generatedAt']] + $s['streak'],
            'loc' => Views::loc($s, $request->choice('group', ['year', 'month', 'week', 'day'], 'year'), $range, $labels, $from, $to),
            'languages' => Views::languages($s, $request->int('limit', 100, 1, 100)),
            'frameworks' => Views::frameworks($s, $public, $request->string('ecosystem')),
            'tests' => Views::tests($s, $public),
            'compare' => Views::compare($s, $request->choice('period', ['week', 'month'], 'week'), $labels),
            'opensource' => Views::openSource($s, $request->int('limit', 50, 1, 200)),
            'repos' => Views::repos($s, $public, $request, $labels),
            'lifetime' => ['scope' => $s['scope'], 'generatedAt' => $s['generatedAt']] + Views::lifetime($s, $labels),
            'meta' => $public ? null : ['generatedAt' => $s['generatedAt'], 'collector' => $this->snapshots->index()['collector'] ?? null, 'scopes' => $this->snapshots->index()['scopes'] ?? []],
            default => null,
        };
        if ($data === null && str_starts_with($endpoint, 'repos/')) {
            $data = Views::repo($s, substr($endpoint, 6), $public);
        }
        return $data === null ? null : Response::json($data, 200, $pretty);
    }

    private function withSnapshot(Request $request, bool $public, callable $build): Response
    {
        $scope = $this->snapshots->scopeFor($request->string('owner') ?: null);
        if ($scope === null) {
            return Response::error(404, 'unknown_owner', 'Unknown owner. Known: ' . implode(', ', $this->snapshots->owners()));
        }
        $snapshot = $this->snapshots->load($scope);
        if ($snapshot === null) {
            return Response::error(503, 'no_data', 'The collector has not produced data yet');
        }
        if ($public) {
            $snapshot = Views::strip($snapshot) + ['repos' => array_values(array_filter($snapshot['repos'] ?? [], static fn (array $r): bool => !$r['private']))];
        }
        return $build($snapshot) ?? Response::error(404, 'not_found', 'No such endpoint or repository. See /v2');
    }

    private function cached(Request $request, string $key, bool $public, callable $build): Response
    {
        $query = $request->query;
        unset($query['pretty']);
        ksort($query);
        $etag = '"' . substr(hash('sha256', $this->snapshots->version() . '|' . $key . '|' . ($public ? 'p' : 'k') . '|' . http_build_query($query)), 0, 32) . '"';
        $cache = ($public ? 'public' : 'private') . ', max-age=' . $this->config->int('cacheSeconds');
        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null && in_array($etag, array_map('trim', explode(',', str_replace('W/', '', $ifNoneMatch))), true)) {
            return new Response(304, ['ETag' => $etag, 'Cache-Control' => $cache], '');
        }
        $response = $build();
        if ($response->status === 200) {
            $response->headers['ETag'] = $etag;
            $response->headers['Cache-Control'] = $cache;
        }
        return $response;
    }

    private function card(Request $request, string $name, bool $public): Response
    {
        if (!in_array($name, Cards::NAMES, true)) {
            return $name === 'discord' ? $this->discordCard($request) : Response::error(404, 'unknown_card', 'Cards: ' . implode(', ', Cards::NAMES) . ', discord');
        }
        return $this->cached($request, 'card-' . $name, $public, function () use ($request, $name, $public): Response {
            return $this->withSnapshot($request, $public, function (array $s) use ($request, $name, $public): Response {
                $cards = new Cards(
                    $s,
                    Theme::resolve($request->string('theme') ?: null),
                    Labels::of($request->lang()),
                    $request->flag('hide_border'),
                    $request->choice('range', ['all', 'year'], 'year') === 'all',
                    $request->string('name') !== '' ? substr($request->string('name'), 0, 40) : null,
                    $public,
                    array_map('intval', $this->config->list('heatmapThresholds')),
                );
                return Response::svg((string) $cards->render($name));
            });
        });
    }

    private function discordCard(Request $request): Response
    {
        $feed = new ProfileFeed($this->config->string('discordProfileFile'), $this->config->string('cacheDir') ?: sys_get_temp_dir() . '/ghapi-cache');
        $state = $feed->state();
        $hideBorder = $request->flag('hide_border');
        $token = hash('sha256', json_encode([$state['status'], $state['displayName'], $state['customStatus'], $state['spotify']['song'] ?? null, $state['playing'] ?? null, $state['stale'], $hideBorder, $state['avatar'] !== null]) ?: '');
        $etag = 'W/"discord-' . substr($token, 0, 16) . '"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'public, max-age=25'];
        if ($request->header('if-none-match') === $etag) {
            return new Response(304, $headers, '');
        }
        $card = new ProfileCard($state, $this->config->string('discordBanner'), $this->config->string('discordPronouns'), $this->config->string('discordBio'), $this->config->list('discordBadges'), $this->rootDir . '/assets/badges');
        $response = Response::svg($card->render($hideBorder));
        $response->headers += $headers;
        return $response;
    }

    private function authorized(Request $request): bool
    {
        $header = (string) $request->header('authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $match)) {
            return false;
        }
        $hash = hash('sha256', $match[1]);
        foreach ($this->config->list('apiKeys') as $key) {
            if (is_array($key) && isset($key['sha256']) && hash_equals(strtolower((string) $key['sha256']), $hash)) {
                return true;
            }
        }
        return false;
    }

    private function health(): Response
    {
        $index = $this->snapshots->index();
        $generated = $index['generatedAt'] ?? null;
        $age = $generated === null ? null : time() - strtotime((string) $generated);
        $status = $generated === null ? 'no_data' : ($age > 2 * 86400 ? 'stale' : 'ok');
        $response = Response::json([
            'status' => $status,
            'generatedAt' => $generated,
            'ageSeconds' => $age,
            'pendingDetails' => $index['collector']['pendingDetails'] ?? null,
        ], $status === 'no_data' ? 503 : 200);
        $response->headers['Cache-Control'] = 'no-store';
        return $response;
    }

    private function describe(bool $withPrivate): array
    {
        $base = rtrim($this->config->string('publicBaseUrl'), '/');
        $public = [
            'summary' => 'totals, streak, top languages and frameworks, tests, commit size, open source',
            'commits?group=year|month|week|day|hour|weekday|punchcard&range=all|365d|90d|30d&from&to' => 'commit counts',
            'commits/hours' => 'commits by hour of day, author local time',
            'commits/size' => 'commit size distribution, median, p90, largest commits',
            'streak' => 'current and longest streak',
            'loc?group=year|month|week|day&range=' => 'lines added and removed, clean-up commits, file kinds',
            'languages?limit=' => 'languages by lines written, living code, first year',
            'frameworks?ecosystem=' => 'frameworks detected from manifests',
            'tests' => 'test code against the rest',
            'compare?period=week|month' => 'current period against the previous one',
            'opensource?limit=' => 'pull requests to public projects and own public repositories',
            'lifetime' => 'repository activity status',
            'repos' => 'public repositories only',
            'cards/{stats|top-langs|streak|activity|heatmap|loc|by-year|top-repos|punchcard|achievements|opensource|frameworks|hours|discord}.svg?theme=tokyonight|light&lang=en|pl&hide_border&range=all' => 'SVG cards',
        ];
        $out = [
            'name' => 'ghapi',
            'version' => 2,
            'docs' => 'Every endpoint accepts owner=<account> to narrow the data, lang=en|pl for labels and pretty=1. Responses carry ETag and honour If-None-Match.',
            'owners' => $this->snapshots->owners(),
            'public' => array_combine(array_map(static fn (string $k): string => $base . '/v2/public/' . $k, array_keys($public)), array_values($public)),
        ];
        if ($withPrivate) {
            $out['private'] = [
                'auth' => 'Authorization: Bearer <key>',
                'endpoints' => 'the same paths under /v2/ without public/, plus repos?status=&visibility=&language=&sort=commits|lines|recent|stars|lifetime|name, repos/{owner}/{name}, meta',
            ];
        }
        return $out;
    }

    private function finish(Request $request, Response $response): Response
    {
        if (str_starts_with($request->path, '/v2/public') || $request->path === '/data/stats' || in_array(ltrim($request->path, '/'), array_merge(self::LEGACY_CARDS, ['discord', 'healthz']), true) || $request->path === '/v2' || $request->path === '/v2/health') {
            $response->headers['Access-Control-Allow-Origin'] = '*';
        }
        $response->headers['Vary'] = 'Accept-Encoding, Authorization';
        return $response;
    }
}
