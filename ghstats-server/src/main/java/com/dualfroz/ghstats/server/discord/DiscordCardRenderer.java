package com.dualfroz.ghstats.server.discord;

import com.dualfroz.ghstats.server.ServerProperties;
import com.dualfroz.ghstats.server.render.Svg;
import java.util.ArrayList;
import java.util.List;
import org.springframework.stereotype.Component;

@Component
public class DiscordCardRenderer {

    private static final int W = 420;
    private static final int BANNER_H = 100;
    private static final String CARD_BG = "#232428";
    private static final String PANEL_BG = "#111214";
    private static final String TEXT = "#f2f3f5";
    private static final String TEXT2 = "#dbdee1";
    private static final String MUTED = "#b5bac1";
    private static final String HAIRLINE = "rgba(255,255,255,0.06)";
    private static final String SPOTIFY = "#1db954";
    private static final int AV_CX = 56;
    private static final int AV_CY = 100;
    private static final int AV_R = 44;

    private final ServerProperties properties;
    private final DiscordBadges badges;

    public DiscordCardRenderer(ServerProperties properties, DiscordBadges badges) {
        this.properties = properties;
        this.badges = badges;
    }

    public String render(LanyardState state, boolean hideBorder) {
        StringBuilder defs = new StringBuilder();
        StringBuilder body = new StringBuilder();

        body.append(banner(state, defs));
        body.append(avatar(state));

        int nameY = 176;
        body.append(Svg.text(20, nameY, TEXT, 22, 700, state.displayName()));
        body.append(clanPill(state, nameY));
        int usernameY = nameY + 22;
        body.append(Svg.text(20, usernameY, MUTED, 15, 400, "@" + state.username()));
        body.append(pronouns(usernameY, "@" + state.username()));
        body.append(badgeBox());

        int y = usernameY + 20;
        y = bio(body, y);
        y = activity(body, defs, state, y);

        int height = y + 16;
        return wrap(height, hideBorder, "Discord profile for " + state.displayName(),
                defs.toString(), body.toString());
    }

    private String banner(LanyardState state, StringBuilder defs) {
        String config = properties.getDiscordBanner();
        if (state.bannerDataUri() != null) {
            return "<image href=\"" + state.bannerDataUri() + "\" x=\"0\" y=\"0\" width=\"" + W
                    + "\" height=\"" + BANNER_H + "\" preserveAspectRatio=\"xMidYMid slice\""
                    + " clip-path=\"url(#dcard)\"/>";
        }
        String fill;
        if (config.startsWith("#")) {
            fill = config;
        } else if (config.contains(",") && !config.startsWith("http")) {
            String[] stops = config.split(",", 2);
            fill = gradient(defs, stops[0].trim(), stops[1].trim());
        } else {
            fill = gradient(defs, "#5865f2", "#404eed");
        }
        return "<rect x=\"0\" y=\"0\" width=\"" + W + "\" height=\"" + BANNER_H + "\" fill=\"" + fill
                + "\" clip-path=\"url(#dcard)\"/>";
    }

    private String badgeBox() {
        List<String> drawable = properties.getDiscordBadges().stream()
                .filter(badges::known).toList();
        if (drawable.isEmpty()) {
            return "";
        }
        int size = 22;
        int gap = 6;
        int padX = 8;
        int padY = 6;
        int n = drawable.size();
        int boxW = padX * 2 + n * size + (n - 1) * gap;
        int boxH = size + padY * 2;
        int boxX = W - 16 - boxW;
        int boxY = 114;
        StringBuilder svg = new StringBuilder();
        svg.append("<rect x=\"").append(boxX).append("\" y=\"").append(boxY).append("\" width=\"").append(boxW)
                .append("\" height=\"").append(boxH).append("\" rx=\"11\" fill=\"rgba(0,0,0,0.45)\"")
                .append(" stroke=\"rgba(255,255,255,0.08)\" stroke-width=\"1\"/>");
        int gx = boxX + padX;
        int gy = boxY + padY;
        for (int i = 0; i < n; i++) {
            svg.append(badges.glyph(drawable.get(i), gx + i * (size + gap), gy, size));
        }
        return svg.toString();
    }

    private String gradient(StringBuilder defs, String from, String to) {
        defs.append("<linearGradient id=\"dbanner\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"1\">")
                .append("<stop offset=\"0\" stop-color=\"").append(from).append("\"/>")
                .append("<stop offset=\"1\" stop-color=\"").append(to).append("\"/></linearGradient>");
        return "url(#dbanner)";
    }

