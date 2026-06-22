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

        List<Row> rows = List.of(
                new Row("Own commits (all-time)", Format.grouped(totals.ownCommits())),
                new Row("Repositories", Format.grouped(totals.reposOwn())),
                new Row("Total stars earned", Format.grouped(totals.stars())),
                new Row("Languages used", Format.grouped(totals.languages())),
                new Row("Active days", Format.grouped(totals.activeDays())),
                new Row("Lines of code", Format.grouped(totals.linesOfCode()))
        );

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, name + "'s GitHub Stats"));
        body.append("<line x1=\"25\" y1=\"50\" x2=\"").append(width - 25)
                .append("\" y2=\"50\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");

        int y = 82;
        int step = 25;
        int index = 0;
        for (Row row : rows) {
            double delay = 0.15 + index * 0.08;
            body.append("<g class=\"fadein\" style=\"animation-delay:").append(Svg.trim(delay)).append("s\">");
            body.append("<circle cx=\"33\" cy=\"").append(y - 4).append("\" r=\"3.5\" fill=\"")
                    .append(theme.icon()).append("\"/>");
            body.append(Svg.text(48, y, theme.text(), 14, 400, row.label()));
            body.append(Svg.textAnchored(width - 25, y, theme.title(), 14, 700, "end", row.value()));
            body.append("</g>");
            y += step;
            index++;
        }

        return Svg.document(width, height, theme, options.hideBorder(),
                name + " GitHub stats overview", body.toString());
    }
}
