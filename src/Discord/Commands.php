<?php

declare(strict_types=1);

namespace Ghapi\Discord;

use Ghapi\Support\Http;
use Ghapi\Support\Json;

final class Commands
{
    private const STRING = 3;
    private const INTEGER = 4;
    private const BOOLEAN = 5;

    public static function definitions(): array
    {
        $owner = ['type' => self::STRING, 'name' => 'owner', 'description' => 'Limit to one account or organization', 'description_localizations' => ['pl' => 'Tylko jedno konto albo organizacja'], 'required' => false, 'autocomplete' => true];
        $range = ['type' => self::STRING, 'name' => 'range', 'description' => 'Time range', 'description_localizations' => ['pl' => 'Zakres czasu'], 'required' => false, 'choices' => [
            ['name' => 'all time', 'name_localizations' => ['pl' => 'od zawsze'], 'value' => 'all'],
            ['name' => 'last 365 days', 'name_localizations' => ['pl' => 'ostatnie 365 dni'], 'value' => '365d'],
            ['name' => 'last 90 days', 'name_localizations' => ['pl' => 'ostatnie 90 dni'], 'value' => '90d'],
            ['name' => 'last 30 days', 'name_localizations' => ['pl' => 'ostatnie 30 dni'], 'value' => '30d'],
        ]];
        $hidden = ['type' => self::BOOLEAN, 'name' => 'hidden', 'description' => 'Show the answer only to you', 'description_localizations' => ['pl' => 'Pokaż odpowiedź tylko Tobie'], 'required' => false];
        $command = static fn (string $name, string $en, string $pl, array $options = []): array => [
            'name' => $name,
            'type' => 1,
            'description' => $en,
            'description_localizations' => ['pl' => $pl],
            'options' => array_merge($options, [$hidden]),
            'integration_types' => [1],
            'contexts' => [0, 1, 2],
        ];
        return [
            $command('help', 'What this app can answer', 'Co ta aplikacja potrafi', []),
            $command('summary', 'All the numbers in one place', 'Wszystkie liczby w jednym miejscu', [$owner]),
            $command('loc', 'Lines added, removed and living code', 'Linie dodane, usunięte i kod żyjący', [$range, $owner]),
            $command('commits', 'Commit counts by period or hour', 'Commity według okresu albo godziny', [
                ['type' => self::STRING, 'name' => 'group', 'description' => 'How to group commits', 'description_localizations' => ['pl' => 'Jak grupować commity'], 'required' => false, 'choices' => [
                    ['name' => 'year', 'name_localizations' => ['pl' => 'rok'], 'value' => 'year'],
                    ['name' => 'month', 'name_localizations' => ['pl' => 'miesiąc'], 'value' => 'month'],
                    ['name' => 'week', 'name_localizations' => ['pl' => 'tydzień'], 'value' => 'week'],
                    ['name' => 'hour of day', 'name_localizations' => ['pl' => 'godzina'], 'value' => 'hour'],
                    ['name' => 'weekday', 'name_localizations' => ['pl' => 'dzień tygodnia'], 'value' => 'weekday'],
                ]],
                $range,
                $owner,
            ]),
            $command('langs', 'Languages by lines written', 'Języki według napisanych linii', [
                ['type' => self::INTEGER, 'name' => 'limit', 'description' => 'How many languages', 'description_localizations' => ['pl' => 'Ile języków'], 'required' => false, 'min_value' => 1, 'max_value' => 25],
                $owner,
            ]),
            $command('oss', 'Open source contributions', 'Kontrybucje open source', []),
            $command('repo', 'Stats of one repository', 'Statystyki jednego repozytorium', [
                ['type' => self::STRING, 'name' => 'name', 'description' => 'Repository', 'description_localizations' => ['pl' => 'Repozytorium'], 'required' => true, 'autocomplete' => true],
            ]),
            $command('compare', 'This week or month against the previous one', 'Ten tydzień albo miesiąc wobec poprzedniego', [
                ['type' => self::STRING, 'name' => 'period', 'description' => 'Period', 'description_localizations' => ['pl' => 'Okres'], 'required' => false, 'choices' => [
                    ['name' => 'week', 'name_localizations' => ['pl' => 'tydzień'], 'value' => 'week'],
                    ['name' => 'month', 'name_localizations' => ['pl' => 'miesiąc'], 'value' => 'month'],
                ]],
                $owner,
            ]),
            $command('frameworks', 'Frameworks detected from manifests', 'Frameworki wykryte z manifestów', [$owner]),
            $command('sizes', 'How big commits are', 'Jak duże są commity', [$owner]),
            $command('tests', 'Test code against the rest', 'Kod testów wobec reszty', [$owner]),
        ];
    }

    public static function register(string $applicationId, string $botToken): array
    {
        $response = Http::request(
            'PUT',
            'https://discord.com/api/v10/applications/' . rawurlencode($applicationId) . '/commands',
            ['Authorization' => 'Bot ' . $botToken, 'Content-Type' => 'application/json', 'User-Agent' => 'DiscordBot (https://ghapi.dualfroz.com, 2)'],
            Json::encode(self::definitions()),
            30,
        );
        $body = $response->json();
        return [
            'ok' => $response->status === 200,
            'status' => $response->status,
            'commands' => is_array($body) && array_is_list($body) ? array_column($body, 'name') : $body,
        ];
    }
}
