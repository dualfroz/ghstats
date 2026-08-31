<?php

declare(strict_types=1);

namespace Ghapi\Cards;

use Ghapi\Support\Labels;
use Ghapi\Support\LanguageColors;

final class Cards
{
    public const NAMES = ['stats', 'top-langs', 'streak', 'activity', 'heatmap', 'loc', 'by-year', 'top-repos', 'punchcard', 'achievements', 'opensource', 'frameworks', 'hours'];

    private const ADDED = '#3fb950';
    private const REMOVED = '#f85149';

    public function __construct(
        private readonly array $s,
        private readonly Theme $theme,
        private readonly Labels $labels,
        private readonly bool $hideBorder = false,
        private readonly bool $allTime = false,
        private readonly ?string $displayName = null,
        private readonly bool $public = true,
        private readonly array $heatmapThresholds = [3, 10, 25, 50],
    ) {
    }

    public function render(string $card): ?string
    {
        return match ($card) {
            'stats' => $this->stats(),
            'top-langs' => $this->topLangs(),
            'streak' => $this->streak(),
            'activity' => $this->activity(),
            'heatmap' => $this->allTime ? $this->heatmapAll() : $this->heatmap(),
            'loc' => $this->loc(),
            'by-year' => $this->byYear(),
            'top-repos' => $this->topRepos(),
            'punchcard' => $this->punchcard(),
            'achievements' => $this->achievements(),
            'opensource' => $this->openSource(),
            'frameworks' => $this->frameworks(),
            'hours' => $this->hours(),
            default => null,
        };
    }

    private function doc(int $width, int $height, string $label, string $body): string
    {
        return Svg::document($width, $height, $this->theme, $this->hideBorder, $label, $body);
    }

    private function name(): string
    {
        return $this->displayName !== null && $this->displayName !== '' ? $this->displayName : ($this->s['identity']['name'] ?? 'GitHub');
    }

    private function stats(): string
    {
        $t = $this->s['totals'];
        $l = $this->labels;
        $width = 495;
        $title = $l->get('stats.title', $this->name());
        $rows = [
            [$l->get('stats.commits'), $l->number($t['commits'])],
            [$l->get('stats.repos'), $l->number($t['repos'])],
            [$l->get('stats.stars'), $l->number($t['stars'] + (int) ($this->s['opensource']['contributed']['stars'] ?? 0))],
            [$l->get('stats.languages'), $l->number($t['languages'])],
            [$l->get('stats.activeDays'), $l->number($t['activeDays'])],
            [$l->get('stats.linesWritten'), $l->number($t['linesNet'])],
        ];
        $body = Svg::title(25, 37, $this->theme, $title) . Svg::divider($width, 50, $this->theme);
        foreach ($rows as $index => [$label, $value]) {
            $y = 78 + $index * 21;
            $body .= Svg::fade(0.15 + $index * 0.07,
                '<circle cx="33" cy="' . ($y - 4) . '" r="3.5" fill="' . $this->theme->icon . '"/>'
                . Svg::text(48, $y, $this->theme->text, 14, 400, $label)
                . Svg::text($width - 25, $y, $this->theme->title, 14, 700, $value, 'end'));
        }
        $y = 78 + count($rows) * 21;
        $body .= Svg::fade(0.57,
            '<circle cx="33" cy="' . ($y - 4) . '" r="3.5" fill="' . $this->theme->icon . '"/>'
            . Svg::text(48, $y, $this->theme->text, 14, 400, $l->get('stats.linesAddedRemoved'))
            . '<text x="' . ($width - 25) . '" y="' . $y . '" text-anchor="end" font-size="14" font-weight="700">'
            . '<tspan fill="' . self::ADDED . '">+' . Svg::escape($l->number($t['linesAdded'])) . '</tspan>'
            . '<tspan fill="' . $this->theme->muted . '"> / </tspan>'
            . '<tspan fill="' . self::REMOVED . '">-' . Svg::escape($l->number($t['linesRemoved'])) . '</tspan></text>');
        return $this->doc($width, 216, $title, $body);
    }

