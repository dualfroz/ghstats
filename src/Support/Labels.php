<?php

declare(strict_types=1);

namespace Ghapi\Support;

final class Labels
{
    private const EN = [
        'stats.title' => "%s's GitHub Stats",
        'stats.commits' => 'Own commits (all-time)',
        'stats.repos' => 'Repositories',
        'stats.stars' => 'Stars of projects with my code',
        'stats.languages' => 'Languages used',
        'stats.activeDays' => 'Active days',
        'stats.linesWritten' => 'Lines written',
        'stats.linesAddedRemoved' => 'Lines added / removed',
        'langs.title' => 'Most Used Languages',
        'langs.other' => 'Other',
        'langs.alt' => 'Most used languages pie chart',
        'langs.empty' => 'No language data available.',
        'loc.title' => 'Lines Written',
        'loc.caption' => 'net lines written, all-time',
        'loc.added' => '+%s added',
        'loc.removed' => '-%s removed',
        'loc.alt' => 'Lines written net with added and removed',
        'year.title' => 'Commits per Year',
        'year.alt' => 'Commits per year bar chart',
        'year.empty' => 'No yearly data available.',
        'streak.total' => 'Total Contributions',
        'streak.allTime' => 'all time',
        'streak.current' => 'Current Streak',
        'streak.longest' => 'Longest Streak',
        'streak.consecutive' => 'consecutive days',
        'streak.alt' => 'Contribution streak',
        'activity.titleYear' => 'Contribution Activity (52 weeks)',
        'activity.titleAll' => 'Contribution Activity (all time)',
        'activity.empty' => 'No activity data available.',
        'heatmap.titleYear' => 'Contribution Heatmap (52 weeks)',
        'heatmap.titleAll' => 'Contribution Heatmap (all time)',
        'heatmap.countYear' => '%s commits in the last year',
        'heatmap.countAll' => '%s commits all-time',
        'heatmap.empty' => 'No contribution data available.',
        'heatmap.less' => 'Less',
        'heatmap.more' => 'More',
        'punch.title' => 'When I Code',
        'punch.subtitle' => 'Commits by weekday (rows) and hour of day (columns), author local time',
        'punch.peak' => 'peak %s commits/hour',
        'punch.empty' => 'No commit-time data available.',
        'repos.title' => 'Top Open Source Contributions',
        'repos.empty' => 'No public contributions yet.',
        'repos.privateTitle' => 'Top Repositories',
        'achievements.title' => 'Achievements',
        'achievements.years' => 'Years of Code',
        'achievements.commits' => 'Commits',
        'achievements.repos' => 'Repositories',
        'achievements.languages' => 'Languages',
        'achievements.streak' => 'Day Streak',
        'achievements.lines' => 'Lines of Code',
        'achievements.polyglot' => '10+ Languages',
        'achievements.marathon' => '30+ Day Streak',
        'oss.title' => 'Open Source',
        'oss.merged' => 'Merged pull requests',
        'oss.repos' => 'Projects contributed to',
        'oss.stars' => 'Stars of those projects',
        'oss.lines' => 'Lines merged upstream',
        'oss.open' => 'Pull requests in review',
        'oss.top' => 'Biggest projects',
        'frameworks.title' => 'Frameworks and Tools',
        'frameworks.repos' => '%s repos',
        'frameworks.empty' => 'No frameworks detected yet.',
        'hours.title' => 'Commits by Hour',
        'hours.peak' => 'most commits around %s:00',
        'nodata' => 'No data available yet.',
        'weekday.1' => 'Monday', 'weekday.2' => 'Tuesday', 'weekday.3' => 'Wednesday', 'weekday.4' => 'Thursday',
        'weekday.5' => 'Friday', 'weekday.6' => 'Saturday', 'weekday.7' => 'Sunday',
        'weekdayShort.1' => 'Mon', 'weekdayShort.2' => 'Tue', 'weekdayShort.3' => 'Wed', 'weekdayShort.4' => 'Thu',
        'weekdayShort.5' => 'Fri', 'weekdayShort.6' => 'Sat', 'weekdayShort.7' => 'Sun',
        'month.1' => 'Jan', 'month.2' => 'Feb', 'month.3' => 'Mar', 'month.4' => 'Apr', 'month.5' => 'May', 'month.6' => 'Jun',
        'month.7' => 'Jul', 'month.8' => 'Aug', 'month.9' => 'Sep', 'month.10' => 'Oct', 'month.11' => 'Nov', 'month.12' => 'Dec',
        'kind.code' => 'Code', 'kind.test' => 'Tests', 'kind.docs' => 'Documentation', 'kind.config' => 'Configuration',
        'kind.lock' => 'Lockfiles', 'kind.data' => 'Data', 'kind.asset' => 'Assets', 'kind.other' => 'Other',
        'size.xs' => 'under 10 lines', 'size.s' => '10-49 lines', 'size.m' => '50-199 lines', 'size.l' => '200-999 lines',
        'size.xl' => '1000-4999 lines', 'size.xxl' => '5000+ lines',
        'status.active' => 'active in the last 30 days', 'status.maintained' => 'active in the last 6 months',
        'status.dormant' => 'active in the last year', 'status.inactive' => 'no commits for over a year',
        'period.week' => 'week', 'period.month' => 'month',
    ];

