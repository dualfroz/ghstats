package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.server.theme.Theme;

public record RenderOptions(
        Theme theme,
        boolean hideBorder,
        String range,
        String lang,
        String displayName
) {
    public static final String RANGE_YEAR = "year";
    public static final String RANGE_ALL = "all";
    public static final String LANG_EN = "en";

    public RenderOptions(Theme theme, boolean hideBorder, String range) {
        this(theme, hideBorder, range, LANG_EN, null);
    }

    public boolean allTime() {
        return RANGE_ALL.equalsIgnoreCase(range);
    }

    public Labels labels() {
        return Labels.of(lang);
    }

    public String nameOr(String fallback) {
        return displayName == null || displayName.isBlank() ? fallback : displayName;
    }
}