    private function topLangs(): string
    {
        $width = 495;
        $body = Svg::title(25, 37, $this->theme, $this->labels->get('langs.title')) . Svg::divider($width, 50, $this->theme);
        $languages = $this->s['languages'];
        if ($languages === []) {
            return $this->doc($width, 216, $this->labels->get('langs.title'), $body . Svg::text(25, 90, $this->theme->muted, 14, 400, $this->labels->get('langs.empty')));
        }
        $slices = [];
        $covered = 0.0;
        foreach (array_slice($languages, 0, 12) as $language) {
            $slices[] = [$language['name'], (float) $language['share'], $language['color']];
            $covered += (float) $language['share'];
        }
        if (count($languages) > 12 && 1 - $covered > 0.0005) {
            $slices[] = [$this->labels->get('langs.other'), 1 - $covered, LanguageColors::UNKNOWN];
        }
        $cx = 108;
        $cy = 138;
        $radius = 66;
        $pie = '';
        if (count($slices) === 1) {
            $pie = '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $radius . '" fill="' . $slices[0][2] . '"/>';
        } else {
            $angle = -M_PI / 2;
            foreach ($slices as [$name, $share, $color]) {
                $sweep = $share * 2 * M_PI;
                $end = $angle + $sweep;
                $pie .= '<path d="M' . $cx . ' ' . $cy
                    . ' L' . Svg::n($cx + $radius * cos($angle)) . ' ' . Svg::n($cy + $radius * sin($angle))
                    . ' A' . $radius . ' ' . $radius . ' 0 ' . ($sweep > M_PI ? 1 : 0) . ' 1 '
                    . Svg::n($cx + $radius * cos($end)) . ' ' . Svg::n($cy + $radius * sin($end))
                    . ' Z" fill="' . $color . '" stroke="' . $this->theme->background . '" stroke-width="1.5"/>';
                $angle = $end;
            }
        }
        $body .= '<g class="pie-grow">' . $pie . '</g>';
        $perColumn = intdiv(count($slices) + 1, 2);
        foreach ($slices as $index => [$name, $share, $color]) {
            $first = $index < $perColumn;
            $x = $first ? 200 : 350;
            $y = 74 + ($first ? $index : $index - $perColumn) * 20;
            $body .= Svg::fade(0.2 + $index * 0.05,
                '<rect x="' . $x . '" y="' . ($y - 11) . '" width="12" height="12" rx="3" fill="' . $color . '"/>'
                . Svg::text($x + 18, $y, $this->theme->text, 13, 400, Svg::truncate($name, 12))
                . Svg::text($x + 128, $y, $this->theme->muted, 13, 600, $this->labels->percent($share), 'end'));
        }
        return $this->doc($width, 216, $this->labels->get('langs.alt'), $body);
    }

    private function streak(): string
    {
        $streak = $this->s['streak'];
        $l = $this->labels;
        $body = '<line x1="165" y1="35" x2="165" y2="160" stroke="' . $this->theme->border . '" stroke-width="1"/>'
            . '<line x1="330" y1="35" x2="330" y2="160" stroke="' . $this->theme->border . '" stroke-width="1"/>';
        $body .= Svg::fade(0.1,
            Svg::text(82, 82, $this->theme->text, 34, 700, $l->number($this->s['totals']['commits']), 'middle')
            . Svg::text(82, 120, $this->theme->title, 14, 600, $l->get('streak.total'), 'middle')
            . Svg::text(82, 142, $this->theme->muted, 12, 400, $l->get('streak.allTime'), 'middle'));
        $body .= Svg::fade(0.2,
            '<circle cx="247" cy="72" r="38" fill="none" stroke="' . $this->theme->title . '" stroke-width="3"/>'
            . Svg::text(247, 82, $this->theme->text, 34, 700, $l->number($streak['current']), 'middle')
            . Svg::text(247, 132, $this->theme->title, 14, 600, $l->get('streak.current'), 'middle')
            . Svg::text(247, 154, $this->theme->muted, 11, 400, $l->range($streak['currentStart'], $streak['currentEnd']), 'middle'));
        $body .= Svg::fade(0.3,
            Svg::text(412, 82, $this->theme->text, 34, 700, $l->number($streak['longest']), 'middle')
            . Svg::text(412, 120, $this->theme->title, 14, 600, $l->get('streak.longest'), 'middle')
            . Svg::text(412, 142, $this->theme->muted, 11, 400, $l->range($streak['longestStart'], $streak['longestEnd']), 'middle'));
        return $this->doc(495, 195, $l->get('streak.alt'), $body);
    }

