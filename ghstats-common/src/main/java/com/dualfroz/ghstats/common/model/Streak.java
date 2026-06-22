package com.dualfroz.ghstats.common.model;

import java.time.LocalDate;

public record Streak(
        int current,
        int longest,
        LocalDate currentStart,
        LocalDate currentEnd
) {
}
