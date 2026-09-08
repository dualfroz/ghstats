<?php

declare(strict_types=1);

namespace Ghapi\Collect;

use Ghapi\Support\Http;
use Ghapi\Support\Json;

final class Notifier
{
    public function __construct(
        private readonly string $webhookUrl,
        private readonly string $publicBaseUrl,
    ) {
    }

    public function refreshed(?array $previous, array $snapshot, array $report): void
    {
        $totals = $snapshot['totals'];
        $rows = [
            ['Commity', 'commits'],
            ['Linie dodane', 'linesAdded'],
            ['Linie usunięte', 'linesRemoved'],
            ['Linie kodu', 'lines'],
            ['Repozytoria', 'repos'],
            ['Języki', 'languages'],
        ];
        $lines = [];
        foreach ($rows as [$label, $key]) {
            $value = (int) ($totals[$key] ?? 0);
            $delta = $previous === null ? null : $value - (int) ($previous[$key] ?? 0);
            $lines[] = "**{$label}:** " . self::number($value) . ($delta ? ' (' . ($delta > 0 ? '+' : '') . self::number($delta) . ')' : '');
        }
        $oss = $snapshot['opensource']['contributed'] ?? [];
        $lines[] = '**Open source:** ' . (int) ($oss['repos'] ?? 0) . ' repo, ' . self::number((int) ($oss['stars'] ?? 0)) . ' gwiazdek';
        $details = [];
        $details[] = "nowe commity: {$report['newCommits']}";
        if ($report['purged'] > 0) {
            $details[] = "usunięte z historii: {$report['purged']}";
        }
        $details[] = 'zaległe szczegóły: ' . (int) ($report['pendingDetails'] ?? 0);
        if ($report['errors'] !== []) {
            $details[] = 'błędy: ' . count($report['errors']);
        }
        $payload = [
            'username' => 'ghapi',
            'embeds' => [[
                'title' => 'Statystyki odświeżone',
                'url' => rtrim($this->publicBaseUrl, '/') . '/v2/public/summary',
                'description' => implode("\n", $lines),
                'color' => 0x7aa2f7,
                'footer' => ['text' => implode(' · ', $details)],
                'timestamp' => $snapshot['generatedAt'],
            ]],
            'allowed_mentions' => ['parse' => []],
        ];
        $response = Http::request('POST', $this->webhookUrl, ['Content-Type' => 'application/json'], Json::encode($payload), 20);
        if ($response->status >= 300) {
            throw new \RuntimeException("webhook HTTP {$response->status}");
        }
    }

    private static function number(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }
}