    private String avatar(LanyardState state) {
        StringBuilder svg = new StringBuilder();
        svg.append("<circle cx=\"").append(AV_CX).append("\" cy=\"").append(AV_CY)
                .append("\" r=\"").append(AV_R + 6).append("\" fill=\"").append(CARD_BG).append("\"/>");
        svg.append("<clipPath id=\"dav\"><circle cx=\"").append(AV_CX).append("\" cy=\"").append(AV_CY)
                .append("\" r=\"").append(AV_R).append("\"/></clipPath>");
        if (state.avatarDataUri() != null) {
            svg.append("<image href=\"").append(state.avatarDataUri()).append("\" x=\"").append(AV_CX - AV_R)
                    .append("\" y=\"").append(AV_CY - AV_R).append("\" width=\"").append(AV_R * 2)
                    .append("\" height=\"").append(AV_R * 2)
                    .append("\" preserveAspectRatio=\"xMidYMid slice\" clip-path=\"url(#dav)\"/>");
        } else {
            svg.append("<circle cx=\"").append(AV_CX).append("\" cy=\"").append(AV_CY)
                    .append("\" r=\"").append(AV_R).append("\" fill=\"#5865f2\"/>");
            svg.append(Svg.textAnchored(AV_CX, AV_CY + 9, "#ffffff", 30, 700, "middle", initials(state)));
        }
        svg.append("<circle cx=\"").append(AV_CX).append("\" cy=\"").append(AV_CY).append("\" r=\"").append(AV_R)
                .append("\" fill=\"none\" stroke=\"rgba(0,0,0,0.15)\" stroke-width=\"1\"/>");

        int dotCx = AV_CX + 31;
        int dotCy = AV_CY + 31;
        svg.append("<circle cx=\"").append(dotCx).append("\" cy=\"").append(dotCy)
                .append("\" r=\"13\" fill=\"").append(CARD_BG).append("\"/>");
        svg.append("<circle cx=\"").append(dotCx).append("\" cy=\"").append(dotCy)
                .append("\" r=\"8.5\" fill=\"").append(statusColor(state.status())).append("\"/>");
        return svg.toString();
    }

    private String clanPill(LanyardState state, int nameY) {
        if (state.clanTag() == null || state.clanTag().isBlank()) {
            return "";
        }
        String tag = state.clanTag();
        boolean hasBadge = state.clanBadgeDataUri() != null;
        int pillX = 20 + (int) (state.displayName().length() * 12.3) + 10;
        int pillH = 22;
        int pillY = nameY - 17;
        int badgeW = hasBadge ? 18 : 0;
        int pillW = 12 + badgeW + (int) (tag.length() * 8.2);

        StringBuilder svg = new StringBuilder();
        svg.append("<rect x=\"").append(pillX).append("\" y=\"").append(pillY)
                .append("\" width=\"").append(pillW).append("\" height=\"").append(pillH)
                .append("\" rx=\"6\" fill=\"rgba(255,255,255,0.08)\"/>");
        int textX = pillX + 8;
        if (hasBadge) {
            svg.append("<image href=\"").append(state.clanBadgeDataUri()).append("\" x=\"").append(pillX + 5)
                    .append("\" y=\"").append(pillY + 4).append("\" width=\"14\" height=\"14\"/>");
            textX = pillX + 5 + 14 + 4;
        }
        svg.append(Svg.text(textX, pillY + 15, TEXT2, 12, 700, tag));
        return svg.toString();
    }

    private String pronouns(int usernameY, String username) {
        String value = properties.getDiscordPronouns();
        if (value == null || value.isBlank()) {
            return "";
        }
        int sepX = 20 + (int) (username.length() * 8.1) + 9;
        StringBuilder svg = new StringBuilder();
        svg.append("<circle cx=\"").append(sepX).append("\" cy=\"").append(usernameY - 4)
                .append("\" r=\"1.6\" fill=\"").append(MUTED).append("\"/>");
        svg.append(Svg.text(sepX + 8, usernameY, MUTED, 13, 400, value));
        return svg.toString();
    }

    private int bio(StringBuilder body, int y) {
        String value = properties.getDiscordBio();
        if (value == null || value.isBlank()) {
            return y;
        }
        int top = y + 14;
        body.append(sectionLabel(20, top, "ABOUT ME"));
        List<String> lines = wrapLines(value, 60, 4);
        int lineY = top + 18;
        for (String line : lines) {
            body.append(Svg.text(20, lineY, TEXT2, 13, 400, line));
            lineY += 17;
        }
        return lineY - 17 + 6;
    }