    private function dayCounts(): array
    {
        $counts = [];
        foreach ($this->s['days'] as [$date, $commits]) {
            $counts[$date] = $commits;
        }
        return $counts;
    }

    private function activity(): string
    {
        $width = 495;
        $height = 200;
        $title = $this->labels->get($this->allTime ? 'activity.titleAll' : 'activity.titleYear');
        $values = [];
        $labels = [];
        if ($this->allTime) {
            $years = [];
            foreach ($this->s['days'] as [$date, $commits]) {
                $years[substr($date, 0, 4)] = ($years[substr($date, 0, 4)] ?? 0) + $commits;
            }
            ksort($years);
            $values = array_values($years);
            $labels = array_map('strval', array_keys($years));
        } else {
            $counts = $this->dayCounts();
            $end = strtotime($this->s['today'] . ' 00:00:00 UTC');
            $start = $end - (53 * 7 - 1) * 86400;
            for ($bucket = 0; $bucket < 53; $bucket++) {
                $sum = 0;
                for ($d = 0; $d < 7; $d++) {
                    $sum += $counts[gmdate('Y-m-d', $start + ($bucket * 7 + $d) * 86400)] ?? 0;
                }
                $values[] = $sum;
                $labels[] = '';
            }
            for ($tick = 0; $tick < 6; $tick++) {
                $index = (int) round($tick * 52 / 5);
                $t = $start + $index * 7 * 86400;
                $labels[$index] = $this->labels->get('month.' . gmdate('n', $t)) . " '" . gmdate('y', $t);
            }
        }
        $body = Svg::title(25, 37, $this->theme, $title);
        if ($values === [] || array_sum($values) === 0 && $this->allTime) {
            return $this->doc($width, 120, $title, $body . Svg::text(25, 90, $this->theme->muted, 14, 400, $this->labels->get('activity.empty')));
        }
        $left = 45;
        $right = $width - 20;
        $top = 60;
        $bottom = $height - 28;
        $max = max(1, max($values));
        $n = count($values);
        $body .= '<defs><linearGradient id="ghsActivity" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $this->theme->title . '" stop-opacity="0.35"/><stop offset="1" stop-color="' . $this->theme->title . '" stop-opacity="0"/></linearGradient></defs>';
        $body .= '<line x1="' . $left . '" y1="' . $bottom . '" x2="' . $right . '" y2="' . $bottom . '" stroke="' . $this->theme->border . '" stroke-width="1"/>';
        $body .= Svg::text($left - 8, $top + 4, $this->theme->muted, 11, 400, $this->labels->number($max), 'end');
        $body .= Svg::text($left - 8, $bottom + 3, $this->theme->muted, 11, 400, '0', 'end');
        $line = '';
        $area = '';
        $dots = '';
        foreach ($values as $i => $value) {
            $x = $n === 1 ? $left + ($right - $left) / 2 : $left + ($right - $left) * $i / ($n - 1);
            $y = $bottom - ($value / $max) * ($bottom - $top);
            $line .= ($i === 0 ? 'M' : ' L') . Svg::n($x) . ' ' . Svg::n($y);
            $area .= $i === 0 ? 'M' . Svg::n($x) . ' ' . $bottom . ' L' . Svg::n($x) . ' ' . Svg::n($y) : ' L' . Svg::n($x) . ' ' . Svg::n($y);
            if ($labels[$i] !== '') {
                $body .= Svg::text($x, $bottom + 18, $this->theme->muted, 10, 400, $labels[$i], 'middle');
            }
            if ($n <= 12) {
                $dots .= '<circle cx="' . Svg::n($x) . '" cy="' . Svg::n($y) . '" r="2.5" fill="' . $this->theme->title . '"/>';
            }
        }
        $area .= ' L' . Svg::n($n === 1 ? $left + ($right - $left) / 2 : $right) . ' ' . $bottom . ' Z';
        $body .= '<path d="' . $area . '" fill="url(#ghsActivity)"/>';
        $body .= '<path d="' . $line . '" fill="none" stroke="' . $this->theme->title . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>' . $dots;
        return $this->doc($width, $height, $title, $body);
    }

