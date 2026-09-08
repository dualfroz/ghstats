<?php

declare(strict_types=1);

namespace Ghapi\Discord;

use Ghapi\Cards\Svg;

final class ProfileCard
{
    private const W = 420;
    private const BANNER_H = 100;
    private const CARD_BG = '#232428';
    private const PANEL_BG = '#111214';
    private const TEXT = '#f2f3f5';
    private const TEXT2 = '#dbdee1';
    private const MUTED = '#b5bac1';
    private const HAIRLINE = 'rgba(255,255,255,0.06)';
    private const SPOTIFY = '#1db954';
    private const AV_CX = 56;
    private const AV_CY = 100;
    private const AV_R = 44;
    private const FLAG_BADGES = [
        64 => 'hypesquad-bravery', 128 => 'hypesquad-brilliance', 256 => 'hypesquad-balance', 512 => 'early-supporter', 4194304 => 'active-developer',
    ];
    private const DISCORD_MARK = 'M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189Z';
    private const LABELS = [
        'pl' => ['about' => 'O MNIE', 'since' => 'CZŁONEK OD', 'spotify' => 'Słucha Spotify', 'by' => 'wykonawca: ', 'on' => 'album: ', 'playing' => 'GRA W', 'cached' => ' (z pamięci)', 'online' => 'Dostępny', 'idle' => 'Zaraz wracam', 'dnd' => 'Nie przeszkadzać', 'offline' => 'Niedostępny', 'months' => ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru']],
        'en' => ['about' => 'ABOUT ME', 'since' => 'MEMBER SINCE', 'spotify' => 'Listening to Spotify', 'by' => 'by ', 'on' => 'on ', 'playing' => 'PLAYING A GAME', 'cached' => ' (cached)', 'online' => 'Online', 'idle' => 'Idle', 'dnd' => 'Do Not Disturb', 'offline' => 'Offline', 'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']],
    ];

    private string $defs = '';

    public function __construct(
        private readonly array $state,
        private readonly string $bannerConfig,
        private readonly string $pronouns,
        private readonly string $bio,
        private readonly array $badges,
        private readonly string $assetsDir,
        private readonly string $lang = 'pl',
    ) {
    }

    public function render(bool $hideBorder): string
    {
        $s = $this->state;
        $body = $this->banner() . $this->avatar();
        $nameY = 176;
        $body .= Svg::text(20, $nameY, self::TEXT, 22, 700, $s['displayName']);
        $body .= $this->clanPill($nameY);
        $usernameY = $nameY + 22;
        $body .= Svg::text(20, $usernameY, self::MUTED, 15, 400, '@' . $s['username']);
        $body .= $this->pronounsText($usernameY, '@' . $s['username']);
        $body .= $this->badgeBox();
        $y = $usernameY + 20;
        $y = $this->bioBlock($body, $y);
        $y = $this->memberSince($body, $y);
        $y = $this->activity($body, $y);
        $height = $y + 16;

        $svg = '<svg width="' . self::W . '" height="' . $height . '" viewBox="0 0 ' . self::W . ' ' . $height . '" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" role="img" aria-label="' . Svg::escape('Discord profile for ' . $s['displayName']) . '">';
        $svg .= '<style>text{font-family:' . Svg::FONT . ';}</style>';
        $svg .= '<defs><clipPath id="dcard"><rect x="0" y="0" width="' . self::W . '" height="' . $height . '" rx="16"/></clipPath>' . $this->defs . '</defs>';
        $svg .= '<rect x="0" y="0" width="' . self::W . '" height="' . $height . '" rx="16" fill="' . self::CARD_BG . '"/>';
        $svg .= $body;
        if (!$hideBorder) {
            $svg .= '<rect x="0.5" y="0.5" width="' . (self::W - 1) . '" height="' . ($height - 1) . '" rx="15.5" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>';
        }
        return $svg . '</svg>';
    }

