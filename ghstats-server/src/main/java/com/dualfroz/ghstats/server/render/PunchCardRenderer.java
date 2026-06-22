package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.PunchCell;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.server.theme.Theme;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class PunchCardRenderer implements CardRenderer {

    private static final int WIDTH = 740;
    private static final int HEIGHT = 220;
    private static final int GRID_LEFT = 58;
    private static final int GRID_TOP = 68;
    private static final int PITCH_X = 27;
    private static final int PITCH_Y = 18;
    private static final double RADIUS_MAX = 8.0;
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

        if (punchCard == null || punchCard.isEmpty()) {
            body.append(Svg.text(25, 90, theme.muted(), 14, 400, "No commit-time data available."));
            return Svg.document(WIDTH, 120, theme, options.hideBorder(),
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

        return Svg.document(WIDTH, HEIGHT, theme, options.hideBorder(),
                "Commit punch card by weekday and hour", body.toString());
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
                double cx = GRID_LEFT + hour * PITCH_X + PITCH_X / 2.0;
                double cy = GRID_TOP + row * PITCH_Y + PITCH_Y / 2.0;
                double radius = Math.max(1.5, RADIUS_MAX * Math.sqrt((double) count / max));
                svg.append("<circle cx=\"").append(Svg.trim(cx)).append("\" cy=\"").append(Svg.trim(cy))
                        .append("\" r=\"").append(Svg.trim(radius)).append("\" fill=\"")
                        .append(theme.icon()).append("\"/>");
            }
        }
        return svg.toString();
    }
}