    private function level(int $count): int
    {
        if ($count <= 0) {
            return 0;
        }
        foreach ($this->heatmapThresholds as $index => $threshold) {
            if ($count <= $threshold) {
                return $index + 1;
            }
        }
        return count($this->heatmapThresholds) + 1;
    }

    private function heatLegend(int $width, int $y, int $cell): string
    {
        $x = $width - 220;
        $out = Svg::text($x - 6, $y + 9, $this->theme->muted, 11, 400, $this->labels->get('heatmap.less'), 'end');
        foreach ($this->theme->heatLevels as $index => $color) {
            $out .= '<rect x="' . ($x + $index * 15) . '" y="' . $y . '" width="' . $cell . '" height="' . $cell . '" rx="2" fill="' . $color . '"/>';
        }
        return $out . Svg::text($x + count($this->theme->heatLevels) * 15 + 4, $y + 9, $this->theme->muted, 11, 400, $this->labels->get('heatmap.more'));
    }

    private function heatmap(): string
    {
        $width = 740;
        $title = $this->labels->get('heatmap.titleYear');
        $counts = $this->dayCounts();
        $today = strtotime($this->s['today'] . ' 00:00:00 UTC');
        $body = Svg::title(25, 37, $this->theme, $title);
        $topLeft = $today - ((int) gmdate('w', $today)) * 86400 - 52 * 7 * 86400;
        $total = 0;
        $cells = '';
        $months = '';
        $previousMonth = -1;
        for ($col = 0; $col < 53; $col++) {
            $columnStart = $topLeft + $col * 7 * 86400;
            if ($columnStart <= $today && (int) gmdate('n', $columnStart) !== $previousMonth) {
                $previousMonth = (int) gmdate('n', $columnStart);
                $months .= Svg::text(32 + $col * 13, 52, $this->theme->muted, 10, 400, $this->labels->get('month.' . $previousMonth));
            }
            for ($row = 0; $row < 7; $row++) {
                $t = $columnStart + $row * 86400;
                if ($t > $today) {
                    continue;
                }
                $count = $counts[gmdate('Y-m-d', $t)] ?? 0;
                $total += $count;
                $cells .= '<rect x="' . (32 + $col * 13) . '" y="' . (58 + $row * 13) . '" width="11" height="11" rx="2" fill="' . $this->theme->heatLevels[$this->level($count)] . '"/>';
            }
        }
        $body .= Svg::text($width - 25, 37, $this->theme->text, 13, 600, $this->labels->get('heatmap.countYear', $this->labels->number($total)), 'end');
        foreach ([1, 3, 5] as $row) {
            $body .= Svg::text(27, 58 + $row * 13 + 9, $this->theme->muted, 9, 400, $this->labels->get('weekdayShort.' . $row), 'end');
        }
        $body .= $months . $cells . $this->heatLegend($width, 58 + 7 * 13 + 14, 11);
        return $this->doc($width, 190, $title, $body);
    }

    private function heatmapAll(): string
    {
        $width = 740;
        $title = $this->labels->get('heatmap.titleAll');
        $counts = $this->dayCounts();
        $body = Svg::title(25, 37, $this->theme, $title);
        if ($counts === []) {
            return $this->doc($width, 120, $title, $body . Svg::text(25, 90, $this->theme->muted, 14, 400, $this->labels->get('heatmap.empty')));
        }
        $body .= Svg::text($width - 25, 37, $this->theme->text, 13, 600, $this->labels->get('heatmap.countAll', $this->labels->number(array_sum($counts))), 'end');
        $dates = array_keys($counts);
        sort($dates);
        $minYear = (int) substr($dates[0], 0, 4);
        $maxDate = end($dates);
        $maxYear = (int) substr($maxDate, 0, 4);
        $blockHeight = 7 * 12 + 22;
        $years = $maxYear - $minYear + 1;
        for ($i = 0; $i < $years; $i++) {
            $year = $minYear + $i;
            $gridTop = 58 + $i * $blockHeight + 4;
            $body .= Svg::text(20, $gridTop + 7 * 6 + 4, $this->theme->title, 12, 700, (string) $year);
            $first = strtotime("{$year}-01-01 00:00:00 UTC");
            $last = min(strtotime("{$year}-12-31 00:00:00 UTC"), strtotime($maxDate . ' 00:00:00 UTC'));
            $offset = (int) gmdate('w', $first);
            for ($t = $first; $t <= $last; $t += 86400) {
                $dayIndex = (int) gmdate('z', $t) + $offset;
                $count = $counts[gmdate('Y-m-d', $t)] ?? 0;
                $body .= '<rect x="' . (48 + intdiv($dayIndex, 7) * 12) . '" y="' . ($gridTop + $dayIndex % 7 * 12) . '" width="10" height="10" rx="2" fill="' . $this->theme->heatLevels[$this->level($count)] . '"/>';
            }
        }
        $body .= $this->heatLegend($width, 58 + $years * $blockHeight + 6, 10);
        return $this->doc($width, 58 + $years * $blockHeight + 28, $title, $body);
    }

