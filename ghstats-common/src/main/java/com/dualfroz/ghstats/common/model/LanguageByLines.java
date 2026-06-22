package com.dualfroz.ghstats.common.model;

public record LanguageByLines(
        String name,
        long lines,
        double share,
        String color
) {
}
