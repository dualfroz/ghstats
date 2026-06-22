package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.Streak;
import com.dualfroz.ghstats.server.theme.Theme;
import org.springframework.stereotype.Component;

@Component
public class StreakCardRenderer implements CardRenderer {

    @Override
    public String key() {
        return "streak";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        Streak streak = snapshot.streak();
        int total = snapshot.totals() != null ? snapshot.totals().ownCommits() : 0;
        int width = 495;
        int height = 195;

        StringBuilder body = new StringBuilder();
        body.append("<line x1=\"165\" y1=\"35\" x2=\"165\" y2=\"160\" stroke=\"")
                .append(theme.border()).append("\" stroke-width=\"1\"/>");
        body.append("<line x1=\"330\" y1=\"35\" x2=\"330\" y2=\"160\" stroke=\"")
                .append(theme.border()).append("\" stroke-width=\"1\"/>");

        body.append(totalPanel(theme, 82, total));
        body.append(currentPanel(theme, 247, streak));
        body.append(longestPanel(theme, 412, streak));

        return Svg.document(width, height, theme, options.hideBorder(),
                "Contribution streak", body.toString());
    }

    private String totalPanel(Theme theme, int cx, int total) {
        StringBuilder svg = new StringBuilder();
        svg.append("<g class=\"fadein\" style=\"animation-delay:0.1s\">");
        svg.append(Svg.textAnchored(cx, 82, theme.text(), 34, 700, "middle", Format.grouped(total)));
        svg.append(Svg.textAnchored(cx, 120, theme.title(), 14, 600, "middle", "Total Contributions"));
        svg.append(Svg.textAnchored(cx, 142, theme.muted(), 12, 400, "middle", "all time"));
        svg.append("</g>");
        return svg.toString();
    }

    private String currentPanel(Theme theme, int cx, Streak streak) {
        StringBuilder svg = new StringBuilder();
        svg.append("<g class=\"fadein\" style=\"animation-delay:0.2s\">");
        svg.append("<circle cx=\"").append(cx).append("\" cy=\"72\" r=\"38\" fill=\"none\" stroke=\"")
                .append(theme.title()).append("\" stroke-width=\"3\"/>");
        svg.append(Svg.textAnchored(cx, 82, theme.text(), 34, 700, "middle",
                Format.grouped(streak.current())));
        svg.append(Svg.textAnchored(cx, 132, theme.title(), 14, 600, "middle", "Current Streak"));
        svg.append(Svg.textAnchored(cx, 154, theme.muted(), 11, 400, "middle",
                Format.range(streak.currentStart(), streak.currentEnd())));
        svg.append("</g>");
        return svg.toString();
    }

    private String longestPanel(Theme theme, int cx, Streak streak) {
        StringBuilder svg = new StringBuilder();
        svg.append("<g class=\"fadein\" style=\"animation-delay:0.3s\">");
        svg.append(Svg.textAnchored(cx, 82, theme.text(), 34, 700, "middle",
                Format.grouped(streak.longest())));
        svg.append(Svg.textAnchored(cx, 120, theme.title(), 14, 600, "middle", "Longest Streak"));
        svg.append(Svg.textAnchored(cx, 142, theme.muted(), 12, 400, "middle", "consecutive days"));
        svg.append("</g>");
        return svg.toString();
    }
}
