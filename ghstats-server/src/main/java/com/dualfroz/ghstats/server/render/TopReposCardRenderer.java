package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.TopRepo;
import com.dualfroz.ghstats.server.theme.Theme;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class TopReposCardRenderer implements CardRenderer {

    private static final int WIDTH = 495;
    private static final int ROW_HEIGHT = 28;
    private static final double BAR_START = 150;
    private static final double BAR_MAX_END = 405;

    @Override
    public String key() {
        return "top-repos";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        List<TopRepo> topRepos = snapshot.topRepos();

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, "Top Repositories"));
        body.append("<line x1=\"25\" y1=\"50\" x2=\"").append(WIDTH - 25)
                .append("\" y2=\"50\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");

        if (topRepos == null || topRepos.isEmpty()) {
            body.append(Svg.text(25, 95, theme.muted(), 14, 400, "No repository data available."));
            return Svg.document(WIDTH, 130, theme, options.hideBorder(),
                    "Top repositories - no data", body.toString());
        }

        int n = topRepos.size();
        int firstCenter = 72;
        int height = firstCenter + (n - 1) * ROW_HEIGHT + 26;
        long max = Math.max(1, topRepos.stream().mapToInt(TopRepo::ownCommits).max().orElse(1));
        double barSpan = BAR_MAX_END - BAR_START;

        for (int i = 0; i < n; i++) {
            TopRepo repo = topRepos.get(i);
            int center = firstCenter + i * ROW_HEIGHT;
            double barWidth = Math.max(2, (double) repo.ownCommits() / max * barSpan);
            double delay = 0.12 + i * 0.05;
            body.append("<g class=\"fadein\" style=\"animation-delay:").append(Svg.trim(delay)).append("s\">");
            body.append(Svg.text(25, center + 4, theme.text(), 13, 400, basename(repo.name())));
            body.append("<rect x=\"").append(Svg.trim(BAR_START)).append("\" y=\"").append(center - 7)
                    .append("\" width=\"").append(Svg.trim(barWidth)).append("\" height=\"14\" rx=\"4\" fill=\"")
                    .append(theme.title()).append("\" class=\"grow-right\" style=\"animation-delay:")
                    .append(Svg.trim(delay)).append("s\"/>");
            body.append(Svg.textAnchored(WIDTH - 25, center + 4, theme.icon(), 12, 700, "end",
                    Format.grouped(repo.ownCommits())));
            body.append("</g>");
        }

        return Svg.document(WIDTH, height, theme, options.hideBorder(),
                "Top repositories by commits", body.toString());
    }

    private String basename(String name) {
        int slash = name.lastIndexOf('/');
        return slash >= 0 ? name.substring(slash + 1) : name;
    }
}
