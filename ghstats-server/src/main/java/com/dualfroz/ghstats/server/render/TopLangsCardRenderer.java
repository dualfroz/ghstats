package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.LanguageColors;
import com.dualfroz.ghstats.common.model.LanguageByLines;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.server.theme.Theme;
import java.util.ArrayList;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class TopLangsCardRenderer implements CardRenderer {

    private static final int TOP_N = 12;

    private record Slice(String name, double share, String color) {
    }

    @Override
    public String key() {
        return "top-langs";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        int width = 360;

        List<LanguageByLines> languages = snapshot.languagesByLines();
        if (languages == null || languages.isEmpty()) {
            StringBuilder empty = new StringBuilder();
            empty.append(Svg.title(25, 37, theme, "Most Used Languages"));
            empty.append(Svg.text(25, 80, theme.muted(), 14, 400, "No language data available."));
            return Svg.document(width, 110, theme, options.hideBorder(),
                    "Most used languages - no data", empty.toString());
        }

        List<Slice> slices = buildSlices(languages);
        int height = Math.max(230, 70 + slices.size() * 24 + 24);

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, "Most Used Languages"));

        double cx = 95;
        double cy = 130;
        double radius = 62;
        body.append(pie(slices, cx, cy, radius, theme));
        body.append(legend(slices, theme, 185, 70));

        return Svg.document(width, height, theme, options.hideBorder(),
                "Most used languages pie chart", body.toString());
    }

    private List<Slice> buildSlices(List<LanguageByLines> languages) {
        List<Slice> slices = new ArrayList<>();
        double coveredShare = 0;
        int limit = Math.min(TOP_N, languages.size());
        for (int i = 0; i < limit; i++) {
            LanguageByLines language = languages.get(i);
            slices.add(new Slice(language.name(), language.share(), language.color()));
            coveredShare += language.share();
        }
        double remaining = 1.0 - coveredShare;
        if (languages.size() > limit && remaining > 0.0005) {
            slices.add(new Slice("Other", remaining, LanguageColors.UNKNOWN));
        }
        return slices;
    }

    private String pie(List<Slice> slices, double cx, double cy, double radius, Theme theme) {
        StringBuilder svg = new StringBuilder();
        if (slices.size() == 1) {
            svg.append("<circle cx=\"").append(Svg.trim(cx)).append("\" cy=\"").append(Svg.trim(cy))
                    .append("\" r=\"").append(Svg.trim(radius)).append("\" fill=\"")
                    .append(slices.get(0).color()).append("\"/>");
            return svg.toString();
        }
        double angle = -Math.PI / 2;
        for (Slice slice : slices) {
            double sweep = slice.share() * 2 * Math.PI;
            double end = angle + sweep;
            double x1 = cx + radius * Math.cos(angle);
            double y1 = cy + radius * Math.sin(angle);
            double x2 = cx + radius * Math.cos(end);
            double y2 = cy + radius * Math.sin(end);
            int largeArc = sweep > Math.PI ? 1 : 0;
            svg.append("<path d=\"M").append(Svg.trim(cx)).append(' ').append(Svg.trim(cy))
                    .append(" L").append(Svg.trim(x1)).append(' ').append(Svg.trim(y1))
                    .append(" A").append(Svg.trim(radius)).append(' ').append(Svg.trim(radius))
                    .append(" 0 ").append(largeArc).append(" 1 ")
                    .append(Svg.trim(x2)).append(' ').append(Svg.trim(y2)).append(" Z\" fill=\"")
                    .append(slice.color()).append("\" stroke=\"").append(theme.background())
                    .append("\" stroke-width=\"1.5\"/>");
            angle = end;
        }
        return svg.toString();
    }

    private String legend(List<Slice> slices, Theme theme, int x, int startY) {
        StringBuilder svg = new StringBuilder();
        int y = startY;
        int index = 0;
        for (Slice slice : slices) {
            double delay = 0.2 + index * 0.07;
            svg.append("<g class=\"fadein\" style=\"animation-delay:").append(Svg.trim(delay)).append("s\">");
            svg.append("<rect x=\"").append(x).append("\" y=\"").append(y - 10)
                    .append("\" width=\"12\" height=\"12\" rx=\"3\" fill=\"").append(slice.color()).append("\"/>");
            svg.append(Svg.text(x + 20, y, theme.text(), 13, 400, slice.name()));
            svg.append(Svg.textAnchored(x + 155, y, theme.muted(), 13, 600, "end",
                    Format.percent(slice.share())));
            svg.append("</g>");
            y += 24;
            index++;
        }
        return svg.toString();
    }
}