    private int activity(StringBuilder body, StringBuilder defs, LanyardState state, int y) {
        int dividerY = y + 12;
        body.append("<line x1=\"20\" y1=\"").append(dividerY).append("\" x2=\"").append(W - 20)
                .append("\" y2=\"").append(dividerY).append("\" stroke=\"").append(HAIRLINE)
                .append("\" stroke-width=\"1\"/>");
        int top = dividerY + 16;

        if (state.spotify() != null) {
            return spotifyPanel(body, defs, state.spotify(), top);
        }
        if (state.playing() != null) {
            return playingPanel(body, state.playing(), top);
        }
        return statusPanel(body, state, top);
    }

    private int spotifyPanel(StringBuilder body, StringBuilder defs, LanyardState.Spotify spotify, int top) {
        int panX = 16;
        int panW = W - 32;
        int panH = 118;
        panel(body, panX, top, panW, panH);

        int art = 76;
        int artX = panX + 14;
        int artY = top + 14;
        body.append("<clipPath id=\"dalbum\"><rect x=\"").append(artX).append("\" y=\"").append(artY)
                .append("\" width=\"").append(art).append("\" height=\"").append(art).append("\" rx=\"6\"/></clipPath>");
        if (spotify.albumArtDataUri() != null) {
            body.append("<image href=\"").append(spotify.albumArtDataUri()).append("\" x=\"").append(artX)
                    .append("\" y=\"").append(artY).append("\" width=\"").append(art).append("\" height=\"").append(art)
                    .append("\" preserveAspectRatio=\"xMidYMid slice\" clip-path=\"url(#dalbum)\"/>");
        } else {
            body.append("<rect x=\"").append(artX).append("\" y=\"").append(artY).append("\" width=\"").append(art)
                    .append("\" height=\"").append(art).append("\" rx=\"6\" fill=\"#2b2d31\"/>");
            body.append(noteGlyph(artX + art / 2, artY + art / 2));
        }
        body.append("<rect x=\"").append(artX).append("\" y=\"").append(artY).append("\" width=\"").append(art)
                .append("\" height=\"").append(art)
                .append("\" rx=\"6\" fill=\"none\" stroke=\"rgba(255,255,255,0.08)\" stroke-width=\"1\"/>");

        int textX = artX + art + 16;
        body.append(spotifyGlyph(textX + 7, top + 22));
        body.append(Svg.text(textX + 20, top + 26, SPOTIFY, 12, 700, "Listening to Spotify"));
        body.append(Svg.text(textX, top + 48, TEXT, 15, 700, truncate(spotify.song(), 26)));
        body.append(Svg.text(textX, top + 66, MUTED, 12, 400, "by " + truncate(spotify.artist(), 30)));
        body.append(Svg.text(textX, top + 82, MUTED, 12, 400, "on " + truncate(spotify.album(), 30)));

        long total = spotify.endMs() - spotify.startMs();
        long elapsed = Math.max(0, Math.min(total, System.currentTimeMillis() - spotify.startMs()));
        int barX = textX;
        int barRight = panX + panW - 16;
        int fullW = barRight - barX;
        int barY = top + 96;
        double fraction = total > 0 ? (double) elapsed / total : 0;
        double filled = fraction * fullW;
        double remainingSec = Math.max(0, (total - elapsed) / 1000.0);

        body.append("<rect x=\"").append(barX).append("\" y=\"").append(barY).append("\" width=\"").append(fullW)
                .append("\" height=\"5\" rx=\"2.5\" fill=\"rgba(255,255,255,0.14)\"/>");
        body.append("<rect x=\"").append(barX).append("\" y=\"").append(barY).append("\" width=\"")
                .append(Svg.trim(filled)).append("\" height=\"5\" rx=\"2.5\" fill=\"").append(SPOTIFY).append("\">");
        if (remainingSec > 0 && total > 0) {
            body.append("<animate attributeName=\"width\" from=\"").append(Svg.trim(filled)).append("\" to=\"")
                    .append(fullW).append("\" dur=\"").append(Svg.trim(remainingSec))
                    .append("s\" fill=\"freeze\"/>");
        }
        body.append("</rect>");

        body.append(Svg.text(barX, barY + 18, MUTED, 10, 400, clock(elapsed)));
        body.append(Svg.textAnchored(barRight, barY + 18, MUTED, 10, 400, "end", clock(total)));
        return top + panH + 4;
    }