    private function loc(): string
    {
        $width = 495;
        $t = $this->s['totals'];
        $l = $this->labels;
        $body = Svg::title(25, 34, $this->theme, $l->get('loc.title')) . Svg::divider($width, 46, $this->theme);
        $body .= Svg::text($width / 2, 104, $this->theme->title, 42, 700, $l->number($t['linesNet']), 'middle');
        $body .= Svg::text($width / 2, 128, $this->theme->muted, 12, 400, $l->get('loc.caption'), 'middle');
        $added = $t['linesAdded'];
        $removed = $t['linesRemoved'];
        if ($added + $removed > 0) {
            $barWidth = $width - 50;
            $addedWidth = $added / ($added + $removed) * $barWidth;
            $body .= '<defs><clipPath id="ghsWrittenBar"><rect x="25" y="150" width="' . $barWidth . '" height="18" rx="9"/></clipPath></defs>'
                . '<g class="grow-right"><g clip-path="url(#ghsWrittenBar)">'
                . '<rect x="25" y="150" width="' . Svg::n($addedWidth) . '" height="18" fill="' . self::ADDED . '"/>'
                . '<rect x="' . Svg::n(25 + $addedWidth) . '" y="150" width="' . Svg::n($barWidth - $addedWidth) . '" height="18" fill="' . self::REMOVED . '"/>'
                . '</g></g>';
            $body .= Svg::text(25, 196, self::ADDED, 13, 600, $l->get('loc.added', $l->number($added)));
            $body .= Svg::text($width - 25, 196, self::REMOVED, 13, 600, $l->get('loc.removed', $l->number($removed)), 'end');
        }
        return $this->doc($width, 216, $l->get('loc.alt'), $body);
    }

    private function byYear(): string
    {
        $width = 495;
        $years = [];
        foreach ($this->s['days'] as [$date, $commits]) {
            $years[substr($date, 0, 4)] = ($years[substr($date, 0, 4)] ?? 0) + $commits;
        }
        ksort($years);
        $body = Svg::title(25, 37, $this->theme, $this->labels->get('year.title')) . Svg::divider($width, 50, $this->theme);
        if ($years === []) {
            return $this->doc($width, 216, $this->labels->get('year.title'), $body . Svg::text(25, 95, $this->theme->muted, 14, 400, $this->labels->get('year.empty')));
        }
        $left = 40;
        $bottom = 178;
        $plotHeight = 108;
        $max = max(1, max($years));
        $slot = ($width - 20 - $left) / count($years);
        $bar = $slot * 0.6;
        $body .= '<line x1="' . $left . '" y1="' . $bottom . '" x2="' . ($width - 20) . '" y2="' . $bottom . '" stroke="' . $this->theme->border . '" stroke-width="1"/>';
        $i = 0;
        foreach ($years as $year => $count) {
            $height = $count / $max * $plotHeight;
            $x = $left + $i * $slot + ($slot - $bar) / 2;
            $y = $bottom - $height;
            $delay = 0.15 + $i * 0.07;
            $body .= Svg::fade($delay,
                '<rect x="' . Svg::n($x) . '" y="' . Svg::n($y) . '" width="' . Svg::n($bar) . '" height="' . Svg::n($height) . '" rx="3" fill="' . $this->theme->title . '" class="grow-up" style="animation-delay:' . Svg::n($delay) . 's"/>'
                . Svg::text($x + $bar / 2, $y - 6, $this->theme->text, 11, 600, $this->labels->compact($count), 'middle')
                . Svg::text($x + $bar / 2, $bottom + 17, $this->theme->muted, 11, 400, (string) $year, 'middle'));
            $i++;
        }
        return $this->doc($width, 216, $this->labels->get('year.alt'), $body);
    }