    private function banner(): string
    {
        if ($this->state['banner'] !== null) {
            return '<image href="' . $this->state['banner'] . '" x="0" y="0" width="' . self::W . '" height="' . self::BANNER_H . '" preserveAspectRatio="xMidYMid slice" clip-path="url(#dcard)"/>';
        }
        if (($this->state['accentColor'] ?? null) !== null) {
            $fill = $this->state['accentColor'];
        } elseif (str_starts_with($this->bannerConfig, '#') && !str_contains($this->bannerConfig, ',')) {
            $fill = $this->bannerConfig;
        } elseif (str_contains($this->bannerConfig, ',') && !str_starts_with($this->bannerConfig, 'http')) {
            [$from, $to] = array_map('trim', explode(',', $this->bannerConfig, 2));
            $fill = $this->gradient($from, $to);
        } else {
            $fill = $this->gradient('#5865f2', '#404eed');
        }
        return '<rect x="0" y="0" width="' . self::W . '" height="' . self::BANNER_H . '" fill="' . Svg::escape($fill) . '" clip-path="url(#dcard)"/>';
    }

    private function gradient(string $from, string $to): string
    {
        $this->defs .= '<linearGradient id="dbanner" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . Svg::escape($from) . '"/><stop offset="1" stop-color="' . Svg::escape($to) . '"/></linearGradient>';
        return 'url(#dbanner)';
    }

