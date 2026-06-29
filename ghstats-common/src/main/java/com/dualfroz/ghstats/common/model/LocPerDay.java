package com.dualfroz.ghstats.common.model;

import java.time.LocalDate;

public record LocPerDay(
        LocalDate date,
        long added,
        long removed
) {
}