    private const PL = [
        'stats.title' => '%s w liczbach',
        'stats.commits' => 'Commity (od zawsze)',
        'stats.repos' => 'Repozytoria',
        'stats.stars' => 'Gwiazdki projektów z moim kodem',
        'stats.languages' => 'Używane języki',
        'stats.activeDays' => 'Dni z pracą',
        'stats.linesWritten' => 'Napisane linie',
        'stats.linesAddedRemoved' => 'Linie dodane / usunięte',
        'langs.title' => 'Czym piszę',
        'langs.other' => 'Pozostałe',
        'langs.alt' => 'Wykres używanych języków',
        'langs.empty' => 'Brak danych o językach.',
        'loc.title' => 'Napisane linie',
        'loc.caption' => 'linii kodu netto, od zawsze',
        'loc.added' => '+%s dodanych',
        'loc.removed' => '-%s usuniętych',
        'loc.alt' => 'Napisane linie netto z dodanymi i usuniętymi',
        'year.title' => 'Commity rocznie',
        'year.alt' => 'Wykres commitów w kolejnych latach',
        'year.empty' => 'Brak danych rocznych.',
        'streak.total' => 'Wszystkie commity',
        'streak.allTime' => 'od zawsze',
        'streak.current' => 'Obecna seria',
        'streak.longest' => 'Najdłuższa seria',
        'streak.consecutive' => 'dni z rzędu',
        'streak.alt' => 'Serie dni z commitami',
        'activity.titleYear' => 'Aktywność (52 tygodnie)',
        'activity.titleAll' => 'Aktywność (od zawsze)',
        'activity.empty' => 'Brak danych o aktywności.',
        'heatmap.titleYear' => 'Mapa commitów (52 tygodnie)',
        'heatmap.titleAll' => 'Mapa commitów (od zawsze)',
        'heatmap.countYear' => '%s commitów w ostatnim roku',
        'heatmap.countAll' => '%s commitów od zawsze',
        'heatmap.empty' => 'Brak danych o commitach.',
        'heatmap.less' => 'Mniej',
        'heatmap.more' => 'Więcej',
        'punch.title' => 'Kiedy koduję',
        'punch.subtitle' => 'Commity według dnia tygodnia (wiersze) i godziny (kolumny), czas lokalny autora',
        'punch.peak' => 'szczyt %s commitów na godzinę',
        'punch.empty' => 'Brak danych o godzinach commitów.',
        'repos.title' => 'Największe kontrybucje open source',
        'repos.empty' => 'Brak publicznych kontrybucji.',
        'repos.privateTitle' => 'Najaktywniejsze repozytoria',
        'achievements.title' => 'Osiągnięcia',
        'achievements.years' => 'Lat kodowania',
        'achievements.commits' => 'Commitów',
        'achievements.repos' => 'Repozytoriów',
        'achievements.languages' => 'Języków',
        'achievements.streak' => 'Dni z rzędu',
        'achievements.lines' => 'Linii kodu',
        'achievements.polyglot' => '10+ języków',
        'achievements.marathon' => '30+ dni z rzędu',
        'oss.title' => 'Open source',
        'oss.merged' => 'Zmergowane pull requesty',
        'oss.repos' => 'Projekty z moim kodem',
        'oss.stars' => 'Gwiazdki tych projektów',
        'oss.lines' => 'Linie przyjęte do projektów',
        'oss.open' => 'Pull requesty w review',
        'oss.top' => 'Największe projekty',
        'frameworks.title' => 'Frameworki i narzędzia',
        'frameworks.repos' => '%s repo',
        'frameworks.empty' => 'Nie wykryto jeszcze frameworków.',
        'hours.title' => 'Commity według godziny',
        'hours.peak' => 'najwięcej commitów około %s:00',
        'nodata' => 'Brak danych.',
        'weekday.1' => 'poniedziałek', 'weekday.2' => 'wtorek', 'weekday.3' => 'środa', 'weekday.4' => 'czwartek',
        'weekday.5' => 'piątek', 'weekday.6' => 'sobota', 'weekday.7' => 'niedziela',
        'weekdayShort.1' => 'Pn', 'weekdayShort.2' => 'Wt', 'weekdayShort.3' => 'Śr', 'weekdayShort.4' => 'Cz',
        'weekdayShort.5' => 'Pt', 'weekdayShort.6' => 'So', 'weekdayShort.7' => 'Nd',
        'month.1' => 'sty', 'month.2' => 'lut', 'month.3' => 'mar', 'month.4' => 'kwi', 'month.5' => 'maj', 'month.6' => 'cze',
        'month.7' => 'lip', 'month.8' => 'sie', 'month.9' => 'wrz', 'month.10' => 'paź', 'month.11' => 'lis', 'month.12' => 'gru',
        'kind.code' => 'Kod', 'kind.test' => 'Testy', 'kind.docs' => 'Dokumentacja', 'kind.config' => 'Konfiguracja',
        'kind.lock' => 'Lockfile', 'kind.data' => 'Dane', 'kind.asset' => 'Zasoby', 'kind.other' => 'Inne',
        'size.xs' => 'poniżej 10 linii', 'size.s' => '10-49 linii', 'size.m' => '50-199 linii', 'size.l' => '200-999 linii',
        'size.xl' => '1000-4999 linii', 'size.xxl' => '5000+ linii',
        'status.active' => 'aktywne w ostatnich 30 dniach', 'status.maintained' => 'aktywne w ostatnich 6 miesiącach',
        'status.dormant' => 'aktywne w ostatnim roku', 'status.inactive' => 'bez commitów od ponad roku',
        'period.week' => 'tydzień', 'period.month' => 'miesiąc',
    ];

