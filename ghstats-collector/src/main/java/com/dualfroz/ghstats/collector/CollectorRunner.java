package com.dualfroz.ghstats.collector;

import com.dualfroz.ghstats.common.LanguageColors;
import com.dualfroz.ghstats.common.model.CalendarDay;
import com.dualfroz.ghstats.common.model.Identity;
import com.dualfroz.ghstats.common.model.LanguageByLines;
import com.dualfroz.ghstats.common.model.LanguageFirstYear;
import com.dualfroz.ghstats.common.model.LocPerDay;
import java.util.Arrays;
import java.util.HashSet;
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
        Map<LocalDate, long[]> locByDay = new HashMap<>();
        Map<String, Long> addedByLanguage = new HashMap<>();
        Map<String, Long> bytesByLanguage = new HashMap<>();
        Map<String, Long> codeByLanguage = new HashMap<>();
        Map<String, Set<String>> reposByLanguage = new HashMap<>();
        Map<String, Integer> firstYearByLanguage = new HashMap<>();
        int[][] punch = new int[7][24];
        List<OwnRepo> ownRepos = new ArrayList<>();
        int reposScanned = 0;
        int stars = 0;
        long linesAdded = 0;
        long linesRemoved = 0;
        long longestCommitChurn = 0;

        for (String owner : properties.getOwners()) {
            List<RemoteRepo> repos = enumerator.enumerate(owner);
            log.info("Owner {}: {} repositories", owner, repos.size());
            for (RemoteRepo repo : repos) {
                if (repo.fork()) {
                    log.debug("Skipping fork {} - forks are excluded from all stats", repo.nameWithOwner());
                    continue;
                }
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
                longestCommitChurn = Math.max(longestCommitChurn, scan.longestCommitChurn());
                scan.firstYearByLanguage().forEach((language, year) ->
                        firstYearByLanguage.merge(language, year, Math::min));
                scan.addedByLanguage().forEach((language, added) ->
                        addedByLanguage.merge(language, added, Long::sum));
                scan.locByDay().forEach((date, delta) -> locByDay.merge(date, delta, (existing, added) -> {
                    existing[0] += added[0];
                    existing[1] += added[1];
                    return existing;
                }));
                ownRepos.add(new OwnRepo(repo.nameWithOwner(), scan.ownCommits(), repo.stargazerCount()));
                stars += repo.stargazerCount();
                if (tokeiAvailable) {
                    // The breakdown in languages[].lines is authored git churn, not disk.
                    // tokei answers the other question - what is still there today - so its
                    // per-language code counts feed languages[].codeLines, and the languages
                    // it finds in this checkout feed languages[].projects.
                    LanguageAnalyzer.LangScan langScan = languageAnalyzer.countByLanguage(checkout);
                    langScan.bytes().forEach((language, bytes) -> bytesByLanguage.merge(language, bytes, Long::sum));
                    langScan.code().forEach((language, code) -> {
                        if (code <= 0) {
                            return;
                        }
                        codeByLanguage.merge(language, code, Long::sum);
                        String canonical = LanguageNormalizer.canonicalOrNull(
                                language, properties.getLanguageDenylist(), properties.getLanguageAliases());
                        if (canonical != null) {
                            reposByLanguage.computeIfAbsent(canonical, key -> new HashSet<>())
                                    .add(repo.nameWithOwner());
                        }
                    });
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
        // Language breakdown = authored lines per language (gross added from git numstat,
        // attributed by file extension), not current lines-on-disk. linesOfCode follows the
        // same basis so locTotal stays equal to the sum of languages[].lines.
        Map<String, Long> keptLanguages = LanguageNormalizer.normalize(
                addedByLanguage, properties.getLanguageDenylist(), properties.getLanguageAliases());
        Map<String, Long> keptCodeLines = LanguageNormalizer.normalize(
                codeByLanguage, properties.getLanguageDenylist(), properties.getLanguageAliases());
        List<LanguageByLines> languages = buildLanguages(keptLanguages, keptCodeLines, reposByLanguage);
        long linesOfCode = keptLanguages.values().stream().mapToLong(Long::longValue).sum();
        List<TopRepo> topRepos = buildTopRepos(ownRepos);
        List<PunchCell> punchCard = buildPunchCard(punch);
        List<LocPerDay> locPerDay = buildLocPerDay(locByDay);
        long charsOfCode = charsOfCode(bytesByLanguage);
        List<LanguageFirstYear> languageFirstYear = buildLanguageFirstYear(firstYearByLanguage);
        int projects = countProjects(ownRepos);

        Totals totals = new Totals(ownCommits, ownRepos.size(), reposScanned, stars,
                languages.size(), linesOfCode, activeDays, meta.followers(), meta.publicRepos(),
                linesAdded, linesRemoved, longestCommitChurn, charsOfCode, projects);

        Snapshot snapshot = new Snapshot(
                Instant.now(),
                new Identity(properties.getIdentityLogin(), properties.getIdentityName()),
                totals, streak, languages, calendar, calendarAll, byYear, topRepos, punchCard, locPerDay,
                languageFirstYear);

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

    private List<LocPerDay> buildLocPerDay(Map<LocalDate, long[]> locByDay) {
        return locByDay.entrySet().stream()
                .sorted(Map.Entry.comparingByKey())
                .map(entry -> new LocPerDay(entry.getKey(), entry.getValue()[0], entry.getValue()[1]))
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

    private List<LanguageByLines> buildLanguages(Map<String, Long> linesByLanguage,
                                                 Map<String, Long> codeByLanguage,
                                                 Map<String, Set<String>> reposByLanguage) {
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
                        LanguageColors.colorFor(entry.getKey()),
                        codeByLanguage.getOrDefault(entry.getKey(), 0L),
                        reposByLanguage.getOrDefault(entry.getKey(), Set.of()).size()))
                .toList();
    }

    // Total characters (on-disk bytes) of counted code: sum the per-language byte
    // totals from tokei, dropping the same non-code/data languages as the LOC stats
    // so the count reflects real source, not JSON/YAML/lockfiles.
    private long charsOfCode(Map<String, Long> bytesByLanguage) {
        Set<String> deny = properties.getLanguageDenylist().stream()
                .map(String::toLowerCase)
                .collect(java.util.stream.Collectors.toSet());
        return bytesByLanguage.entrySet().stream()
                .filter(entry -> !deny.contains(entry.getKey().toLowerCase()))
                .mapToLong(Map.Entry::getValue)
                .sum();
    }

    private List<LanguageFirstYear> buildLanguageFirstYear(Map<String, Integer> firstYearByLanguage) {
        return firstYearByLanguage.entrySet().stream()
                .sorted(Map.Entry.comparingByKey())
                .map(entry -> new LanguageFirstYear(entry.getKey(), entry.getValue()))
                .toList();
    }

    // Number of distinct projects, derived by collapsing a repo name to its family
    // key: strip the owner and one trailing role suffix (backend/frontend/ios/...),
    // so vulcode-backend + vulcode-frontend + vulcode-discord count as one project
    // while standalone repos (e.g. lunatic-chess) stay separate.
    private static final Set<String> REPO_ROLE_SUFFIXES = Set.of(
            "backend", "frontend", "api", "web", "app", "discord", "bot", "panel", "server",
            "client", "config", "mobile", "ios", "android", "desktop", "tui", "cli", "docs",
            "site", "landing", "admin", "core", "common", "shared", "infra", "worker", "gateway");

    private int countProjects(List<OwnRepo> ownRepos) {
        Set<String> keys = new HashSet<>();
        for (OwnRepo repo : ownRepos) {
            keys.add(projectKey(repo.nameWithOwner()));
        }
        return keys.size();
    }

    private String projectKey(String nameWithOwner) {
        String name = nameWithOwner;
        int slash = name.indexOf('/');
        if (slash >= 0) {
            name = name.substring(slash + 1);
        }
        name = name.toLowerCase();
        String[] parts = name.split("-");
        if (parts.length > 1 && REPO_ROLE_SUFFIXES.contains(parts[parts.length - 1])) {
            return String.join("-", Arrays.copyOf(parts, parts.length - 1));
        }
        return name;
    }

    private List<TopRepo> buildTopRepos(List<OwnRepo> ownRepos) {
        return ownRepos.stream()
                .sorted(Comparator.comparingInt(OwnRepo::ownCommits).reversed())
                .limit(properties.getTopReposCount())
                .map(repo -> new TopRepo(repo.nameWithOwner(), repo.ownCommits()))
                .toList();
    }
}