    private function badgeBox(): string
    {
        $keys = [];
        foreach (self::FLAG_BADGES as $flag => $key) {
            if (((int) ($this->state['publicFlags'] ?? 0) & $flag) === $flag) {
                $keys[] = $key;
            }
        }
        $keys = array_values(array_unique(array_merge($keys, array_map(static fn ($key): string => strtolower((string) $key), $this->badges))));
        $glyphs = [];
        foreach ($keys as $key) {
            if (!preg_match('/^[a-z0-9-]+$/', $key)) {
                continue;
            }
            foreach (['svg' => 'image/svg+xml', 'png' => 'image/png'] as $extension => $mime) {
                $file = $this->assetsDir . '/' . $key . '.' . $extension;
                if (is_file($file)) {
                    $glyphs[] = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file));
                    break;
                }
            }
        }
        if ($glyphs === []) {
            return '';
        }
        $size = 22;
        $gap = 6;
        $n = count($glyphs);
        $boxW = 16 + $n * $size + ($n - 1) * $gap;
        $boxX = self::W - 16 - $boxW;
        $out = '<rect x="' . $boxX . '" y="114" width="' . $boxW . '" height="' . ($size + 12) . '" rx="11" fill="rgba(0,0,0,0.45)" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>';
        foreach ($glyphs as $i => $uri) {
            $out .= '<image href="' . $uri . '" x="' . ($boxX + 8 + $i * ($size + $gap)) . '" y="120" width="' . $size . '" height="' . $size . '" preserveAspectRatio="xMidYMid meet"/>';
        }
        return $out;
    }

    private function avatar(): string
    {
        $s = $this->state;
        $out = '<circle cx="' . self::AV_CX . '" cy="' . self::AV_CY . '" r="' . (self::AV_R + 6) . '" fill="' . self::CARD_BG . '"/>';
        $out .= '<clipPath id="dav"><circle cx="' . self::AV_CX . '" cy="' . self::AV_CY . '" r="' . self::AV_R . '"/></clipPath>';
        if ($s['avatar'] !== null) {
            $out .= '<image href="' . $s['avatar'] . '" x="' . (self::AV_CX - self::AV_R) . '" y="' . (self::AV_CY - self::AV_R) . '" width="' . (self::AV_R * 2) . '" height="' . (self::AV_R * 2) . '" preserveAspectRatio="xMidYMid slice" clip-path="url(#dav)"/>';
        } else {
            $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $s['username'] ?: $s['displayName']) ?: 'DF', 0, 2));
            $out .= '<circle cx="' . self::AV_CX . '" cy="' . self::AV_CY . '" r="' . self::AV_R . '" fill="#5865f2"/>';
            $out .= Svg::text(self::AV_CX, self::AV_CY + 9, '#ffffff', 30, 700, $initials, 'middle');
        }
        $out .= '<circle cx="' . self::AV_CX . '" cy="' . self::AV_CY . '" r="' . self::AV_R . '" fill="none" stroke="rgba(0,0,0,0.15)" stroke-width="1"/>';
        $dotX = self::AV_CX + 31;
        $dotY = self::AV_CY + 31;
        $out .= '<circle cx="' . $dotX . '" cy="' . $dotY . '" r="13" fill="' . self::CARD_BG . '"/>';
        return $out . '<circle cx="' . $dotX . '" cy="' . $dotY . '" r="8.5" fill="' . self::statusColor($s['status']) . '"/>';
    }

    private function clanPill(int $nameY): string
    {
        $tag = (string) ($this->state['clanTag'] ?? '');
        if (trim($tag) === '') {
            return '';
        }
        $hasBadge = $this->state['clanBadge'] !== null;
        $pillX = 20 + (int) (Svg::length($this->state['displayName']) * 12.3) + 10;
        $pillY = $nameY - 17;
        $pillW = 12 + ($hasBadge ? 18 : 0) + (int) (Svg::length($tag) * 8.2);
        $out = '<rect x="' . $pillX . '" y="' . $pillY . '" width="' . $pillW . '" height="22" rx="6" fill="rgba(255,255,255,0.08)"/>';
        $textX = $pillX + 8;
        if ($hasBadge) {
            $out .= '<image href="' . $this->state['clanBadge'] . '" x="' . ($pillX + 5) . '" y="' . ($pillY + 4) . '" width="14" height="14"/>';
            $textX = $pillX + 23;
        }
        return $out . Svg::text($textX, $pillY + 15, self::TEXT2, 12, 700, $tag);
    }

    private function pronounsText(int $usernameY, string $username): string
    {
        if (trim($this->pronouns) === '') {
            return '';
        }
        $sepX = 20 + (int) (Svg::length($username) * 8.1) + 9;
        return '<circle cx="' . $sepX . '" cy="' . ($usernameY - 4) . '" r="1.6" fill="' . self::MUTED . '"/>'
            . Svg::text($sepX + 8, $usernameY, self::MUTED, 13, 400, $this->pronouns);
    }

    private function bioBlock(string &$body, int $y): int
    {
        if (trim($this->bio) === '') {
            return $y;
        }
        $top = $y + 14;
        $body .= $this->sectionLabel(20, $top, $this->label('about'));
        $lineY = $top + 18;
        foreach (array_slice(explode("\n", wordwrap($this->bio, 60, "\n", true)), 0, 4) as $line) {
            $body .= Svg::text(20, $lineY, self::TEXT2, 13, 400, $line);
            $lineY += 17;
        }
        return $lineY - 11;
    }

    private function activity(string &$body, int $y): int
    {
        $dividerY = $y + 12;
        $body .= '<line x1="20" y1="' . $dividerY . '" x2="' . (self::W - 20) . '" y2="' . $dividerY . '" stroke="' . self::HAIRLINE . '" stroke-width="1"/>';
        $top = $dividerY + 16;
        if ($this->state['spotify'] !== null) {
            return $this->spotifyPanel($body, $this->state['spotify'], $top);
        }
        if ($this->state['playing'] !== null) {
            return $this->playingPanel($body, $this->state['playing'], $top);
        }
        return $this->statusPanel($body, $top);
    }

    private function spotifyPanel(string &$body, array $spotify, int $top): int
    {
        $panX = 16;
        $panW = self::W - 32;
        $body .= $this->panel($panX, $top, $panW, 118);
        $artX = $panX + 14;
        $artY = $top + 14;
        $body .= '<clipPath id="dalbum"><rect x="' . $artX . '" y="' . $artY . '" width="76" height="76" rx="6"/></clipPath>';
        if ($spotify['art'] !== null) {
            $body .= '<image href="' . $spotify['art'] . '" x="' . $artX . '" y="' . $artY . '" width="76" height="76" preserveAspectRatio="xMidYMid slice" clip-path="url(#dalbum)"/>';
        } else {
            $cx = $artX + 38;
            $cy = $artY + 38;
            $body .= '<rect x="' . $artX . '" y="' . $artY . '" width="76" height="76" rx="6" fill="#2b2d31"/>'
                . '<g fill="' . self::MUTED . '"><rect x="' . ($cx + 4) . '" y="' . ($cy - 14) . '" width="3" height="18" rx="1.5"/><circle cx="' . ($cx - 2) . '" cy="' . ($cy + 4) . '" r="5"/><path d="M' . ($cx + 4) . ' ' . ($cy - 14) . ' L' . ($cx + 12) . ' ' . ($cy - 12) . ' L' . ($cx + 12) . ' ' . ($cy - 8) . ' L' . ($cx + 4) . ' ' . ($cy - 10) . ' Z"/></g>';
        }
        $body .= '<rect x="' . $artX . '" y="' . $artY . '" width="76" height="76" rx="6" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>';
        $textX = $artX + 92;
        $gx = $textX + 7;
        $gy = $top + 22;
        $body .= '<g><circle cx="' . $gx . '" cy="' . $gy . '" r="7" fill="' . self::SPOTIFY . '"/>'
            . self::arc($gx, $gy - 1.2, 4.2, 1.6) . self::arc($gx, $gy + 0.9, 3.2, 1.5) . self::arc($gx, $gy + 2.8, 2.2, 1.3) . '</g>';
        $body .= Svg::text($textX + 20, $top + 26, self::SPOTIFY, 12, 700, $this->label('spotify'));
        $body .= Svg::text($textX, $top + 48, self::TEXT, 15, 700, self::cut($spotify['song'], 26));
        $body .= Svg::text($textX, $top + 66, self::MUTED, 12, 400, $this->label('by') . self::cut($spotify['artist'], 30));
        $body .= Svg::text($textX, $top + 82, self::MUTED, 12, 400, $this->label('on') . self::cut($spotify['album'], 30));
        $total = $spotify['end'] - $spotify['start'];
        $elapsed = max(0, min($total, (int) (microtime(true) * 1000) - $spotify['start']));
        $barRight = $panX + $panW - 16;
        $fullW = $barRight - $textX;
        $barY = $top + 96;
        $filled = $total > 0 ? $elapsed / $total * $fullW : 0;
        $remaining = max(0, ($total - $elapsed) / 1000);
        $body .= '<rect x="' . $textX . '" y="' . $barY . '" width="' . $fullW . '" height="5" rx="2.5" fill="rgba(255,255,255,0.14)"/>';
        $body .= '<rect x="' . $textX . '" y="' . $barY . '" width="' . Svg::n($filled) . '" height="5" rx="2.5" fill="' . self::SPOTIFY . '">';
        if ($remaining > 0 && $total > 0) {
            $body .= '<animate attributeName="width" from="' . Svg::n($filled) . '" to="' . $fullW . '" dur="' . Svg::n($remaining) . 's" fill="freeze"/>';
        }
        $body .= '</rect>';
        $body .= Svg::text($textX, $barY + 18, self::MUTED, 10, 400, self::clock($elapsed));
        $body .= Svg::text($barRight, $barY + 18, self::MUTED, 10, 400, self::clock($total), 'end');
        return $top + 122;
    }

    private function playingPanel(string &$body, array $playing, int $top): int
    {
        $hasDetails = trim((string) $playing['details']) !== '';
        $hasState = trim((string) $playing['state']) !== '';
        $panH = 44 + ($hasDetails ? 18 : 0) + ($hasState ? 18 : 0);
        $body .= $this->panel(16, $top, self::W - 32, $panH);
        $tileX = 30;
        $tileY = $top + intdiv($panH - 52, 2);
        $cx = $tileX + 26;
        $cy = $tileY + 26;
        $body .= '<rect x="' . $tileX . '" y="' . $tileY . '" width="52" height="52" rx="8" fill="#2b2d31"/>'
            . '<g fill="none" stroke="' . self::MUTED . '" stroke-width="2" stroke-linecap="round"><rect x="' . ($cx - 13) . '" y="' . ($cy - 7) . '" width="26" height="14" rx="7"/><line x1="' . ($cx - 7) . '" y1="' . ($cy - 1) . '" x2="' . ($cx - 7) . '" y2="' . ($cy + 3) . '"/><line x1="' . ($cx - 9) . '" y1="' . ($cy + 1) . '" x2="' . ($cx - 5) . '" y2="' . ($cy + 1) . '"/><circle cx="' . ($cx + 6) . '" cy="' . $cy . '" r="1.6" fill="' . self::MUTED . '" stroke="none"/></g>';
        $textX = $tileX + 66;
        $body .= $this->sectionLabel($textX, $top + 20, $this->label('playing'));
        $lineY = $top + 40;
        $body .= Svg::text($textX, $lineY, self::TEXT, 14, 700, self::cut($playing['name'], 32));
        foreach ([$hasDetails ? $playing['details'] : null, $hasState ? $playing['state'] : null] as $line) {
            if ($line !== null) {
                $lineY += 18;
                $body .= Svg::text($textX, $lineY, self::MUTED, 12, 400, self::cut((string) $line, 36));
            }
        }
        return $top + $panH + 4;
    }

    private function statusPanel(string &$body, int $top): int
    {
        $s = $this->state;
        $label = $s['customStatus'] ?? ($s['present'] ? $this->label(in_array($s['status'], ['online', 'idle', 'dnd'], true) ? $s['status'] : 'offline') : 'Discord');
        if ($s['stale']) {
            $label .= $this->label('cached');
        }
        $body .= $this->panel(16, $top, self::W - 32, 46);
        $body .= '<circle cx="40" cy="' . ($top + 23) . '" r="6" fill="' . self::statusColor($s['status']) . '"/>';
        $body .= Svg::text(56, $top + 28, self::TEXT2, 14, 500, self::cut($label, 44));
        return $top + 50;
    }

    private function panel(int $x, int $y, int $w, int $h): string
    {
        return '<rect x="' . $x . '" y="' . $y . '" width="' . $w . '" height="' . $h . '" rx="10" fill="' . self::PANEL_BG . '" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>';
    }

    private function label(string $key): string
    {
        return (self::LABELS[$this->lang] ?? self::LABELS['pl'])[$key];
    }

    private function date(?int $timestamp): string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return '';
        }
        $month = (self::LABELS[$this->lang] ?? self::LABELS['pl'])['months'][(int) gmdate('n', $timestamp) - 1];
        return $this->lang === 'en'
            ? $month . ' ' . gmdate('j', $timestamp) . ', ' . gmdate('Y', $timestamp)
            : gmdate('j', $timestamp) . ' ' . $month . ' ' . gmdate('Y', $timestamp);
    }

    private function memberSince(string &$body, int $y): int
    {
        $created = $this->state['createdAt'] ?? null;
        if ($created === null) {
            return $y;
        }
        $top = $y + 16;
        $body .= $this->sectionLabel(20, $top, $this->label('since'));
        $x = 20;
        $row = $top + 20;
        $body .= '<svg x="' . $x . '" y="' . ($row - 12) . '" width="16" height="16" viewBox="0 0 24 24"><path d="' . self::DISCORD_MARK . '" fill="' . self::MUTED . '"/></svg>';
        $body .= Svg::text($x + 22, $row + 1, self::TEXT2, 13, 400, $this->date((int) $created));
        return $row + 4;
    }

    private function sectionLabel(int $x, int $y, string $text): string
    {
        return '<text x="' . $x . '" y="' . $y . '" fill="' . self::MUTED . '" font-size="11" font-weight="700" letter-spacing="0.6">' . Svg::escape($text) . '</text>';
    }

    private static function arc(float $cx, float $cy, float $half, float $weight): string
    {
        return '<path d="M' . Svg::n($cx - $half) . ' ' . Svg::n($cy) . ' Q' . Svg::n($cx) . ' ' . Svg::n($cy - $half * 0.8) . ' ' . Svg::n($cx + $half) . ' ' . Svg::n($cy) . '" fill="none" stroke="#0b3d1f" stroke-width="' . Svg::n($weight) . '" stroke-linecap="round"/>';
    }

    private static function cut(string $text, int $max): string
    {
        return Svg::truncate($text, $max, '...');
    }

    private static function clock(int $millis): string
    {
        $seconds = max(0, intdiv($millis, 1000));
        return intdiv($seconds, 60) . ':' . sprintf('%02d', $seconds % 60);
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'online' => '#23a55a',
            'idle' => '#f0b232',
            'dnd' => '#f23f43',
            default => '#80848e',
        };
    }
}