    private function topRepos(): string
    {
        $width = 495;
        if ($this->public) {
            $rows = array_map(static fn (array $p): array => [basename($p['name']), $p['stars'], $p['merged']], array_slice(array_values(array_filter($this->s['opensource']['projects'], static fn (array $p): bool => $p['merged'] > 0)), 0, 8));
            $title = $this->labels->get('repos.title');
        } else {
            $rows = array_map(static fn (array $r): array => [basename($r['name']), $r['commits'], $r['commits']], array_slice($this->s['repos'], 0, 10));
            $title = $this->labels->get('repos.privateTitle');
        }
        $body = Svg::title(25, 37, $this->theme, $title) . Svg::divider($width, 50, $this->theme);
        if ($rows === []) {
            return $this->doc($width, 130, $title, $body . Svg::text(25, 95, $this->theme->muted, 14, 400, $this->labels->get('repos.empty')));
        }
        $max = max(1, max(array_column($rows, 1)));
        foreach ($rows as $i => [$name, $value, $secondary]) {
            $center = 72 + $i * 28;
            $scale = $this->public ? sqrt($value / $max) : $value / $max;
            $barWidth = max(2, $scale * 255);
            $delay = 0.12 + $i * 0.05;
            $label = $this->public ? '★ ' . $this->labels->compact($value) : $this->labels->number($value);
            $body .= Svg::fade($delay,
                Svg::text(25, $center + 4, $this->theme->text, 13, 400, Svg::truncate($name, 18))
                . '<rect x="165" y="' . ($center - 7) . '" width="' . Svg::n($barWidth * 0.85) . '" height="14" rx="4" fill="' . $this->theme->title . '" class="grow-right" style="animation-delay:' . Svg::n($delay) . 's"/>'
                . Svg::text($width - 25, $center + 4, $this->theme->icon, 12, 700, $label, 'end'));
        }
        return $this->doc($width, 72 + (count($rows) - 1) * 28 + 26, $title, $body);
    }

    private function punchcard(): string
    {
        $width = 740;
        $grid = $this->s['punch']['all'];
        $l = $this->labels;
        $body = Svg::title(25, 37, $this->theme, $l->get('punch.title')) . Svg::text(25, 56, $this->theme->muted, 11, 400, $l->get('punch.subtitle'));
        $max = max(1, max(array_map('max', $grid)));
        if (array_sum(array_map('array_sum', $grid)) === 0) {
            return $this->doc($width, 130, $l->get('punch.title'), $body . Svg::text(25, 100, $this->theme->muted, 14, 400, $l->get('punch.empty')));
        }
        $radius = static fn (float $f): float => 2.2 + $f ** 0.7 * (9.5 - 2.2);
        $opacity = static fn (float $f): float => round(0.35 + 0.65 * $f, 2);
        for ($row = 0; $row < 7; $row++) {
            $body .= Svg::text(48, 78 + $row * 20 + 14, $this->theme->muted, 10, 400, $l->get('weekdayShort.' . ($row + 1)), 'end');
            for ($hour = 0; $hour < 24; $hour++) {
                $count = $grid[$row][$hour];
                if ($count <= 0) {
                    continue;
                }
                $f = $count / $max;
                $body .= '<circle cx="' . Svg::n(58 + $hour * 27 + 13.5) . '" cy="' . (78 + $row * 20 + 10) . '" r="' . Svg::n($radius($f)) . '" fill="' . $this->theme->icon . '" fill-opacity="' . Svg::n($opacity($f)) . '"/>';
            }
        }
        for ($hour = 0; $hour <= 23; $hour += 3) {
            $body .= Svg::text(58 + $hour * 27 + 13.5, 78 + 140 + 16, $this->theme->muted, 9, 400, (string) $hour, 'middle');
        }
        $y = 78 + 140 + 40;
        $x = $width - 236;
        $body .= Svg::text($x - 10, $y + 4, $this->theme->muted, 11, 400, $l->get('heatmap.less'), 'end');
        foreach ([0.05, 0.3, 0.55, 0.8, 1.0] as $i => $f) {
            $body .= '<circle cx="' . ($x + $i * 26) . '" cy="' . $y . '" r="' . Svg::n($radius($f)) . '" fill="' . $this->theme->icon . '" fill-opacity="' . Svg::n($opacity($f)) . '"/>';
        }
        $body .= Svg::text($x + 126, $y + 4, $this->theme->muted, 11, 400, $l->get('heatmap.more'));
        $body .= Svg::text(58, $y + 4, $this->theme->muted, 10, 400, $l->get('punch.peak', $l->number($max)));
        return $this->doc($width, 280, $l->get('punch.title'), $body);
    }