    private function __construct(public readonly string $lang)
    {
    }

    public static function of(string $lang): self
    {
        return new self($lang === 'pl' ? 'pl' : 'en');
    }

    public function get(string $key, string|int|float ...$args): string
    {
        $map = $this->lang === 'pl' ? self::PL : self::EN;
        $value = $map[$key] ?? self::EN[$key] ?? $key;
        return $args === [] ? $value : sprintf($value, ...$args);
    }

    public function number(int|float $value): string
    {
        return $this->lang === 'pl'
            ? str_replace(' ', "\u{00A0}", number_format((float) $value, 0, ',', ' '))
            : number_format((float) $value, 0, '.', ',');
    }

    public function compact(int $value): string
    {
        $trim = static function (float $number): string {
            $formatted = sprintf('%.1f', $number);
            return str_ends_with($formatted, '.0') ? substr($formatted, 0, -2) : $formatted;
        };
        $out = match (true) {
            $value < 1000 => (string) $value,
            $value < 1_000_000 => $trim($value / 1000) . 'k',
            $value < 1_000_000_000 => $trim($value / 1_000_000) . 'M',
            default => $trim($value / 1_000_000_000) . 'B',
        };
        return $this->lang === 'pl' ? str_replace('.', ',', $out) : $out;
    }

    public function percent(float $share): string
    {
        $out = sprintf('%.1f%%', $share * 100);
        return $this->lang === 'pl' ? str_replace('.', ',', $out) : $out;
    }

    public function day(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $t = strtotime($date . ' 00:00:00 UTC');
        $month = $this->get('month.' . gmdate('n', $t));
        return $this->lang === 'pl'
            ? gmdate('j', $t) . ' ' . $month . ' ' . gmdate('Y', $t)
            : $month . ' ' . gmdate('j', $t) . ', ' . gmdate('Y', $t);
    }

    public function range(?string $start, ?string $end): string
    {
        if ($start === null || $end === null) {
            return '-';
        }
        if ($start === $end) {
            return $this->day($start);
        }
        if (substr($start, 0, 4) === substr($end, 0, 4)) {
            $t = strtotime($start . ' 00:00:00 UTC');
            $short = $this->lang === 'pl'
                ? gmdate('j', $t) . ' ' . $this->get('month.' . gmdate('n', $t))
                : $this->get('month.' . gmdate('n', $t)) . ' ' . gmdate('j', $t);
            return $short . ' - ' . $this->day($end);
        }
        return $this->day($start) . ' - ' . $this->day($end);
    }
}
