package com.dualfroz.ghstats.common.model;

import com.fasterxml.jackson.annotation.JsonIgnore;
import java.time.Instant;
import java.util.List;

public record Snapshot(
        Instant generatedAt,
        Identity identity,
        Totals totals,
        Streak streak,
        List<LanguageByLines> languagesByLines,
        List<CalendarDay> calendar,
        List<CalendarDay> calendarAll,
        List<YearCount> byYear,
        List<TopRepo> topRepos,
        List<PunchCell> punchCard
) {
    @JsonIgnore
    public boolean isEmpty() {
        return totals == null || calendar == null || calendar.isEmpty();
    }
}
