package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.YearCount;
import com.dualfroz.ghstats.server.theme.Theme;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class ByYearCardRenderer implements CardRenderer {

    private static final int WIDTH = 495;
    private static final int HEIGHT = 216;

    @Override
    public String key() {
        return "by-year";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        List<YearCount> byYear = snapshot.byYear();

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, options.labels().get("year.title")));
        body.append("<line x1=\"25\" y1=\"50\" x2=\"").append(WIDTH - 25)
                .append("\" y2=\"50\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");

        if (byYear == null || byYear.isEmpty()) {
            body.append(Svg.text(25, 95, theme.muted(), 14, 400, options.labels().get("year.empty")));
            return Svg.document(WIDTH, HEIGHT, theme, options.hideBorder(),
                    options.labels().get("year.emptyTitle"), body.toString());
        }

        double plotLeft = 40;
        double plotRight = WIDTH - 20;
        double plotTop = 70;
        double plotBottom = 178;
        double plotWidth = plotRight - plotLeft;
        double plotHeight = plotBottom - plotTop;

        long max = Math.max(1, byYear.stream().mapToLong(YearCount::count).max().orElse(1));
        int n = byYear.size();
        double slot = plotWidth / n;
        double barWidth = slot * 0.6;

        body.append("<line x1=\"").append(Svg.trim(plotLeft)).append("\" y1=\"").append(Svg.trim(plotBottom))
                .append("\" x2=\"").append(Svg.trim(plotRight)).append("\" y2=\"").append(Svg.trim(plotBottom))
                .append("\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");

        for (int i = 0; i < n; i++) {
            YearCount year = byYear.get(i);
            double barHeight = (double) year.count() / max * plotHeight;
            double barX = plotLeft + i * slot + (slot - barWidth) / 2;
            double barY = plotBottom - barHeight;
            double centerX = barX + barWidth / 2;
            double delay = 0.15 + i * 0.07;
            body.append("<g class=\"fadein\" style=\"animation-delay:").append(Svg.trim(delay)).append("s\">");
            body.append("<rect x=\"").append(Svg.trim(barX)).append("\" y=\"").append(Svg.trim(barY))
                    .append("\" width=\"").append(Svg.trim(barWidth)).append("\" height=\"")
                    .append(Svg.trim(barHeight)).append("\" rx=\"3\" fill=\"").append(theme.title())
                    .append("\" class=\"grow-up\" style=\"animation-delay:").append(Svg.trim(delay)).append("s\"/>");
            body.append(Svg.textAnchored(centerX, barY - 6, theme.text(), 11, 600, "middle",
                    Format.grouped(year.count())));
            body.append(Svg.textAnchored(centerX, plotBottom + 17, theme.muted(), 11, 400, "middle",
                    year.year()));
            body.append("</g>");
        }

        return Svg.document(WIDTH, HEIGHT, theme, options.hideBorder(),
                options.labels().get("year.alt"), body.toString());
    }
}