    private function achievements(): string
    {
        $width = 495;
        $t = $this->s['totals'];
        $l = $this->labels;
        $badges = [
            [(string) $t['yearsActive'], $l->get('achievements.years')],
            [$l->compact($t['commits']), $l->get('achievements.commits')],
            [$l->number($t['repos']), $l->get('achievements.repos')],
            [(string) $t['languages'], $l->get('achievements.languages')],
            [(string) $this->s['streak']['longest'], $l->get('achievements.streak')],
            [$l->compact($t['codeLinesAdded']), $l->get('achievements.lines')],
        ];
        if ($t['languages'] >= 10) {
            $badges[] = ['Polyglot', $l->get('achievements.polyglot')];
        }
        if ($this->s['streak']['longest'] >= 30) {
            $badges[] = ['Marathoner', $l->get('achievements.marathon')];
        }
        $body = Svg::title(25, 37, $this->theme, $l->get('achievements.title')) . Svg::divider($width, 48, $this->theme);
        foreach ($badges as $i => [$value, $label]) {
            $x = 23 + ($i % 3) * 157;
            $y = 58 + intdiv($i, 3) * 90;
            $body .= Svg::fade(0.12 + $i * 0.05,
                '<rect x="' . $x . '" y="' . $y . '" width="145" height="76" rx="8" fill="none" stroke="' . $this->theme->border . '" stroke-width="1"/>'
                . '<circle cx="' . ($x + 16) . '" cy="' . ($y + 16) . '" r="4" fill="' . $this->theme->icon . '"/>'
                . Svg::text($x + 72.5, $y + 46, $this->theme->title, strlen($value) <= 6 ? 24 : 16, 700, $value, 'middle')
                . Svg::text($x + 72.5, $y + 64, $this->theme->muted, 11, 400, $label, 'middle'));
        }
        return $this->doc($width, 58 + intdiv(count($badges) + 2, 3) * 90 + 6, $l->get('achievements.title'), $body);
    }

    private function openSource(): string
    {
        $width = 495;
        $oss = $this->s['opensource'];
        $l = $this->labels;
        $rows = [
            [$l->get('oss.merged'), $l->number($oss['pullRequests']['merged'] ?? 0)],
            [$l->get('oss.repos'), $l->number($oss['contributed']['repos'])],
            [$l->get('oss.stars'), $l->number($oss['contributed']['stars'])],
            [$l->get('oss.lines'), $l->number($oss['contributed']['linesAdded'])],
            [$l->get('oss.open'), $l->number($oss['pullRequests']['open'] ?? 0)],
        ];
        $body = Svg::title(25, 37, $this->theme, $l->get('oss.title')) . Svg::divider($width, 50, $this->theme);
        foreach ($rows as $index => [$label, $value]) {
            $y = 78 + $index * 22;
            $body .= Svg::fade(0.15 + $index * 0.07,
                '<circle cx="33" cy="' . ($y - 4) . '" r="3.5" fill="' . $this->theme->icon . '"/>'
                . Svg::text(48, $y, $this->theme->text, 14, 400, $label)
                . Svg::text($width - 25, $y, $this->theme->title, 14, 700, $value, 'end'));
        }
        $merged = array_values(array_filter($oss['projects'], static fn (array $p): bool => $p['merged'] > 0));
        usort($merged, static fn (array $a, array $b): int => $b['stars'] <=> $a['stars']);
        $top = array_slice($merged, 0, 4);
        $y = 78 + count($rows) * 22 + 8;
        if ($top !== []) {
            $body .= Svg::text(25, $y, $this->theme->muted, 12, 600, $l->get('oss.top'));
            foreach ($top as $i => $project) {
                $rowY = $y + 22 + $i * 20;
                $body .= Svg::fade(0.5 + $i * 0.06,
                    '<circle cx="31" cy="' . ($rowY - 4) . '" r="4" fill="' . ($project['color'] ?? LanguageColors::UNKNOWN) . '"/>'
                    . Svg::text(42, $rowY, $this->theme->text, 13, 400, Svg::truncate($project['name'], 40))
                    . Svg::text($width - 25, $rowY, $this->theme->icon, 12, 700, '★ ' . $l->compact($project['stars']), 'end'));
            }
            $y += 22 + count($top) * 20;
        }
        return $this->doc($width, $y + 8, $l->get('oss.title'), $body);
    }

