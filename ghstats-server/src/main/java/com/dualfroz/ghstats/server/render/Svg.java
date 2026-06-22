package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.server.theme.Theme;

public final class Svg {

    public static final String FONT =
            "'Segoe UI', Ubuntu, 'Helvetica Neue', Helvetica, Arial, sans-serif";

    private Svg() {
    }

    public static String document(int width, int height, Theme theme, boolean hideBorder,
                                  String ariaLabel, String body) {
        StringBuilder svg = new StringBuilder(2048);
        svg.append("<svg width=\"").append(width).append("\" height=\"").append(height)
                .append("\" viewBox=\"0 0 ").append(width).append(' ').append(height)
                .append("\" xmlns=\"http://www.w3.org/2000/svg\" role=\"img\" aria-label=\"")
                .append(escape(ariaLabel)).append("\">");
        svg.append("<style>")
                .append("text{font-family:").append(FONT).append(";}")
                .append(".fadein{opacity:0;animation:ghsFade 0.7s ease forwards;}")
                .append("@keyframes ghsFade{from{opacity:0;transform:translateY(6px);}")
                .append("to{opacity:1;transform:translateY(0);}}")
                .append(".grow-up{transform-box:fill-box;transform-origin:center bottom;")
                .append("animation:ghsGrowUp 0.75s cubic-bezier(0.22,1,0.36,1) forwards;}")
                .append("@keyframes ghsGrowUp{from{transform:scaleY(0);}to{transform:scaleY(1);}}")
                .append(".grow-right{transform-box:fill-box;transform-origin:left center;")
                .append("animation:ghsGrowRight 0.75s cubic-bezier(0.22,1,0.36,1) forwards;}")
                .append("@keyframes ghsGrowRight{from{transform:scaleX(0);}to{transform:scaleX(1);}}")
                .append(".pie-grow{transform-box:fill-box;transform-origin:center;opacity:0;")
                .append("animation:ghsPie 0.7s ease forwards;}")
                .append("@keyframes ghsPie{from{transform:scale(0.6);opacity:0;}")
                .append("to{transform:scale(1);opacity:1;}}")
                .append("</style>");
        svg.append("<rect x=\"0.5\" y=\"0.5\" rx=\"6\" width=\"").append(width - 1)
                .append("\" height=\"").append(height - 1)
                .append("\" fill=\"").append(theme.background()).append('"');
        if (!hideBorder) {
            svg.append(" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"");
        }
        svg.append("/>");
        svg.append(body);
        svg.append("</svg>");
        return svg.toString();
    }

    public static String title(int x, int y, Theme theme, String text) {
        return "<text x=\"" + x + "\" y=\"" + y + "\" fill=\"" + theme.title()
                + "\" font-size=\"18\" font-weight=\"600\">" + escape(text) + "</text>";
    }

    public static String text(double x, double y, String fill, int size, int weight, String content) {
        return "<text x=\"" + trim(x) + "\" y=\"" + trim(y) + "\" fill=\"" + fill
                + "\" font-size=\"" + size + "\" font-weight=\"" + weight + "\">" + escape(content) + "</text>";
    }

    public static String textAnchored(double x, double y, String fill, int size, int weight,
                                      String anchor, String content) {
        return "<text x=\"" + trim(x) + "\" y=\"" + trim(y) + "\" fill=\"" + fill
                + "\" font-size=\"" + size + "\" font-weight=\"" + weight
                + "\" text-anchor=\"" + anchor + "\">" + escape(content) + "</text>";
    }

    public static String trim(double value) {
        if (value == Math.rint(value)) {
            return String.valueOf((long) value);
        }
        return String.format(java.util.Locale.US, "%.2f", value);
    }

    public static String escape(String raw) {
        if (raw == null) {
            return "";
        }
        StringBuilder out = new StringBuilder(raw.length());
        for (int i = 0; i < raw.length(); i++) {
            char c = raw.charAt(i);
            switch (c) {
                case '&' -> out.append("&amp;");
                case '<' -> out.append("&lt;");
                case '>' -> out.append("&gt;");
                case '"' -> out.append("&quot;");
                case '\'' -> out.append("&#39;");
                default -> out.append(c);
            }
        }
        return out.toString();
    }
}