    private int playingPanel(StringBuilder body, LanyardState.Playing playing, int top) {
        boolean hasDetails = playing.details() != null && !playing.details().isBlank();
        boolean hasState = playing.state() != null && !playing.state().isBlank();
        int panX = 16;
        int panW = W - 32;
        int panH = 44 + (hasDetails ? 18 : 0) + (hasState ? 18 : 0);
        panel(body, panX, top, panW, panH);

        int tile = 52;
        int tileX = panX + 14;
        int tileY = top + (panH - tile) / 2;
        body.append("<rect x=\"").append(tileX).append("\" y=\"").append(tileY).append("\" width=\"").append(tile)
                .append("\" height=\"").append(tile).append("\" rx=\"8\" fill=\"#2b2d31\"/>");
        body.append(controllerGlyph(tileX + tile / 2, tileY + tile / 2));

        int textX = tileX + tile + 14;
        body.append(sectionLabel(textX, top + 20, "PLAYING A GAME"));
        int lineY = top + 40;
        body.append(Svg.text(textX, lineY, TEXT, 14, 700, truncate(playing.name(), 32)));
        if (hasDetails) {
            lineY += 18;
            body.append(Svg.text(textX, lineY, MUTED, 12, 400, truncate(playing.details(), 36)));
        }
        if (hasState) {
            lineY += 18;
            body.append(Svg.text(textX, lineY, MUTED, 12, 400, truncate(playing.state(), 36)));
        }
        return top + panH + 4;
    }

    private int statusPanel(StringBuilder body, LanyardState state, int top) {
        String label = state.customStatus() != null ? state.customStatus()
                : (state.present() ? statusLabel(state.status()) : "Discord");
        if (state.stale()) {
            label = label + " (cached)";
        }
        int panX = 16;
        int panW = W - 32;
        int panH = 46;
        panel(body, panX, top, panW, panH);
        body.append("<circle cx=\"").append(panX + 24).append("\" cy=\"").append(top + panH / 2)
                .append("\" r=\"6\" fill=\"").append(statusColor(state.status())).append("\"/>");
        body.append(Svg.text(panX + 40, top + panH / 2 + 5, TEXT2, 14, 500, truncate(label, 44)));
        return top + panH + 4;
    }

    private void panel(StringBuilder body, int x, int y, int w, int h) {
        body.append("<rect x=\"").append(x).append("\" y=\"").append(y).append("\" width=\"").append(w)
                .append("\" height=\"").append(h).append("\" rx=\"10\" fill=\"").append(PANEL_BG)
                .append("\" stroke=\"rgba(255,255,255,0.04)\" stroke-width=\"1\"/>");
    }

    private String spotifyGlyph(int cx, int cy) {
        return "<g>"
                + "<circle cx=\"" + cx + "\" cy=\"" + cy + "\" r=\"7\" fill=\"" + SPOTIFY + "\"/>"
                + arc(cx, cy - 1.2, 4.2, "#0b3d1f", 1.6)
                + arc(cx, cy + 0.9, 3.2, "#0b3d1f", 1.5)
                + arc(cx, cy + 2.8, 2.2, "#0b3d1f", 1.3)
                + "</g>";
    }

    private String arc(double cx, double cy, double halfWidth, String color, double weight) {
        return "<path d=\"M" + Svg.trim(cx - halfWidth) + " " + Svg.trim(cy) + " Q" + Svg.trim(cx) + " "
                + Svg.trim(cy - halfWidth * 0.8) + " " + Svg.trim(cx + halfWidth) + " " + Svg.trim(cy)
                + "\" fill=\"none\" stroke=\"" + color + "\" stroke-width=\"" + Svg.trim(weight)
                + "\" stroke-linecap=\"round\"/>";
    }

    private String noteGlyph(int cx, int cy) {
        return "<g fill=\"" + MUTED + "\">"
                + "<rect x=\"" + (cx + 4) + "\" y=\"" + (cy - 14) + "\" width=\"3\" height=\"18\" rx=\"1.5\"/>"
                + "<circle cx=\"" + (cx - 2) + "\" cy=\"" + (cy + 4) + "\" r=\"5\"/>"
                + "<path d=\"M" + (cx + 4) + " " + (cy - 14) + " L" + (cx + 12) + " " + (cy - 12)
                + " L" + (cx + 12) + " " + (cy - 8) + " L" + (cx + 4) + " " + (cy - 10) + " Z\"/>"
                + "</g>";
    }

