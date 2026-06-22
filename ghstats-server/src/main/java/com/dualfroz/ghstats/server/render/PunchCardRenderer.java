package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.PunchCell;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.server.theme.Theme;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class PunchCardRenderer implements CardRenderer {

    private static final int WIDTH = 740;
    private static final int HEIGHT = 264;
    private static final int GRID_LEFT = 58;
    private static final int GRID_TOP = 78;
    private static final int PITCH_X = 27;
    private static final int PITCH_Y = 20;
    private static final double RADIUS_MIN = 2.2;
    private static final double RADIUS_MAX = 9.5;
    private static final double GAMMA = 0.7;
    private static final String[] DAYS = {"Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"};

    @Override
    public String key() {
        return "punchcard";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        List<PunchCell> punchCard = snapshot.punchCard();

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, "When I Code"));
        body.append(Svg.text(25, 56, theme.muted(), 11, 400,
                "Commits by weekday (rows) and hour of day (columns), author local time"));

        if (punchCard == null || punchCard.isEmpty()) {
            body.append(Svg.text(25, 100, theme.muted(), 14, 400, "No commit-time data available."));
            return Svg.document(WIDTH, 130, theme, options.hideBorder(),
                    "When I code - no data", body.toString());
        }

        int[][] grid = new int[7][24];
        int max = 1;
        for (PunchCell cell : punchCard) {
            if (cell.weekday() >= 1 && cell.weekday() <= 7 && cell.hour() >= 0 && cell.hour() <= 23) {
                grid[cell.weekday() - 1][cell.hour()] = cell.count();
                max = Math.max(max, cell.count());
            }
        }

        body.append(weekdayLabels(theme));
        body.append(hourLabels(theme));
        body.append(bubbles(grid, max, theme));
        body.append(legend(max, theme));

        return Svg.document(WIDTH, HEIGHT, theme, options.hideBorder(),
                "Commit punch card by weekday and hour", body.toString());
    }

    private double radiusFor(double fraction) {
        return RADIUS_MIN + Math.pow(fraction, GAMMA) * (RADIUS_MAX - RADIUS_MIN);
    }

    private double opacityFor(double fraction) {
        return 0.35 + 0.65 * fraction;
    }

    private String weekdayLabels(Theme theme) {
        StringBuilder svg = new StringBuilder();
        for (int row = 0; row < 7; row++) {
            int y = GRID_TOP + row * PITCH_Y + PITCH_Y / 2 + 4;
            svg.append(Svg.textAnchored(GRID_LEFT - 10, y, theme.muted(), 10, 400, "end", DAYS[row]));
        }
        return svg.toString();
    }

    private String hourLabels(Theme theme) {
        StringBuilder svg = new StringBuilder();
        int labelY = GRID_TOP + 7 * PITCH_Y + 16;
        for (int hour = 0; hour <= 23; hour += 3) {
            double x = GRID_LEFT + hour * PITCH_X + PITCH_X / 2.0;
            svg.append(Svg.textAnchored(x, labelY, theme.muted(), 9, 400, "middle", String.valueOf(hour)));
        }
        return svg.toString();
    }

    private String bubbles(int[][] grid, int max, Theme theme) {
        StringBuilder svg = new StringBuilder();
        for (int row = 0; row < 7; row++) {
            for (int hour = 0; hour < 24; hour++) {
                int count = grid[row][hour];
                if (count <= 0) {
                    continue;
                }
                double fraction = (double) count / max;
                double cx = GRID_LEFT + hour * PITCH_X + PITCH_X / 2.0;
                double cy = GRID_TOP + row * PITCH_Y + PITCH_Y / 2.0;
                svg.append("<circle cx=\"").append(Svg.trim(cx)).append("\" cy=\"").append(Svg.trim(cy))
                        .append("\" r=\"").append(Svg.trim(radiusFor(fraction))).append("\" fill=\"")
                        .append(theme.icon()).append("\" fill-opacity=\"")
                        .append(Svg.trim(round(opacityFor(fraction)))).append("\"/>");
            }
        }
        return svg.toString();
    }

    private String legend(int max, Theme theme) {
        StringBuilder svg = new StringBuilder();
        int y = GRID_TOP + 7 * PITCH_Y + 40;
        double x = WIDTH - 236;
        double[] fractions = {0.05, 0.3, 0.55, 0.8, 1.0};
        svg.append(Svg.textAnchored(x - 10, y + 4, theme.muted(), 11, 400, "end", "Less"));
        for (int i = 0; i < fractions.length; i++) {
            double cx = x + i * 26;
            svg.append("<circle cx=\"").append(Svg.trim(cx)).append("\" cy=\"").append(y)
                    .append("\" r=\"").append(Svg.trim(radiusFor(fractions[i]))).append("\" fill=\"")
                    .append(theme.icon()).append("\" fill-opacity=\"")
                    .append(Svg.trim(round(opacityFor(fractions[i])))).append("\"/>");
        }
        svg.append(Svg.text(x + 5 * 26 - 4, y + 4, theme.muted(), 11, 400, "More"));
        svg.append(Svg.textAnchored(GRID_LEFT, y + 4, theme.muted(), 10, 400, "start",
                "peak " + Format.grouped(max) + " commits/hour"));
        return svg.toString();
    }

    private double round(double value) {
        return Math.round(value * 100.0) / 100.0;
    }
}
