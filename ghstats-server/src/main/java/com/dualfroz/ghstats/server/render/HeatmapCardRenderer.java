package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.CalendarDay;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.server.theme.Theme;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.Collections;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import org.springframework.stereotype.Component;

@Component
public class HeatmapCardRenderer implements CardRenderer {

    private static final DateTimeFormatter MONTH = DateTimeFormatter.ofPattern("MMM", Locale.ENGLISH);
    private static final int COLUMNS = 53;
    private static final int ROWS = 7;
    private static final int CELL = 11;
    private static final int PITCH = 13;
    private static final int GRID_LEFT = 32;
    private static final int GRID_TOP = 58;

    @Override
    public String key() {
        return "heatmap";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        int width = 740;
        int height = 190;

        Map<LocalDate, Integer> counts = index(snapshot.calendar());
        LocalDate today = lastDate(snapshot.calendar());

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, "Contribution Heatmap (52 weeks)"));

        if (today == null) {
            body.append(Svg.text(25, 90, theme.muted(), 14, 400, "No contribution data available."));
            return Svg.document(width, 120, theme, options.hideBorder(),
                    "Contribution heatmap - no data", body.toString());
        }

        LocalDate topLeft = today.minusDays(today.getDayOfWeek().getValue() % 7).minusWeeks(COLUMNS - 1L);
        int[] thresholds = quartiles(counts, topLeft, today);

        body.append(monthLabels(topLeft, today, theme));
        body.append(weekdayLabels(theme));
        body.append(cells(counts, topLeft, today, theme, thresholds));
        body.append(legend(theme, width));

        return Svg.document(width, height, theme, options.hideBorder(),
                "Contribution heatmap", body.toString());
    }

    private Map<LocalDate, Integer> index(List<CalendarDay> calendar) {
        Map<LocalDate, Integer> counts = new HashMap<>();
        if (calendar != null) {
            for (CalendarDay day : calendar) {
                counts.put(day.date(), day.count());
            }
        }
        return counts;
    }

    private LocalDate lastDate(List<CalendarDay> calendar) {
        if (calendar == null || calendar.isEmpty()) {
            return null;
        }
        return calendar.get(calendar.size() - 1).date();
    }

    private int[] quartiles(Map<LocalDate, Integer> counts, LocalDate topLeft, LocalDate today) {
        List<Integer> positive = new ArrayList<>();
        for (LocalDate date = topLeft; !date.isAfter(today); date = date.plusDays(1)) {
            int value = counts.getOrDefault(date, 0);
            if (value > 0) {
                positive.add(value);
            }
        }
        if (positive.isEmpty()) {
            return new int[]{1, 2, 3};
        }
        Collections.sort(positive);
        return new int[]{
                positive.get(positive.size() / 4),
                positive.get(positive.size() / 2),
                positive.get(positive.size() * 3 / 4)
        };
    }

    private int level(int count, int[] thresholds) {
        if (count <= 0) {
            return 0;
        }
        if (count <= thresholds[0]) {
            return 1;
        }
        if (count <= thresholds[1]) {
            return 2;
        }
        if (count <= thresholds[2]) {
            return 3;
        }
        return 4;
    }

    private String cells(Map<LocalDate, Integer> counts, LocalDate topLeft, LocalDate today,
                         Theme theme, int[] thresholds) {
        StringBuilder svg = new StringBuilder();
        for (int col = 0; col < COLUMNS; col++) {
            for (int row = 0; row < ROWS; row++) {
                LocalDate date = topLeft.plusWeeks(col).plusDays(row);
                if (date.isAfter(today)) {
                    continue;
                }
                int count = counts.getOrDefault(date, 0);
                String color = theme.heatLevels().get(level(count, thresholds));
                int x = GRID_LEFT + col * PITCH;
                int y = GRID_TOP + row * PITCH;
                svg.append("<rect x=\"").append(x).append("\" y=\"").append(y)
                        .append("\" width=\"").append(CELL).append("\" height=\"").append(CELL)
                        .append("\" rx=\"2\" fill=\"").append(color).append("\"/>");
            }
        }
        return svg.toString();
    }

    private String monthLabels(LocalDate topLeft, LocalDate today, Theme theme) {
        StringBuilder svg = new StringBuilder();
        int previousMonth = -1;
        for (int col = 0; col < COLUMNS; col++) {
            LocalDate columnStart = topLeft.plusWeeks(col);
            if (columnStart.isAfter(today)) {
                break;
            }
            int month = columnStart.getMonthValue();
            if (month != previousMonth) {
                int x = GRID_LEFT + col * PITCH;
                svg.append(Svg.text(x, GRID_TOP - 6, theme.muted(), 10, 400, MONTH.format(columnStart)));
                previousMonth = month;
            }
        }
        return svg.toString();
    }

    private String weekdayLabels(Theme theme) {
        StringBuilder svg = new StringBuilder();
        String[] days = {"Mon", "Wed", "Fri"};
        int[] rows = {1, 3, 5};
        for (int i = 0; i < days.length; i++) {
            int y = GRID_TOP + rows[i] * PITCH + CELL - 2;
            svg.append(Svg.textAnchored(GRID_LEFT - 5, y, theme.muted(), 9, 400, "end", days[i]));
        }
        return svg.toString();
    }

    private String legend(Theme theme, int width) {
        StringBuilder svg = new StringBuilder();
        int y = GRID_TOP + ROWS * PITCH + 14;
        int x = width - 200;
        svg.append(Svg.textAnchored(x - 6, y + 9, theme.muted(), 11, 400, "end", "Less"));
        for (int lvl = 0; lvl < 5; lvl++) {
            svg.append("<rect x=\"").append(x + lvl * 15).append("\" y=\"").append(y)
                    .append("\" width=\"").append(CELL).append("\" height=\"").append(CELL)
                    .append("\" rx=\"2\" fill=\"").append(theme.heatLevels().get(lvl)).append("\"/>");
        }
        svg.append(Svg.text(x + 5 * 15 + 4, y + 9, theme.muted(), 11, 400, "More"));
        return svg.toString();
    }
}
