package com.dualfroz.ghstats.server.theme;

import java.util.List;

public record Theme(
        String name,
        String background,
        String text,
        String title,
        String icon,
        String muted,
        String border,
        List<String> heatLevels
) {
}
