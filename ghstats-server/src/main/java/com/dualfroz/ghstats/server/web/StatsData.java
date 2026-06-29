package com.dualfroz.ghstats.server.web;

import com.dualfroz.ghstats.common.model.CalendarDay;
import com.dualfroz.ghstats.common.model.LanguageByLines;
import com.dualfroz.ghstats.common.model.LocPerDay;
import com.dualfroz.ghstats.common.model.YearCount;
import java.time.Instant;
import java.util.List;

public record StatsData(
        long locTotal,
        long charsOfCode,
        int ownCommits,
        int reposOwn,
        int projects,
        int activeDays,
        long linesAdded,
        long linesRemoved,
        long longestCommitChurn,
        List<LanguageByLines> languages,
        List<LanguageMeta> languageMeta,
        CommitWindow commitsLast30d,
        CommitWindow commitsLastYear,
        List<YearCount> byYear,
        Instant generatedAt,
        List<LocPerDay> locPerDay
) {
    public record CommitWindow(
            int total,
            List<CalendarDay> days
    ) {
    }

    /**
     * Per-language metadata derived from the snapshot: expertise stars (1-5,
     * blended from code volume and tenure) and the first year we used it.
     */
    public record LanguageMeta(
            String name,
            Integer stars,
            Integer since
    ) {
    }
}
