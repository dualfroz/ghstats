package com.dualfroz.ghstats.common.model;

import java.time.LocalDate;

public record CalendarDay(
        LocalDate date,
        int count
) {
}
