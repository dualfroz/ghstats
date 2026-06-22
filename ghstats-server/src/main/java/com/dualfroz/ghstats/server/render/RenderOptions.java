package com.dualfroz.ghstats.server.render;

import com.dualfroz.ghstats.server.theme.Theme;

public record RenderOptions(
        Theme theme,
        boolean hideBorder,
        String range
) {
    public static final String RANGE_YEAR = "year";
    public static final String RANGE_ALL = "all";

    public boolean allTime() {
        return RANGE_ALL.equalsIgnoreCase(range);
    }
}
