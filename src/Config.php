<?php

declare(strict_types=1);

namespace Ghapi;

use Ghapi\Support\Json;
use RuntimeException;

final class Config
{
    private const DEFAULTS = [
        'owners' => ['dualfroz', 'VulCodeCom', 'aphrodipl', 'thelunaticstudios', 'Sinlege'],
        'login' => 'dualfroz',
        'name' => 'DualFroz',
        'timezone' => 'Europe/Warsaw',
        'authorEmails' => [
            'me@dualfroz.com',
            'me@dualfroz.com',
            '284081405+dualfroz@users.noreply.github.com',
            '37844753+FoksVHox@users.noreply.github.com',
        ],
        'fullCountRepos' => ['secret'],
        'excludeRepos' => [],
        'languageAliases' => [
            'Tsx' => 'TypeScript',
            'Jsx' => 'JavaScript',
            'C Header' => 'C',
            'C/C++ Header' => 'C',
            'C++ Header' => 'C++',
            'GNU Style Assembly' => 'Assembly',
            'SCSS' => 'Sass',
        ],
        'languageDenylist' => [
            'JSON', 'JSON5', 'YAML', 'TOML', 'Markdown', 'Plain Text', 'Text', 'SVG', 'XML', 'INI', 'Dotenv', 'CSV',
        ],
        'stateDir' => '/srv/ghapi/state',
        'snapshotDir' => '/srv/ghapi/snapshot',
        'tmpDir' => '/srv/ghapi/tmp',
        'cacheDir' => '/srv/ghapi/cache',
        'tokeiBin' => 'tokei',
        'tarBin' => 'tar',
        'refreshHours' => 24,
        'restBudgetReserve' => 300,
        'graphqlBudgetReserve' => 300,
        'maxDetailRequestsPerRun' => 4000,
        'maxTarballBytes' => 1500000000,
        'discordWebhook' => '',
        'discordUserId' => '000000000000000000',
        'discordPublicKey' => '',
        'discordApplicationId' => '',
        'discordBotTokenFile' => '',
        'discordProfileFile' => '/opt/projects/vulcode/bots/discordv2/data/profile/000000000000000000.json',
        'discordBio' => '',
        'discordPronouns' => 'Strange(r)',
        'discordBanner' => '#5865f2,#404eed',
        'discordBadges' => ['nitro', 'boost', 'quest', 'orb', 'legacy'],
        'apiKeys' => [],
        'publicBaseUrl' => 'https://ghapi.dualfroz.com',
        'cacheSeconds' => 1800,
        'heatmapThresholds' => [3, 10, 25, 50],
    ];

    private array $values;

    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function load(?string $path = null): self
    {
        $path ??= getenv('GHAPI_CONFIG') ?: '/etc/ghapi/config.json';
        $values = self::DEFAULTS;
        foreach (array_filter([$path, getenv('GHAPI_CONFIG_EXTRA') ?: null]) as $file) {
            if (!is_file($file)) {
                continue;
            }
            $loaded = Json::readFile($file);
            if (!is_array($loaded)) {
                throw new RuntimeException("Config at {$file} is not a JSON object");
            }
            $values = array_replace($values, $loaded);
        }
        return new self($values);
    }

    public static function fromArray(array $values): self
    {
        return new self(array_replace(self::DEFAULTS, $values));
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function string(string $key): string
    {
        return (string) ($this->values[$key] ?? '');
    }

    public function int(string $key): int
    {
        return (int) ($this->values[$key] ?? 0);
    }

    public function list(string $key): array
    {
        $value = $this->values[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    public function authorEmails(): array
    {
        return array_map('strtolower', $this->list('authorEmails'));
    }

    public function isFullCount(string $nameWithOwner): bool
    {
        $lower = strtolower($nameWithOwner);
        $short = substr($lower, (int) strpos($lower, '/') + 1);
        foreach ($this->list('fullCountRepos') as $entry) {
            $entry = strtolower((string) $entry);
            if ($entry === $lower || $entry === $short) {
                return true;
            }
        }
        return false;
    }

    public function canonicalLanguage(string $language): ?string
    {
        $name = $language;
        foreach ($this->list('languageAliases') as $alias => $target) {
            if (strcasecmp((string) $alias, $language) === 0) {
                $name = (string) $target;
                break;
            }
        }
        foreach ($this->list('languageDenylist') as $denied) {
            if (strcasecmp((string) $denied, $name) === 0) {
                return null;
            }
        }
        return $name;
    }

    public function isExcluded(string $nameWithOwner): bool
    {
        $lower = strtolower($nameWithOwner);
        foreach ($this->list('excludeRepos') as $entry) {
            if (strtolower((string) $entry) === $lower) {
                return true;
            }
        }
        return false;
    }
}
