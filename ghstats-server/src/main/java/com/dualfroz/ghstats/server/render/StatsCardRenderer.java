package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.Totals;
import com.dualfroz.ghstats.server.theme.Theme;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class StatsCardRenderer implements CardRenderer {

    private record Row(String label, String value) {
    }

    @Override
    public String key() {
        return "stats";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        Totals totals = snapshot.totals();
        int width = 495;
        int height = 216;

        String name = snapshot.identity() != null && snapshot.identity().name() != null
                ? snapshot.identity().name()
                : "GitHub";

        Labels labels = options.labels();
        String cardName = options.nameOr(name);
        List<Row> rows = List.of(
                new Row(labels.get("stats.commits"), Format.grouped(totals.ownCommits())),
                new Row(labels.get("stats.repos"), Format.grouped(totals.reposOwn())),
                new Row(labels.get("stats.stars"), Format.grouped(totals.stars())),
                new Row(labels.get("stats.languages"), Format.grouped(totals.languages())),
                new Row(labels.get("stats.activeDays"), Format.grouped(totals.activeDays())),
                new Row(labels.get("stats.linesWritten"), Format.grouped(totals.linesAdded() - totals.linesRemoved()))
        );

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, labels.get("stats.title", cardName)));
        body.append("<line x1=\"25\" y1=\"50\" x2=\"").append(width - 25)
                .append("\" y2=\"50\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");

        int startY = 78;
        int step = 21;
        for (int index = 0; index < rows.size(); index++) {
            Row row = rows.get(index);
            int y = startY + index * step;
            double delay = 0.15 + index * 0.07;
            body.append("<g class=\"fadein\" style=\"animation-delay:").append(Svg.trim(delay)).append("s\">");
            body.append("<circle cx=\"33\" cy=\"").append(y - 4).append("\" r=\"3.5\" fill=\"")
                    .append(theme.icon()).append("\"/>");
            body.append(Svg.text(48, y, theme.text(), 14, 400, row.label()));
            body.append(Svg.textAnchored(width - 25, y, theme.title(), 14, 700, "end", row.value()));
            body.append("</g>");
        }
        body.append(diffRow(theme, width, startY + rows.size() * step, totals.linesAdded(), totals.linesRemoved(), labels));

        return Svg.document(width, height, theme, options.hideBorder(),
                labels.get("stats.title", cardName), body.toString());
    }

    private String diffRow(Theme theme, int width, int y, long added, long removed, Labels labels) {
        StringBuilder svg = new StringBuilder();
        svg.append("<g class=\"fadein\" style=\"animation-delay:0.57s\">");
        svg.append("<circle cx=\"33\" cy=\"").append(y - 4).append("\" r=\"3.5\" fill=\"")
                .append(theme.icon()).append("\"/>");
        svg.append(Svg.text(48, y, theme.text(), 14, 400, labels.get("stats.linesAddedRemoved")));
        svg.append("<text x=\"").append(width - 25).append("\" y=\"").append(y)
                .append("\" text-anchor=\"end\" font-size=\"14\" font-weight=\"700\">")
                .append("<tspan fill=\"#3fb950\">+").append(Svg.escape(Format.grouped(added))).append("</tspan>")
                .append("<tspan fill=\"").append(theme.muted()).append("\"> / </tspan>")
                .append("<tspan fill=\"#f85149\">-").append(Svg.escape(Format.grouped(removed))).append("</tspan>")
                .append("</text>");
        svg.append("</g>");
        return svg.toString();
    }
}
