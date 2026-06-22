package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.Streak;
import com.dualfroz.ghstats.common.model.Totals;
import com.dualfroz.ghstats.common.model.YearCount;
import com.dualfroz.ghstats.server.theme.Theme;
import java.time.LocalDate;
import java.time.ZoneOffset;
import java.util.ArrayList;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class AchievementsCardRenderer implements CardRenderer {

    private static final int WIDTH = 495;
    private static final int COLUMNS = 3;
    private static final int TILE_WIDTH = 145;
    private static final int TILE_HEIGHT = 76;
    private static final int COLUMN_GAP = 12;
    private static final int ROW_GAP = 14;
    private static final int LEFT = 23;
    private static final int FIRST_TOP = 58;

    private record Badge(String value, String label) {
    }

    @Override
    public String key() {
        return "achievements";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, "Achievements"));
        body.append("<line x1=\"25\" y1=\"48\" x2=\"").append(WIDTH - 25)
                .append("\" y2=\"48\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");

        Totals totals = snapshot.totals();
        if (totals == null) {
            body.append(Svg.text(25, 90, theme.muted(), 14, 400, "No achievement data available."));
            return Svg.document(WIDTH, 120, theme, options.hideBorder(),
                    "Achievements - no data", body.toString());
        }

        List<Badge> badges = buildBadges(snapshot, totals);
        int rows = (badges.size() + COLUMNS - 1) / COLUMNS;
        int height = FIRST_TOP + rows * (TILE_HEIGHT + ROW_GAP) + 6;

        for (int i = 0; i < badges.size(); i++) {
            int col = i % COLUMNS;
            int row = i / COLUMNS;
            int x = LEFT + col * (TILE_WIDTH + COLUMN_GAP);
            int y = FIRST_TOP + row * (TILE_HEIGHT + ROW_GAP);
            body.append(tile(badges.get(i), x, y, theme, 0.12 + i * 0.05));
        }

        return Svg.document(WIDTH, height, theme, options.hideBorder(),
                "GitHub achievements", body.toString());
    }

    private List<Badge> buildBadges(Snapshot snapshot, Totals totals) {
        List<Badge> badges = new ArrayList<>();
        int currentYear = snapshot.generatedAt() != null
                ? snapshot.generatedAt().atOffset(ZoneOffset.UTC).getYear()
                : LocalDate.now().getYear();
        int minYear = minYear(snapshot.byYear(), currentYear);
        Streak streak = snapshot.streak();
        int longest = streak != null ? streak.longest() : 0;

        badges.add(new Badge(String.valueOf(currentYear - minYear + 1), "Years of Code"));
        badges.add(new Badge(Format.compact(totals.ownCommits()), "Commits"));
        badges.add(new Badge(Format.grouped(totals.reposOwn()), "Repositories"));
        badges.add(new Badge(String.valueOf(totals.languages()), "Languages"));
        badges.add(new Badge(String.valueOf(longest), "Day Streak"));
        badges.add(new Badge(Format.compact(totals.linesOfCode()), "Lines of Code"));
        if (totals.languages() >= 10) {
            badges.add(new Badge("Polyglot", "10+ Languages"));
        }
        if (longest >= 30) {
            badges.add(new Badge("Marathoner", "30+ Day Streak"));
        }
        return badges;
    }

    private int minYear(List<YearCount> byYear, int fallback) {
        if (byYear == null || byYear.isEmpty()) {
            return fallback;
        }
        int min = fallback;
        for (YearCount year : byYear) {
            try {
                min = Math.min(min, Integer.parseInt(year.year()));
            } catch (NumberFormatException ignored) {
                // skip malformed year labels
            }
        }
        return min;
    }

    private String tile(Badge badge, int x, int y, Theme theme, double delay) {
        int valueFont = badge.value().length() <= 6 ? 24 : 16;
        StringBuilder svg = new StringBuilder();
        svg.append("<g class=\"fadein\" style=\"animation-delay:").append(Svg.trim(delay)).append("s\">");
        svg.append("<rect x=\"").append(x).append("\" y=\"").append(y)
                .append("\" width=\"").append(TILE_WIDTH).append("\" height=\"").append(TILE_HEIGHT)
                .append("\" rx=\"8\" fill=\"none\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");
        svg.append("<circle cx=\"").append(x + 16).append("\" cy=\"").append(y + 16)
                .append("\" r=\"4\" fill=\"").append(theme.icon()).append("\"/>");
        svg.append(Svg.textAnchored(x + TILE_WIDTH / 2.0, y + 46, theme.title(), valueFont, 700, "middle",
                badge.value()));
        svg.append(Svg.textAnchored(x + TILE_WIDTH / 2.0, y + 64, theme.muted(), 11, 400, "middle",
                badge.label()));
        svg.append("</g>");
        return svg.toString();
    }
}
