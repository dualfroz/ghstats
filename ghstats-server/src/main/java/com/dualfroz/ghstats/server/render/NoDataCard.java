package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.server.theme.Theme;

public final class NoDataCard {

    private NoDataCard() {
    }

    public static String render(String cardKey, Theme theme, boolean hideBorder) {
        int width = 495;
        int height = 150;
        StringBuilder body = new StringBuilder();
        body.append(Svg.title(25, 40, theme, "GitHub Stats"));
        body.append(Svg.text(25, 85, theme.text(), 15, 400, "No data available yet."));
        body.append(Svg.text(25, 110, theme.muted(), 13, 400,
                "The collector has not produced a snapshot for '" + cardKey + "'."));
        return Svg.document(width, height, theme, hideBorder, "GitHub stats - no data", body.toString());
    }
}
