package com.dualfroz.ghstats.server.theme;

import java.util.List;
import java.util.Map;

public final class Themes {

    public static final String DEFAULT_NAME = "tokyonight";

    private static final Theme TOKYONIGHT = new Theme(
            "tokyonight",
            "#1a1b27",
            "#a9b1d6",
            "#7aa2f7",
            "#bb9af7",
            "#565f89",
            "#2f3549",
            List.of("#232433", "#2c3a63", "#3d5aa0", "#5a7fd6", "#7aa2f7")
    );

    private static final Theme LIGHT = new Theme(
            "light",
            "#ffffff",
            "#24292f",
            "#0969da",
            "#8250df",
            "#57606a",
            "#d0d7de",
            List.of("#ebedf0", "#9be9a8", "#40c463", "#30a14e", "#216e39")
    );

    private static final Map<String, Theme> BY_NAME = Map.of(
            "tokyonight", TOKYONIGHT,
            "light", LIGHT
    );

    private Themes() {
    }

    public static Theme resolve(String name) {
        if (name == null) {
            return TOKYONIGHT;
        }
        return BY_NAME.getOrDefault(name.toLowerCase(), TOKYONIGHT);
    }

    public static List<String> names() {
        return List.copyOf(BY_NAME.keySet());
    }
}
