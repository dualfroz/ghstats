<?php

declare(strict_types=1);

namespace Ghapi\Discord;

use Ghapi\Support\Files;
use Ghapi\Support\Http;
use Ghapi\Support\Json;

final class Lanyard
{
    private const TTL = 25;
    private const STALE_AFTER = 600;

    public function __construct(
        private readonly string $userId,
        private readonly string $cacheDir,
        private readonly string $bannerUrl,
    ) {
    }

    public function state(): array
    {
        $file = $this->cacheDir . '/lanyard-' . preg_replace('/\D/', '', $this->userId) . '.json';
        $cached = Json::readFile($file);
        if (is_array($cached) && time() - (int) ($cached['fetchedAt'] ?? 0) < self::TTL) {
            return $cached;
        }
        $lock = @fopen($file . '.lock', 'c');
        if ($lock !== false && !flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return is_array($cached) ? $cached : self::absent();
        }
        try {
            $fresh = $this->fetch($cached);
            Files::ensureDir($this->cacheDir, 0750);
            Json::writeFile($file, $fresh);
            return $fresh;
        } catch (\Throwable) {
            if (is_array($cached)) {
                $cached['stale'] = time() - (int) ($cached['fetchedAt'] ?? 0) > self::STALE_AFTER;
                return $cached;
            }
            return self::absent();
        } finally {
            if ($lock !== false) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public static function absent(): array
    {
        return [
            'present' => false, 'stale' => false, 'status' => 'offline', 'displayName' => 'DualFroz', 'username' => 'dualfroz',
            'clanTag' => null, 'clanBadge' => null, 'avatar' => null, 'banner' => null, 'spotify' => null, 'playing' => null,
            'customStatus' => null, 'fetchedAt' => 0,
        ];
    }

    private function fetch(?array $previous): array
    {
        $response = Http::request('GET', 'https://api.lanyard.rest/v1/users/' . rawurlencode($this->userId), ['User-Agent' => 'ghapi-discord-card'], null, 6);
        $root = $response->status === 200 ? $response->json() : null;
        if (!is_array($root) || !($root['success'] ?? false)) {
            throw new \RuntimeException('lanyard unavailable');
        }
        $data = $root['data'];
        $user = $data['discord_user'] ?? [];
        $username = (string) ($user['username'] ?? 'dualfroz');
        $images = $previous['images'] ?? [];
        $image = function (?string $url) use (&$images): ?string {
            if ($url === null || $url === '') {
                return null;
            }
            if (isset($images[$url])) {
                return $images[$url];
            }
            $response = Http::request('GET', $url, ['User-Agent' => 'ghapi-discord-card'], null, 6);
            if ($response->status !== 200 || strlen($response->body) > 1_500_000) {
                return null;
            }
            $type = (string) ($response->header('content-type') ?? 'image/png');
            $type = str_starts_with($type, 'image/') ? explode(';', $type)[0] : 'image/png';
            return $images[$url] = 'data:' . $type . ';base64,' . base64_encode($response->body);
        };

        $userId = (string) ($user['id'] ?? $this->userId);
        $guild = $user['primary_guild'] ?? [];
        $clanTag = $guild['tag'] ?? null;
        $spotify = null;
        if (($data['listening_to_spotify'] ?? false) && isset($data['spotify'])) {
            $spotify = [
                'song' => (string) ($data['spotify']['song'] ?? ''),
                'artist' => (string) ($data['spotify']['artist'] ?? ''),
                'album' => (string) ($data['spotify']['album'] ?? ''),
                'art' => $image($data['spotify']['album_art_url'] ?? null),
                'start' => (int) ($data['spotify']['timestamps']['start'] ?? 0),
                'end' => (int) ($data['spotify']['timestamps']['end'] ?? 0),
            ];
        }
        $playing = null;
        $customStatus = null;
        foreach ($data['activities'] ?? [] as $activity) {
            $type = (int) ($activity['type'] ?? 0);
            if ($type === 4) {
                $customStatus ??= ($activity['state'] ?? '') !== '' ? (string) $activity['state'] : null;
                continue;
            }
            if ($type === 2 || $spotify !== null || $playing !== null || ($activity['name'] ?? '') === '') {
                continue;
            }
            $playing = ['name' => (string) $activity['name'], 'details' => $activity['details'] ?? null, 'state' => $activity['state'] ?? null];
        }
        $avatar = isset($user['avatar']) ? $image("https://cdn.discordapp.com/avatars/{$userId}/{$user['avatar']}.png?size=128") : null;
        $clanBadge = ($clanTag !== null && isset($guild['badge'], $guild['identity_guild_id']))
            ? $image("https://cdn.discordapp.com/guild-tag-badges/{$guild['identity_guild_id']}/{$guild['badge']}.png?size=32")
            : null;
        $banner = str_starts_with($this->bannerUrl, 'http') ? $image($this->bannerUrl) : null;
        $used = array_filter([$avatar, $clanBadge, $banner, $spotify['art'] ?? null]);
        $images = array_filter($images, static fn (string $uri): bool => in_array($uri, $used, true));

        return [
            'present' => true,
            'stale' => false,
            'status' => (string) ($data['discord_status'] ?? 'offline'),
            'displayName' => (string) ($user['global_name'] ?? $username),
            'username' => $username,
            'clanTag' => $clanTag,
            'clanBadge' => $clanBadge,
            'avatar' => $avatar,
            'banner' => $banner,
            'spotify' => $spotify,
            'playing' => $playing,
            'customStatus' => $customStatus,
            'fetchedAt' => time(),
            'images' => $images,
        ];
    }
}
