package com.dualfroz.ghstats.server.render;

import static org.junit.jupiter.api.Assertions.assertTrue;

import com.dualfroz.ghstats.common.model.CalendarDay;
import com.dualfroz.ghstats.common.model.Identity;
import com.dualfroz.ghstats.common.model.LanguageByLines;
import com.dualfroz.ghstats.common.model.PunchCell;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.Streak;
import com.dualfroz.ghstats.common.model.Totals;
import com.dualfroz.ghstats.common.model.TopRepo;
import com.dualfroz.ghstats.common.model.YearCount;
import com.dualfroz.ghstats.server.theme.Themes;
import java.time.Instant;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;
import org.junit.jupiter.api.Test;

class RenderersTest {

    private final RenderOptions options =
            new RenderOptions(Themes.resolve("tokyonight"), false, RenderOptions.RANGE_YEAR);

    private Snapshot sample() {
        List<CalendarDay> calendar = new ArrayList<>();
        LocalDate today = LocalDate.of(2026, 8, 25);
        for (int i = 370; i >= 0; i--) {
            calendar.add(new CalendarDay(today.minusDays(i), i % 5));
        }
        List<CalendarDay> calendarAll = new ArrayList<>();
        for (int year = 2021; year <= 2026; year++) {
            for (int day = 1; day <= 20; day++) {
                calendarAll.add(new CalendarDay(LocalDate.of(year, 3, day), (day % 4) + 1));
            }
        }
        return new Snapshot(
                Instant.parse("2026-08-25T18:00:00Z"),
                new Identity("dualfroz", "DualFroz"),
                new Totals(12345, 34, 186, 210, 8, 987654, 512, 42, 34, 1234567, 234567),
                new Streak(7, 40, LocalDate.of(2026, 8, 19), LocalDate.of(2026, 8, 25)),
                List.of(
                        new LanguageByLines("TypeScript", 400000, 0.4, "#3178c6"),
                        new LanguageByLines("Rust", 250000, 0.25, "#dea584"),
                        new LanguageByLines("Go", 150000, 0.15, "#00ADD8"),
                        new LanguageByLines("Python", 100000, 0.10, "#3572A5"),
                        new LanguageByLines("PHP", 87654, 0.10, "#4F5D95")),
                calendar,
                calendarAll,
                List.of(new YearCount("2020", 420), new YearCount("2021", 800),
                        new YearCount("2022", 2200), new YearCount("2023", 3100),
                        new YearCount("2024", 2600), new YearCount("2025", 2400),
                        new YearCount("2026", 1245)),
                List.of(new TopRepo("dualfroz/secret", 7044), new TopRepo("dualfroz/ghstats", 320),
                        new TopRepo("VulCodeCom/panel", 210)),
                List.of(new PunchCell(1, 9, 5), new PunchCell(3, 14, 12),
                        new PunchCell(6, 22, 3), new PunchCell(5, 10, 8)));
    }

    @Test
    void statsCardRenders() {
        String svg = new StatsCardRenderer().render(sample(), options);
        assertTrue(svg.startsWith("<svg"));
        assertTrue(svg.contains("width=\"495\" height=\"216\""));
        assertTrue(svg.contains("DualFroz&#39;s GitHub Stats"));
        assertTrue(svg.contains("12,345"));
        assertTrue(svg.contains("987,654"));
        assertTrue(svg.contains("+1,234,567"));
        assertTrue(svg.contains("-234,567"));
    }

    @Test
    void topLangsPieMatchesStatsSize() {
        String svg = new TopLangsCardRenderer().render(sample(), options);
        assertTrue(svg.contains("width=\"495\" height=\"216\""));
        assertTrue(svg.contains("Most Used Languages"));
        assertTrue(svg.contains("TypeScript"));
        assertTrue(svg.contains("<path d=\"M"));
        assertTrue(svg.contains("40.0%"));
    }

    @Test
    void locCardMatchesStatsSize() {
        String svg = new LocCardRenderer().render(sample(), options);
        assertTrue(svg.contains("width=\"495\" height=\"216\""));
        assertTrue(svg.contains("Lines of Code"));
        assertTrue(svg.contains("987,654"));
        assertTrue(svg.contains("TypeScript"));
    }

    @Test
    void heatmapYearRendersCells() {
        String svg = new HeatmapCardRenderer().render(sample(), options);
        assertTrue(svg.contains("Contribution Heatmap"));
        assertTrue(svg.contains("<rect"));
        assertTrue(svg.contains("Less"));
        assertTrue(svg.contains("More"));
    }

    @Test
    void heatmapAllTimeRendersYearBlocks() {
        RenderOptions allTime =
                new RenderOptions(Themes.resolve("tokyonight"), false, RenderOptions.RANGE_ALL);
        String svg = new HeatmapCardRenderer().render(sample(), allTime);
        assertTrue(svg.contains("all time"));
        assertTrue(svg.contains(">2021</text>"));
        assertTrue(svg.contains(">2026</text>"));
        assertTrue(svg.contains("<rect"));
    }

    @Test
    void byYearCardRenders() {
        String svg = new ByYearCardRenderer().render(sample(), options);
        assertTrue(svg.contains("width=\"495\" height=\"216\""));
        assertTrue(svg.contains("Commits per Year"));
        assertTrue(svg.contains(">2026</text>"));
        assertTrue(svg.contains("<rect"));
    }

    @Test
    void topReposCardRenders() {
        String svg = new TopReposCardRenderer().render(sample(), options);
        assertTrue(svg.contains("Top Repositories"));
        assertTrue(svg.contains(">secret</text>"));
        assertTrue(svg.contains("7,044"));
    }

    @Test
    void punchCardRenders() {
        String svg = new PunchCardRenderer().render(sample(), options);
        assertTrue(svg.contains("When I Code"));
        assertTrue(svg.contains("<circle"));
        assertTrue(svg.contains(">Mon</text>"));
    }

    @Test
    void achievementsCardRenders() {
        String svg = new AchievementsCardRenderer().render(sample(), options);
        assertTrue(svg.contains("Achievements"));
        assertTrue(svg.contains("Years of Code"));
        assertTrue(svg.contains("Commits"));
        assertTrue(svg.contains("Marathoner"));
    }

    @Test
    void activityAllTimeUsesYears() {
        RenderOptions allTime =
                new RenderOptions(Themes.resolve("tokyonight"), false, RenderOptions.RANGE_ALL);
        String svg = new ActivityCardRenderer().render(sample(), allTime);
        assertTrue(svg.contains("all time"));
        assertTrue(svg.contains("2021"));
        assertTrue(svg.contains("2026"));
    }

    @Test
    void noDataCardWhenSnapshotEmpty() {
        Snapshot empty = new Snapshot(Instant.now(), new Identity("x", "x"), null,
                new Streak(0, 0, null, null), List.of(), List.of(), List.of(), List.of(),
                List.of(), List.of());
        assertTrue(empty.isEmpty());
        String svg = NoDataCard.render("stats", options.theme(), false);
        assertTrue(svg.contains("No data available"));
    }
}