    private function frameworks(): string
    {
        $width = 495;
        $rows = array_slice($this->s['frameworks'], 0, 12);
        $title = $this->labels->get('frameworks.title');
        $body = Svg::title(25, 37, $this->theme, $title) . Svg::divider($width, 50, $this->theme);
        if ($rows === []) {
            return $this->doc($width, 130, $title, $body . Svg::text(25, 95, $this->theme->muted, 14, 400, $this->labels->get('frameworks.empty')));
        }
        $max = max(1, max(array_column($rows, 'repos')));
        $perColumn = intdiv(count($rows) + 1, 2);
        foreach ($rows as $i => $framework) {
            $first = $i < $perColumn;
            $x = $first ? 25 : 260;
            $y = 76 + ($first ? $i : $i - $perColumn) * 26;
            $bar = max(4, $framework['repos'] / $max * 120);
            $body .= Svg::fade(0.12 + $i * 0.04,
                Svg::text($x, $y, $this->theme->text, 13, 600, Svg::truncate($framework['name'], 18))
                . Svg::text($x + 210, $y, $this->theme->muted, 11, 400, $this->labels->get('frameworks.repos', $framework['repos']), 'end')
                . '<rect x="' . $x . '" y="' . ($y + 5) . '" width="' . Svg::n($bar) . '" height="4" rx="2" fill="' . $this->theme->title . '" class="grow-right" style="animation-delay:' . Svg::n(0.12 + $i * 0.04) . 's"/>');
        }
        return $this->doc($width, 76 + $perColumn * 26 + 4, $title, $body);
    }

    private function hours(): string
    {
        $width = 495;
        $grid = $this->s['punch']['all'];
        $hours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hours[] = array_sum(array_column($grid, $hour));
        }
        $title = $this->labels->get('hours.title');
        $body = Svg::title(25, 37, $this->theme, $title);
        $max = max(1, max($hours));
        $peak = array_search(max($hours), $hours, true);
        $body .= Svg::text($width - 25, 37, $this->theme->muted, 12, 400, $this->labels->get('hours.peak', sprintf('%02d', (int) $peak)), 'end');
        $body .= Svg::divider($width, 50, $this->theme);
        $bottom = 176;
        $slot = ($width - 50) / 24;
        foreach ($hours as $hour => $count) {
            $height = $count / $max * 100;
            $x = 25 + $hour * $slot + $slot * 0.15;
            $delay = 0.1 + $hour * 0.02;
            $body .= '<rect x="' . Svg::n($x) . '" y="' . Svg::n($bottom - $height) . '" width="' . Svg::n($slot * 0.7) . '" height="' . Svg::n(max(1, $height)) . '" rx="2" fill="' . ($hour === $peak ? $this->theme->icon : $this->theme->title) . '" class="grow-up" style="animation-delay:' . Svg::n($delay) . 's"/>';
            if ($hour % 3 === 0) {
                $body .= Svg::text($x + $slot * 0.35, $bottom + 16, $this->theme->muted, 10, 400, sprintf('%02d', $hour), 'middle');
            }
        }
        return $this->doc($width, 204, $title, $body);
    }
}
