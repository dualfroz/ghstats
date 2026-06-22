package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.Totals;
import com.dualfroz.ghstats.server.theme.Theme;
import org.springframework.stereotype.Component;

@Component
public class LocCardRenderer implements CardRenderer {

    private static final int WIDTH = 495;
    private static final int HEIGHT = 216;
    private static final String ADDED = "#3fb950";
    private static final String REMOVED = "#f85149";

    @Override
    public String key() {
        return "loc";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        Totals totals = snapshot.totals();

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 34, theme, "Lines Written"));
        body.append("<line x1=\"25\" y1=\"46\" x2=\"").append(WIDTH - 25)
                .append("\" y2=\"46\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");

        if (totals == null) {
            body.append(Svg.text(25, 90, theme.muted(), 14, 400, "No line data available."));
            return Svg.document(WIDTH, HEIGHT, theme, options.hideBorder(),
                    "Lines written", body.toString());
        }

        long added = totals.linesAdded();
        long removed = totals.linesRemoved();
        long net = added - removed;

        body.append(Svg.textAnchored(WIDTH / 2.0, 104, theme.title(), 42, 700, "middle", Format.grouped(net)));
        body.append(Svg.textAnchored(WIDTH / 2.0, 128, theme.muted(), 12, 400, "middle",
                "net lines written, all-time"));

        long span = added + removed;
        if (span > 0) {
            body.append(bar(added, removed, theme, 25, 150, WIDTH - 50, 18));
            body.append(Svg.text(25, 196, ADDED, 13, 600, "+" + Format.grouped(added) + " added"));
            body.append(Svg.textAnchored(WIDTH - 25, 196, REMOVED, 13, 600, "end",
                    "-" + Format.grouped(removed) + " removed"));
        }

        return Svg.document(WIDTH, HEIGHT, theme, options.hideBorder(),
                "Lines written net with added and removed", body.toString());
    }

    private String bar(long added, long removed, Theme theme, int x, int y, int barWidth, int barHeight) {
        double addedWidth = (double) added / (added + removed) * barWidth;
        StringBuilder svg = new StringBuilder();
        svg.append("<defs><clipPath id=\"ghsWrittenBar\"><rect x=\"").append(x).append("\" y=\"").append(y)
                .append("\" width=\"").append(barWidth).append("\" height=\"").append(barHeight)
                .append("\" rx=\"").append(barHeight / 2).append("\"/></clipPath></defs>");
        svg.append("<g clip-path=\"url(#ghsWrittenBar)\">");
        svg.append("<rect x=\"").append(x).append("\" y=\"").append(y)
                .append("\" width=\"").append(Svg.trim(addedWidth)).append("\" height=\"").append(barHeight)
                .append("\" fill=\"").append(ADDED).append("\"/>");
        svg.append("<rect x=\"").append(Svg.trim(x + addedWidth)).append("\" y=\"").append(y)
                .append("\" width=\"").append(Svg.trim(barWidth - addedWidth)).append("\" height=\"").append(barHeight)
                .append("\" fill=\"").append(REMOVED).append("\"/>");
        svg.append("</g>");
        return svg.toString();
    }
}
