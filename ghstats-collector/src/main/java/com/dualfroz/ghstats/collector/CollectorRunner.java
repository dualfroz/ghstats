package com.dualfroz.ghstats.collector;

import com.dualfroz.ghstats.common.LanguageColors;
import com.dualfroz.ghstats.common.model.CalendarDay;
import com.dualfroz.ghstats.common.model.Identity;
import com.dualfroz.ghstats.common.model.LanguageByLines;
import com.dualfroz.ghstats.common.model.PunchCell;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.common.model.Streak;
import com.dualfroz.ghstats.common.model.Totals;
import com.dualfroz.ghstats.common.model.TopRepo;
import com.dualfroz.ghstats.common.model.YearCount;
import java.io.IOException;
import java.nio.file.Path;
import java.time.Instant;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.TreeMap;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

@Component
public class CollectorRunner {

    private static final Logger log = LoggerFactory.getLogger(CollectorRunner.class);

    private record OwnRepo(String nameWithOwner, int ownCommits, int stars) {
    }

    private final CollectorProperties properties;
    private final RepoEnumerator enumerator;
    private final RepoSync repoSync;
    private final CommitAnalyzer commitAnalyzer;
    private final LanguageAnalyzer languageAnalyzer;
    private final StreakCalculator streakCalculator;
    private final MetaFetcher metaFetcher;
    private final SnapshotWriter snapshotWriter;

    public CollectorRunner(CollectorProperties properties, RepoEnumerator enumerator, RepoSync repoSync,
                           CommitAnalyzer commitAnalyzer, LanguageAnalyzer languageAnalyzer,
                           StreakCalculator streakCalculator, MetaFetcher metaFetcher,
                           SnapshotWriter snapshotWriter) {
        this.properties = properties;
        this.enumerator = enumerator;
        this.repoSync = repoSync;
        this.commitAnalyzer = commitAnalyzer;
        this.languageAnalyzer = languageAnalyzer;
        this.streakCalculator = streakCalculator;
        this.metaFetcher = metaFetcher;
        this.snapshotWriter = snapshotWriter;
    }

    public void run() throws IOException {
        boolean tokeiAvailable = languageAnalyzer.tokeiAvailable();
        if (!tokeiAvailable) {
            log.warn("tokei not available - lines of code and language breakdown will be empty");
        }

        MetaFetcher.Meta meta = metaFetcher.fetch();

        Set<String> fullCountRepos = properties.getFullCountRepos().stream()
                .map(name -> name.toLowerCase())
                .collect(java.util.stream.Collectors.toSet());

        Map<LocalDate, Integer> dayCounts = new HashMap<>();
        Map<String, Long> linesByLanguage = new HashMap<>();
        int[][] punch = new int[7][24];
        List<OwnRepo> ownRepos = new ArrayList<>();
        int reposScanned = 0;
        int stars = 0;
        long linesAdded = 0;
        long linesRemoved = 0;

        for (String owner : properties.getOwners()) {
            List<RemoteRepo> repos = enumerator.enumerate(owner);
            log.info("Owner {}: {} repositories", owner, repos.size());
            for (RemoteRepo repo : repos) {
                reposScanned++;
                Path checkout = repoSync.ensureCheckout(repo);
                if (checkout == null) {
                    continue;
                }
                boolean countAll = fullCountRepos.contains(repo.nameWithOwner().toLowerCase())
                        || fullCountRepos.contains(repo.repo().toLowerCase());
                CommitAnalyzer.CommitScan scan = commitAnalyzer.analyze(checkout, countAll);
                if (scan.ownCommits() == 0) {
                    log.debug("Skipping {} - no commits by tracked authors", repo.nameWithOwner());
                    continue;
                }
                scan.dayCounts().forEach((date, count) -> dayCounts.merge(date, count, Integer::sum));
                accumulate(punch, scan.punch());
                linesAdded += scan.linesAdded();
                linesRemoved += scan.linesRemoved();
                ownRepos.add(new OwnRepo(repo.nameWithOwner(), scan.ownCommits(), repo.stargazerCount()));
                stars += repo.stargazerCount();
                if (tokeiAvailable) {
                    languageAnalyzer.countByLanguage(checkout)
                            .forEach((language, lines) -> linesByLanguage.merge(language, lines, Long::sum));
                }
            }
        }

        LocalDate today = LocalDate.now();
        int ownCommits = dayCounts.values().stream().mapToInt(Integer::intValue).sum()
                + properties.getBaselineCommits();
        int activeDays = dayCounts.size();
        Streak streak = streakCalculator.compute(dayCounts.keySet(), today);
        List<CalendarDay> calendar = buildCalendar(dayCounts, today);
        List<CalendarDay> calendarAll = buildCalendarAll(dayCounts);
        List<YearCount> byYear = buildByYear(dayCounts);
        Map<String, Long> keptLanguages = LanguageNormalizer.normalize(
                linesByLanguage, properties.getLanguageDenylist(), properties.getLanguageAliases());
        List<LanguageByLines> languages = buildLanguages(keptLanguages);
        long linesOfCode = keptLanguages.values().stream().mapToLong(Long::longValue).sum();
        List<TopRepo> topRepos = buildTopRepos(ownRepos);
        List<PunchCell> punchCard = buildPunchCard(punch);

        Totals totals = new Totals(ownCommits, ownRepos.size(), reposScanned, stars,
                languages.size(), linesOfCode, activeDays, meta.followers(), meta.publicRepos(),
                linesAdded, linesRemoved);

        Snapshot snapshot = new Snapshot(
                Instant.now(),
                new Identity(properties.getIdentityLogin(), properties.getIdentityName()),
                totals, streak, languages, calendar, calendarAll, byYear, topRepos, punchCard);

        log.info("Computed: ownCommits={} ownRepos={} scanned={} stars={} languages={} loc={} activeDays={} "
                        + "linesAdded={} linesRemoved={} currentStreak={} longestStreak={}",
                ownCommits, ownRepos.size(), reposScanned, stars, languages.size(), linesOfCode, activeDays,
                linesAdded, linesRemoved, streak.current(), streak.longest());

        snapshotWriter.write(snapshot);
    }

