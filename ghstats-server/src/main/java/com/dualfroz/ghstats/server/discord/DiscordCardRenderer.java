package com.dualfroz.ghstats.server.discord;

import com.dualfroz.ghstats.server.render.Svg;
import com.dualfroz.ghstats.server.theme.Theme;
import org.springframework.stereotype.Component;

@Component
public class DiscordCardRenderer {

    private static final int WIDTH = 400;

    public String render(LanyardState state, Theme theme, boolean hideBorder) {
        boolean present = state.present();
        String displayName = present && state.displayName() != null ? state.displayName() : "DualFroz";
        String dotColor = statusColor(present ? state.status() : "offline");
        String statusLabel = present ? statusLabel(state.status()) : "Discord";
        if (present && state.stale()) {
            statusLabel = statusLabel + " (cached)";
        }
        String activity = present ? state.activity() : null;
        int height = activity != null ? 122 : 96;

        StringBuilder body = new StringBuilder();
        body.append(Svg.text(25, 32, theme.muted(), 12, 600, "DISCORD"));
        body.append("<circle cx=\"34\" cy=\"58\" r=\"7\" fill=\"").append(dotColor).append("\"/>");
        body.append(Svg.text(52, 64, theme.text(), 20, 700, displayName));
        body.append(Svg.text(52, 84, dotColor, 13, 600, statusLabel));

        if (activity != null) {
            body.append("<line x1=\"25\" y1=\"98\" x2=\"").append(WIDTH - 25)
                    .append("\" y2=\"98\" stroke=\"").append(theme.border()).append("\" stroke-width=\"1\"/>");
            body.append(Svg.text(25, 116, theme.title(), 13, 400, activity));
        }

        return Svg.document(WIDTH, height, theme, hideBorder,
                "Discord status for " + displayName, body.toString());
    }

    private String statusColor(String status) {
        return switch (status) {
            case "online" -> "#43b581";
            case "idle" -> "#faa61a";
            case "dnd" -> "#f04747";
            default -> "#747f8d";
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
