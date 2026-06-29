package com.dualfroz.ghstats.server.web;

import com.dualfroz.ghstats.common.model.CalendarDay;
import com.dualfroz.ghstats.common.model.LanguageByLines;
import com.dualfroz.ghstats.common.model.LanguageFirstYear;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.server.ServerProperties;
import com.dualfroz.ghstats.server.SnapshotState;
import com.dualfroz.ghstats.server.SnapshotStore;
import java.time.Duration;
import java.time.Year;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import org.springframework.http.CacheControl;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestHeader;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class StatsDataController {

    private static final int LAST_30_DAYS = 30;

    private final SnapshotStore store;
    private final ServerProperties properties;

    public StatsDataController(SnapshotStore store, ServerProperties properties) {
        this.store = store;
        this.properties = properties;
    }

    @GetMapping(value = "/data/stats", produces = MediaType.APPLICATION_JSON_VALUE)
    public ResponseEntity<StatsData> stats(
            @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        SnapshotState state = store.state();
        if (!state.hasData()) {
            return ResponseEntity.status(HttpStatus.SERVICE_UNAVAILABLE)
                    .cacheControl(CacheControl.noStore())
                    .build();
        }

        CacheControl cacheControl = CacheControl
                .maxAge(Duration.ofSeconds(properties.getCacheMaxAgeSeconds()))
                .cachePublic();
        String etag = state.etag().substring(0, state.etag().length() - 1) + "-data\"";
        if (etag.equals(ifNoneMatch)) {
            return ResponseEntity.status(HttpStatus.NOT_MODIFIED)
                    .eTag(etag)
                    .cacheControl(cacheControl)
                    .build();
        }

        return ResponseEntity.ok()
                .eTag(etag)
                .cacheControl(cacheControl)
                .body(build(state));
    }

    private StatsData build(SnapshotState state) {
        Snapshot snapshot = state.snapshot();
        List<CalendarDay> calendar = snapshot.calendar();
        List<CalendarDay> last30 = calendar.size() <= LAST_30_DAYS
                ? calendar
                : calendar.subList(calendar.size() - LAST_30_DAYS, calendar.size());

        return new StatsData(
                snapshot.totals().linesOfCode(),
                snapshot.totals().charsOfCode(),
                snapshot.totals().ownCommits(),
                snapshot.totals().reposOwn(),
                snapshot.totals().projects(),
                snapshot.totals().activeDays(),
                snapshot.totals().linesAdded(),
                snapshot.totals().linesRemoved(),
                snapshot.totals().longestCommitChurn(),
                snapshot.languagesByLines(),
                languageMeta(snapshot),
                window(last30),
                window(calendar),
                snapshot.byYear(),
                state.generatedAt(),
                snapshot.locPerDay() != null ? snapshot.locPerDay() : List.of());
    }

    // Per-language expertise/experience, fully derived from the snapshot (no manual
    // config): `since` is the first commit year for that language (from git), and
    // `stars` (1-5) is a score blending code volume with tenure. sqrt on the volume
    // share keeps mid-size languages from being crushed by the largest one.
    private List<StatsData.LanguageMeta> languageMeta(Snapshot snapshot) {
        Map<String, Integer> firstYear = new HashMap<>();
        List<LanguageFirstYear> years = snapshot.languageFirstYear();
        if (years != null) {
            for (LanguageFirstYear entry : years) {
                firstYear.put(entry.name(), entry.year());
            }
        }
        long maxLines = snapshot.languagesByLines().stream()
                .mapToLong(LanguageByLines::lines).max().orElse(1L);
        int nowYear = Year.now().getValue();

        List<StatsData.LanguageMeta> meta = new ArrayList<>();
        for (LanguageByLines language : snapshot.languagesByLines()) {
            Integer since = firstYear.get(language.name());
            int stars = expertiseStars(language.lines(), maxLines, since, nowYear);
            meta.add(new StatsData.LanguageMeta(language.name(), stars, since));
        }
        return meta;
    }

    private int expertiseStars(long lines, long maxLines, Integer since, int nowYear) {
        double volumeScore = maxLines > 0 ? Math.sqrt((double) lines / maxLines) : 0.0;
        double tenureScore = 0.0;
        if (since != null) {
            int years = Math.max(1, nowYear - since + 1);
            tenureScore = Math.min(years, 6) / 6.0;
        }
        double combined = 0.6 * volumeScore + 0.4 * tenureScore;
        long stars = Math.round(1 + 4 * combined);
        return (int) Math.max(1, Math.min(5, stars));
    }

    private StatsData.CommitWindow window(List<CalendarDay> days) {
        int total = days.stream().mapToInt(CalendarDay::count).sum();
        return new StatsData.CommitWindow(total, List.copyOf(days));
    }
}