    private List<CalendarDay> buildCalendar(Map<LocalDate, Integer> dayCounts, LocalDate today) {
        List<CalendarDay> calendar = new ArrayList<>();
        LocalDate start = today.minusDays(properties.getCalendarDays() - 1L);
        for (LocalDate day = start; !day.isAfter(today); day = day.plusDays(1)) {
            calendar.add(new CalendarDay(day, dayCounts.getOrDefault(day, 0)));
        }
        return calendar;
    }

    private List<CalendarDay> buildCalendarAll(Map<LocalDate, Integer> dayCounts) {
        return dayCounts.entrySet().stream()
                .sorted(Map.Entry.comparingByKey())
                .map(entry -> new CalendarDay(entry.getKey(), entry.getValue()))
                .toList();
    }

    private List<YearCount> buildByYear(Map<LocalDate, Integer> dayCounts) {
        Map<Integer, Integer> perYear = new TreeMap<>();
        dayCounts.forEach((date, count) -> perYear.merge(date.getYear(), count, Integer::sum));
        return perYear.entrySet().stream()
                .map(entry -> new YearCount(String.valueOf(entry.getKey()), entry.getValue()))
                .toList();
    }

    private void accumulate(int[][] target, int[][] source) {
        for (int weekday = 0; weekday < 7; weekday++) {
            for (int hour = 0; hour < 24; hour++) {
                target[weekday][hour] += source[weekday][hour];
            }
        }
    }

    private List<PunchCell> buildPunchCard(int[][] punch) {
        List<PunchCell> cells = new ArrayList<>();
        for (int weekday = 0; weekday < 7; weekday++) {
            for (int hour = 0; hour < 24; hour++) {
                if (punch[weekday][hour] > 0) {
                    cells.add(new PunchCell(weekday + 1, hour, punch[weekday][hour]));
                }
            }
        }
        return cells;
    }

    private List<LanguageByLines> buildLanguages(Map<String, Long> linesByLanguage) {
        long total = linesByLanguage.values().stream().mapToLong(Long::longValue).sum();
        if (total == 0) {
            return List.of();
        }
        return linesByLanguage.entrySet().stream()
                .sorted(Map.Entry.<String, Long>comparingByValue().reversed())
                .map(entry -> new LanguageByLines(
                        entry.getKey(),
                        entry.getValue(),
                        (double) entry.getValue() / total,
                        LanguageColors.colorFor(entry.getKey())))
                .toList();
    }

    private List<TopRepo> buildTopRepos(List<OwnRepo> ownRepos) {
        return ownRepos.stream()
                .sorted(Comparator.comparingInt(OwnRepo::ownCommits).reversed())
                .limit(properties.getTopReposCount())
                .map(repo -> new TopRepo(repo.nameWithOwner(), repo.ownCommits()))
                .toList();
    }
}
