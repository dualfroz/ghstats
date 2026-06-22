package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.CalendarDay;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.YearCount;
import com.dualfroz.ghstats.server.theme.Theme;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import org.springframework.stereotype.Component;

@Component
public class ActivityCardRenderer implements CardRenderer {

    private static final DateTimeFormatter MONTH = DateTimeFormatter.ofPattern("MMM ''yy", Locale.ENGLISH);
    private static final int WEEKS = 53;

    private record Series(List<Double> values, List<String> labels) {
    }

    @Override
    public String key() {
        return "activity";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        int width = 495;
        int height = 200;

        boolean allTime = options.allTime();
        Series series = allTime ? buildYearly(snapshot.byYear()) : buildWeekly(snapshot.calendar());
        String title = allTime ? "Contribution Activity (all time)" : "Contribution Activity (52 weeks)";

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, title));

        if (series.values().isEmpty()) {
            body.append(Svg.text(25, 90, theme.muted(), 14, 400, "No activity data available."));
            return Svg.document(width, 120, theme, options.hideBorder(), title, body.toString());
        }

        body.append(chart(series, theme, width, height));
        return Svg.document(width, height, theme, options.hideBorder(), title, body.toString());
    }

    private Series buildWeekly(List<CalendarDay> calendar) {
        List<Double> values = new ArrayList<>();
        List<String> labels = new ArrayList<>();
        if (calendar == null || calendar.isEmpty()) {
            return new Series(values, labels);
        }
        int total = calendar.size();
        int fromIndex = Math.max(0, total - WEEKS * 7);
        List<CalendarDay> window = calendar.subList(fromIndex, total);

        for (int bucket = 0; bucket < window.size(); bucket += 7) {
            int end = Math.min(bucket + 7, window.size());
            int sum = 0;
            for (int i = bucket; i < end; i++) {
                sum += window.get(i).count();
            }
            values.add((double) sum);
            labels.add("");
        }

        int buckets = values.size();
        int ticks = Math.min(6, buckets);
        for (int t = 0; t < ticks; t++) {
            int bucketIndex = ticks == 1 ? 0 : (int) Math.round((double) t * (buckets - 1) / (ticks - 1));
            int dayIndex = Math.min(bucketIndex * 7, window.size() - 1);
            labels.set(bucketIndex, MONTH.format(window.get(dayIndex).date()));
        }
        return new Series(values, labels);
    }

    private Series buildYearly(List<YearCount> byYear) {
        List<Double> values = new ArrayList<>();
        List<String> labels = new ArrayList<>();
        if (byYear == null) {
            return new Series(values, labels);
        }
        for (YearCount year : byYear) {
            values.add((double) year.count());
            labels.add(year.year());
        }
        return new Series(values, labels);
    }

    private String chart(Series series, Theme theme, int width, int height) {
        List<Double> values = series.values();
        double plotLeft = 45;
        double plotRight = width - 20;
        double plotTop = 60;
        double plotBottom = height - 28;
        double plotWidth = plotRight - plotLeft;
        double plotHeight = plotBottom - plotTop;

        double max = Math.max(1.0, values.stream().mapToDouble(Double::doubleValue).max().orElse(1.0));
        int n = values.size();

        StringBuilder svg = new StringBuilder();
        svg.append("<defs><linearGradient id=\"ghsActivity\" x1=\"0\" y1=\"0\" x2=\"0\" y2=\"1\">")
                .append("<stop offset=\"0\" stop-color=\"").append(theme.title()).append("\" stop-opacity=\"0.35\"/>")
                .append("<stop offset=\"1\" stop-color=\"").append(theme.title()).append("\" stop-opacity=\"0\"/>")
                .append("</linearGradient></defs>");

        svg.append("<line x1=\"").append(Svg.trim(plotLeft)).append("\" y1=\"").append(Svg.trim(plotBottom))
                .append("\" x2=\"").append(Svg.trim(plotRight)).append("\" y2=\"").append(Svg.trim(plotBottom))
                .append("\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");
        svg.append(Svg.textAnchored(plotLeft - 8, plotTop + 4, theme.muted(), 11, 400, "end",
                Format.grouped((long) max)));
        svg.append(Svg.textAnchored(plotLeft - 8, plotBottom + 3, theme.muted(), 11, 400, "end", "0"));

        StringBuilder line = new StringBuilder();
        StringBuilder area = new StringBuilder();
        for (int i = 0; i < n; i++) {
            double x = n == 1 ? (plotLeft + plotWidth / 2) : plotLeft + plotWidth * i / (n - 1);
            double y = plotBottom - (values.get(i) / max) * plotHeight;
            line.append(i == 0 ? "M" : " L").append(Svg.trim(x)).append(' ').append(Svg.trim(y));
            if (i == 0) {
                area.append("M").append(Svg.trim(x)).append(' ').append(Svg.trim(plotBottom))
                        .append(" L").append(Svg.trim(x)).append(' ').append(Svg.trim(y));
            } else {
                area.append(" L").append(Svg.trim(x)).append(' ').append(Svg.trim(y));
            }
            String label = series.labels().get(i);
            if (label != null && !label.isEmpty()) {
                svg.append(Svg.textAnchored(x, plotBottom + 18, theme.muted(), 10, 400, "middle", label));
            }
        }
        double lastX = n == 1 ? (plotLeft + plotWidth / 2) : plotRight;
        area.append(" L").append(Svg.trim(lastX)).append(' ').append(Svg.trim(plotBottom)).append(" Z");

        svg.append("<path d=\"").append(area).append("\" fill=\"url(#ghsActivity)\"/>");
        svg.append("<path d=\"").append(line).append("\" fill=\"none\" stroke=\"").append(theme.title())
                .append("\" stroke-width=\"2\" stroke-linejoin=\"round\" stroke-linecap=\"round\"/>");

        for (int i = 0; i < n; i++) {
            double x = n == 1 ? (plotLeft + plotWidth / 2) : plotLeft + plotWidth * i / (n - 1);
            double y = plotBottom - (values.get(i) / max) * plotHeight;
            if (n <= 12) {
                svg.append("<circle cx=\"").append(Svg.trim(x)).append("\" cy=\"").append(Svg.trim(y))
                        .append("\" r=\"2.5\" fill=\"").append(theme.title()).append("\"/>");
            }
        }
        return svg.toString();
    }
}
