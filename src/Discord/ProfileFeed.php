<?php

declare(strict_types=1);

namespace Ghapi\Discord;

use Ghapi\Support\Files;
use Ghapi\Support\Http;
use Ghapi\Support\Json;

final class ProfileFeed
{
    private const STALE_AFTER = 900;
    private const IMAGE_TTL = 86400;

    public function __construct(
        private readonly string $feedFile,
        private readonly string $cacheDir,
    ) {
    }

    public function state(): array
    {
        $feed = Json::readFile($this->feedFile);
        if (!is_array($feed) || !isset($feed['username'])) {
            return self::absent();
        }
        $stale = time() - (int) ($feed['updatedAt'] ?? 0) > self::STALE_AFTER;
        $activities = is_array($feed['activities'] ?? null) ? $feed['activities'] : [];
        $spotify = null;
        $playing = null;
        $customStatus = null;
        foreach ($activities as $activity) {
            $type = (string) ($activity['type'] ?? '');
            if ($type === 'spotify' && $spotify === null) {
                $spotify = [
                    'song' => (string) ($activity['title'] ?? ''),
                    'artist' => (string) ($activity['artist'] ?? ''),
                    'album' => (string) ($activity['album'] ?? ''),
                    'art' => $this->image($activity['albumArt'] ?? null),
                    'start' => (int) ($activity['start'] ?? 0),
                    'end' => (int) ($activity['end'] ?? 0),
                ];
            } elseif ($type === 'custom') {
                $text = trim((string) (($activity['emoji'] ?? '') . ' ' . ($activity['name'] ?? '')));
                $customStatus ??= $text !== '' ? $text : null;
            } elseif (in_array($type, ['playing', 'streaming', 'watching', 'competing', 'listening'], true) && $playing === null && ($activity['name'] ?? '') !== '') {
                $playing = ['name' => (string) $activity['name'], 'details' => $activity['details'] ?? null, 'state' => $activity['state'] ?? null];
            }
        }
        if ($stale) {
            $spotify = null;
            $playing = null;
        }
        $spotify = $spotify !== null && $spotify['end'] > 0 && $spotify['end'] < (int) (microtime(true) * 1000) ? null : $spotify;

        return [
            'present' => true,
            'stale' => $stale,
            'status' => $stale ? 'offline' : (string) ($feed['status'] ?? 'offline'),
            'displayName' => (string) ($feed['globalName'] ?? $feed['username']),
            'username' => (string) $feed['username'],
            'clanTag' => $feed['clanTag'] ?? null,
            'clanBadge' => $this->image($feed['clanBadge'] ?? null),
            'avatar' => $this->image($feed['avatar'] ?? null),
            'banner' => $this->image($feed['banner'] ?? null),
            'accentColor' => isset($feed['accentColor']) ? sprintf('#%06x', (int) $feed['accentColor']) : null,
            'publicFlags' => (int) ($feed['publicFlags'] ?? 0),
            'createdAt' => self::snowflakeTime((string) ($feed['userId'] ?? '')),
            'joinedAt' => isset($feed['joinedAt']) ? (strtotime((string) $feed['joinedAt']) ?: null) : null,
            'guildIcon' => $this->image($feed['guildIcon'] ?? null),
            'spotify' => $spotify,
            'playing' => $spotify === null ? $playing : null,
            'customStatus' => $customStatus,
            'fetchedAt' => (int) ($feed['updatedAt'] ?? 0),
        ];
    }

    public static function snowflakeTime(string $id): ?int
    {
        if (!ctype_digit($id) || strlen($id) < 15) {
            return null;
        }
        return intdiv(intdiv((int) $id, 4194304) + 1420070400000, 1000);
    }

    public static function absent(): array
    {
        return [
            'present' => false, 'stale' => false, 'status' => 'offline', 'displayName' => 'DualFroz', 'username' => 'dualfroz',
            'clanTag' => null, 'clanBadge' => null, 'avatar' => null, 'banner' => null, 'accentColor' => null, 'spotify' => null,
            'playing' => null, 'customStatus' => null, 'fetchedAt' => 0,
        ];
    }

    private function image(?string $url): ?string
    {
        if ($url === null || $url === '' || !preg_match('#^https://(cdn\.discordapp\.com|media\.discordapp\.net|i\.scdn\.co)/#', $url)) {
            return null;
        }
        $url = str_replace('/ab67616d0000b273', '/ab67616d00001e02', $url);
        $url = preg_replace('#([?&]size=)\d+#', '${1}' . (str_contains($url, '/banners/') ? '512' : (str_contains($url, '/icons/') || str_contains($url, '/guild-tag-badges/') ? '32' : '128')), $url) ?? $url;
        $file = $this->cacheDir . '/img-' . substr(hash('sha256', $url), 0, 32) . '.txt';
        if (is_file($file)) {
            $age = time() - (int) filemtime($file);
            $cached = (string) file_get_contents($file);
            if ($cached !== '' && $age < self::IMAGE_TTL) {
                return $cached;
            }
            if ($cached === '' && $age < 600) {
                return null;
            }
        }
        if (random_int(1, 50) === 1) {
            foreach (glob($this->cacheDir . '/img-*.txt') ?: [] as $old) {
                if (time() - (int) filemtime($old) > 2 * self::IMAGE_TTL) {
                    @unlink($old);
                }
            }
        }
        $response = Http::request('GET', $url, ['User-Agent' => 'ghapi-discord-card'], null, 6);
        $uri = '';
        if ($response->status === 200 && strlen($response->body) <= 1_500_000) {
            $type = explode(';', (string) ($response->header('content-type') ?? 'image/png'))[0];
            $type = str_starts_with($type, 'image/') ? $type : 'image/png';
            $uri = 'data:' . $type . ';base64,' . base64_encode($response->body);
        }
        try {
            Files::ensureDir($this->cacheDir, 0750);
            Files::writeAtomic($file, $uri, 0640);
        } catch (\Throwable) {
        }
        return $uri === '' ? null : $uri;
    }
}
