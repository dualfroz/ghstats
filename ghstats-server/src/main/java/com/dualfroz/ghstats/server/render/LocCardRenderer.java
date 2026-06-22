package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.common.LanguageColors;
import com.dualfroz.ghstats.common.model.LanguageByLines;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.server.theme.Theme;
import java.util.ArrayList;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class LocCardRenderer implements CardRenderer {

    private static final int TOP_N = 12;

    private record Segment(String name, long lines, double share, String color) {
    }

    @Override
    public String key() {
        return "loc";
    }

    @Override
    public String render(Snapshot snapshot, RenderOptions options) {
        Theme theme = options.theme();
        int width = 495;
        long total = snapshot.totals() != null ? snapshot.totals().linesOfCode() : 0;

        List<Segment> segments = buildSegments(snapshot.languagesByLines());
        int legendRows = segments.size();
        int height = Math.max(150, 150 + legendRows * 22);

        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 37, theme, "Lines of Code"));
        body.append(Svg.text(25, 82, theme.text(), 30, 700, Format.grouped(total)));
        body.append(Svg.text(28 + digitsWidth(total), 82, theme.muted(), 14, 400, "lines"));

        if (segments.isEmpty()) {
            body.append(Svg.text(25, 118, theme.muted(), 13, 400,
                    "No language breakdown available."));
            return Svg.document(width, 140, theme, options.hideBorder(),
                    "Lines of code", body.toString());
        }

        body.append(bar(segments, theme, 25, 100, width - 50, 10));
        body.append(legend(segments, theme, 25, 140));

        return Svg.document(width, height, theme, options.hideBorder(),
                "Lines of code by language", body.toString());
    }

    private List<Segment> buildSegments(List<LanguageByLines> languages) {
        List<Segment> segments = new ArrayList<>();
        if (languages == null || languages.isEmpty()) {
            return segments;
        }
        double coveredShare = 0;
        long coveredLines = 0;
        long grandTotal = languages.stream().mapToLong(LanguageByLines::lines).sum();
        int limit = Math.min(TOP_N, languages.size());
        for (int i = 0; i < limit; i++) {
            LanguageByLines language = languages.get(i);
            segments.add(new Segment(language.name(), language.lines(), language.share(), language.color()));
            coveredShare += language.share();
            coveredLines += language.lines();
        }
        if (languages.size() > limit) {
            double remaining = Math.max(0, 1.0 - coveredShare);
            segments.add(new Segment("Other", grandTotal - coveredLines, remaining, LanguageColors.UNKNOWN));
        }
        return segments;
    }

    private String bar(List<Segment> segments, Theme theme, int x, int y, int barWidth, int barHeight) {
        StringBuilder svg = new StringBuilder();
        svg.append("<defs><clipPath id=\"ghsLocBar\"><rect x=\"").append(x).append("\" y=\"").append(y)
                .append("\" width=\"").append(barWidth).append("\" height=\"").append(barHeight)
                .append("\" rx=\"").append(barHeight / 2).append("\"/></clipPath></defs>");
        svg.append("<g clip-path=\"url(#ghsLocBar)\">");
        double cursor = x;
        for (Segment segment : segments) {
            double segmentWidth = segment.share() * barWidth;
            svg.append("<rect x=\"").append(Svg.trim(cursor)).append("\" y=\"").append(y)
                    .append("\" width=\"").append(Svg.trim(segmentWidth + 0.5)).append("\" height=\"")
                    .append(barHeight).append("\" fill=\"").append(segment.color()).append("\"/>");
            cursor += segmentWidth;
        }
        svg.append("</g>");
        return svg.toString();
    }

    private String legend(List<Segment> segments, Theme theme, int x, int startY) {
        StringBuilder svg = new StringBuilder();
        int y = startY;
        int index = 0;
        for (Segment segment : segments) {
            double delay = 0.15 + index * 0.07;
            svg.append("<g class=\"fadein\" style=\"animation-delay:").append(Svg.trim(delay)).append("s\">");
            svg.append("<rect x=\"").append(x).append("\" y=\"").append(y - 10)
                    .append("\" width=\"12\" height=\"12\" rx=\"3\" fill=\"").append(segment.color()).append("\"/>");
            svg.append(Svg.text(x + 20, y, theme.text(), 13, 400, segment.name()));
            svg.append(Svg.textAnchored(x + 300, y, theme.muted(), 13, 400, "end",
                    Format.grouped(segment.lines())));
            svg.append(Svg.textAnchored(x + 400, y, theme.title(), 13, 600, "end",
                    Format.percent(segment.share())));
            svg.append("</g>");
            y += 22;
            index++;
        }
        return svg.toString();
    }

    private int digitsWidth(long value) {
        return Format.grouped(value).length() * 18;
    }
}