    private String controllerGlyph(int cx, int cy) {
        return "<g fill=\"none\" stroke=\"" + MUTED + "\" stroke-width=\"2\" stroke-linecap=\"round\">"
                + "<rect x=\"" + (cx - 13) + "\" y=\"" + (cy - 7) + "\" width=\"26\" height=\"14\" rx=\"7\"/>"
                + "<line x1=\"" + (cx - 7) + "\" y1=\"" + (cy - 1) + "\" x2=\"" + (cx - 7) + "\" y2=\"" + (cy + 3) + "\"/>"
                + "<line x1=\"" + (cx - 9) + "\" y1=\"" + (cy + 1) + "\" x2=\"" + (cx - 5) + "\" y2=\"" + (cy + 1) + "\"/>"
                + "<circle cx=\"" + (cx + 6) + "\" cy=\"" + cy + "\" r=\"1.6\" fill=\"" + MUTED + "\" stroke=\"none\"/>"
                + "</g>";
    }

    private String sectionLabel(int x, int y, String text) {
        return "<text x=\"" + x + "\" y=\"" + y + "\" fill=\"" + MUTED
                + "\" font-size=\"11\" font-weight=\"700\" letter-spacing=\"0.6\">" + Svg.escape(text) + "</text>";
    }

    private String wrap(int height, boolean hideBorder, String aria, String defs, String body) {
        StringBuilder svg = new StringBuilder(6144);
        svg.append("<svg width=\"").append(W).append("\" height=\"").append(height)
                .append("\" viewBox=\"0 0 ").append(W).append(' ').append(height)
                .append("\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\""
                        + " role=\"img\" aria-label=\"")
                .append(Svg.escape(aria)).append("\">");
        svg.append("<style>text{font-family:").append(Svg.FONT).append(";}</style>");
        svg.append("<defs><clipPath id=\"dcard\"><rect x=\"0\" y=\"0\" width=\"").append(W).append("\" height=\"")
                .append(height).append("\" rx=\"16\"/></clipPath>").append(defs).append("</defs>");
        svg.append("<rect x=\"0\" y=\"0\" width=\"").append(W).append("\" height=\"").append(height)
                .append("\" rx=\"16\" fill=\"").append(CARD_BG).append("\"/>");
        svg.append(body);
        if (!hideBorder) {
            svg.append("<rect x=\"0.5\" y=\"0.5\" width=\"").append(W - 1).append("\" height=\"").append(height - 1)
                    .append("\" rx=\"15.5\" fill=\"none\" stroke=\"rgba(255,255,255,0.08)\" stroke-width=\"1\"/>");
        }
        svg.append("</svg>");
        return svg.toString();
    }

    private List<String> wrapLines(String text, int maxChars, int maxLines) {
        List<String> lines = new ArrayList<>();
        StringBuilder line = new StringBuilder();
        for (String word : text.trim().split("\\s+")) {
            if (line.length() == 0) {
                line.append(word);
            } else if (line.length() + 1 + word.length() <= maxChars) {
                line.append(' ').append(word);
            } else {
                lines.add(line.toString());
                line = new StringBuilder(word);
                if (lines.size() == maxLines - 1) {
                    break;
                }
            }
        }
        if (lines.size() < maxLines && line.length() > 0) {
            lines.add(line.toString());
        }
        if (!lines.isEmpty() && lines.size() == maxLines) {
            String last = lines.get(maxLines - 1);
            if (last.length() > maxChars - 1) {
                lines.set(maxLines - 1, last.substring(0, maxChars - 1) + "...");
            }
        }
        return lines;
    }

    private String initials(LanyardState state) {
        String source = state.username() != null && !state.username().isBlank()
                ? state.username() : state.displayName();
        String cleaned = source.replaceAll("[^A-Za-z0-9]", "");
        if (cleaned.isEmpty()) {
            return "DF";
        }
        return cleaned.substring(0, Math.min(2, cleaned.length())).toUpperCase();
    }

    private String truncate(String text, int maxChars) {
        if (text == null) {
            return "";
        }
        return text.length() <= maxChars ? text : text.substring(0, maxChars - 1) + "...";
    }

    private String clock(long millis) {
        long totalSeconds = Math.max(0, millis / 1000);
        return (totalSeconds / 60) + ":" + String.format("%02d", totalSeconds % 60);
    }

    private String statusColor(String status) {
        return switch (status) {
            case "online" -> "#23a55a";
            case "idle" -> "#f0b232";
            case "dnd" -> "#f23f43";
            default -> "#80848e";
        };
    }

    private String statusLabel(String status) {
        return switch (status) {
            case "online" -> "Online";
            case "idle" -> "Idle";
            case "dnd" -> "Do Not Disturb";
            default -> "Offline";
        };
    }
}
