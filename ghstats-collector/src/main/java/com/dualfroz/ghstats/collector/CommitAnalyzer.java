package com.dualfroz.ghstats.collector;

import java.nio.file.Path;
import java.time.Duration;
import java.time.LocalDate;
import java.time.format.DateTimeParseException;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

@Component
public class CommitAnalyzer {

    private static final Logger log = LoggerFactory.getLogger(CommitAnalyzer.class);
    private static final String RECORD_SEPARATOR = "\u001e";

    public record CommitScan(
            int ownCommits,
            Map<LocalDate, Integer> dayCounts,
            int[][] punch,
            long linesAdded,
            long linesRemoved,
            Map<LocalDate, long[]> locByDay,
            long longestCommitChurn,
            Map<String, Integer> firstYearByLanguage,
            Map<String, Long> addedByLanguage) {
    }

    // Map file extension -> language name, using the exact names tokei/the
    // normalizer produce, so per-language first-use years join cleanly onto the
    // language breakdown. Only extensions we care to attribute are listed; anything
    // else (data files, assets) is intentionally ignored.
    private static final Map<String, String> EXT_TO_LANGUAGE = Map.ofEntries(
            Map.entry("ts", "TypeScript"), Map.entry("tsx", "TypeScript"), Map.entry("mts", "TypeScript"),
            Map.entry("js", "JavaScript"), Map.entry("jsx", "JavaScript"), Map.entry("mjs", "JavaScript"),
            Map.entry("cjs", "JavaScript"),
            Map.entry("rs", "Rust"),
            Map.entry("php", "PHP"),
            Map.entry("go", "Go"),
            Map.entry("cs", "C#"),
            Map.entry("py", "Python"),
            Map.entry("ex", "Elixir"), Map.entry("exs", "Elixir"),
            Map.entry("kt", "Kotlin"), Map.entry("kts", "Kotlin"),
            Map.entry("java", "Java"),
            Map.entry("swift", "Swift"),
            Map.entry("rb", "Ruby"),
            Map.entry("cpp", "C++"), Map.entry("cc", "C++"), Map.entry("cxx", "C++"), Map.entry("hpp", "C++"),
            Map.entry("c", "C"), Map.entry("h", "C"),
            Map.entry("html", "HTML"), Map.entry("htm", "HTML"),
            Map.entry("css", "CSS"),
            Map.entry("scss", "Sass"), Map.entry("sass", "Sass"),
            Map.entry("dart", "Dart"),
            Map.entry("vue", "Vue"),
            Map.entry("svelte", "Svelte"),
            Map.entry("sql", "SQL"),
            Map.entry("sh", "Shell"), Map.entry("bash", "Shell"),
            Map.entry("hs", "Haskell"), Map.entry("lhs", "Haskell"),
            Map.entry("lua", "Lua"),
            Map.entry("zig", "Zig"),
            Map.entry("fs", "F#"), Map.entry("fsx", "F#"), Map.entry("fsi", "F#"),
            Map.entry("scala", "Scala"),
            Map.entry("sol", "Solidity"),
            Map.entry("s", "Assembly"), Map.entry("asm", "Assembly"));

    private final ProcessRunner processRunner;
    private final CollectorProperties properties;

    public CommitAnalyzer(ProcessRunner processRunner, CollectorProperties properties) {
        this.processRunner = processRunner;
        this.properties = properties;
    }

    public CommitScan analyze(Path repoPath, boolean countAll) {
        Set<String> authors = properties.getAuthorEmails().stream()
                .map(email -> email.toLowerCase())
                .collect(java.util.stream.Collectors.toSet());

        ProcessRunner.ProcessResult result = processRunner.run(
                List.of(properties.getGitBin(), "-C", repoPath.toString(),
                        "log", "--all", "--no-merges", "--numstat",
                        "--pretty=format:%x1e%ae|%ad", "--date=format:%Y-%m-%d %u %H"),
                null, Duration.ofSeconds(properties.getNumstatTimeoutSeconds()));

        Map<LocalDate, Integer> dayCounts = new HashMap<>();
        Map<LocalDate, long[]> locByDay = new HashMap<>();
        Map<String, Integer> firstYearByLanguage = new HashMap<>();
        Map<String, Long> addedByLanguage = new HashMap<>();
        int[][] punch = new int[7][24];
        if (!result.ok()) {
            return new CommitScan(0, dayCounts, punch, 0, 0, locByDay, 0, firstYearByLanguage,
                    addedByLanguage);
        }

        int ownCommits = 0;
        long linesAdded = 0;
        long linesRemoved = 0;
        long longestCommitChurn = 0;

        for (String record : result.stdout().split(RECORD_SEPARATOR)) {
            if (record.isBlank()) {
                continue;
            }
            int newline = record.indexOf('\n');
            String header = (newline < 0 ? record : record.substring(0, newline)).strip();
            int separator = header.indexOf('|');
            if (separator < 0) {
                continue;
            }
            String email = header.substring(0, separator).toLowerCase();
            if (!countAll && !authors.contains(email)) {
                continue;
            }
            String[] stamp = header.substring(separator + 1).trim().split("\\s+");
            if (stamp.length < 3) {
                continue;
            }
            LocalDate date = parseDate(stamp[0]);
            if (date == null) {
                continue;
            }
            ownCommits++;
            dayCounts.merge(date, 1, Integer::sum);
            int weekday = parseInt(stamp[1]);
            int hour = parseInt(stamp[2]);
            if (weekday >= 1 && weekday <= 7 && hour >= 0 && hour <= 23) {
                punch[weekday - 1][hour]++;
            }

            if (newline >= 0) {
                long commitAdded = 0;
                long commitRemoved = 0;
                int commitYear = date.getYear();
                for (String statLine : record.substring(newline + 1).split("\n")) {
                    long[] delta = parseNumstat(statLine);
                    if (delta != null) {
                        commitAdded += delta[0];
                        commitRemoved += delta[1];
                    }
                    String language = languageForStatLine(statLine);
                    if (language != null) {
                        firstYearByLanguage.merge(language, commitYear, Math::min);
                        // Authored-lines attribution: credit the language with the lines this
                        // commit ADDED to files of that language (gross added, no subtraction of
                        // removed). Binary files report "-" churn (delta == null) and are skipped.
                        if (delta != null) {
                            addedByLanguage.merge(language, delta[0], Long::sum);
                        }
                    }
                }
                linesAdded += commitAdded;
                linesRemoved += commitRemoved;
                longestCommitChurn = Math.max(longestCommitChurn, commitAdded + commitRemoved);
                locByDay.merge(date, new long[]{commitAdded, commitRemoved}, (existing, added) -> {
                    existing[0] += added[0];
                    existing[1] += added[1];
                    return existing;
                });
            }
        }
        return new CommitScan(ownCommits, dayCounts, punch, linesAdded, linesRemoved, locByDay,
                longestCommitChurn, firstYearByLanguage, addedByLanguage);
    }

    // Extract the language of a numstat line ("added\tremoved\tpath") from the file
    // extension of its path. Handles rename forms ("a => b", "dir/{a => b}/c") by
    // taking the text after the last "=>" and trimming braces.
    private static String languageForStatLine(String statLine) {
        int lastTab = statLine.lastIndexOf('\t');
        if (lastTab < 0) {
            return null;
        }
        String path = statLine.substring(lastTab + 1).trim();
        int arrow = path.lastIndexOf("=> ");
        if (arrow >= 0) {
            path = path.substring(arrow + 3).replace("}", "").trim();
        }
        int dot = path.lastIndexOf('.');
        if (dot < 0 || dot == path.length() - 1) {
            return null;
        }
        String ext = path.substring(dot + 1).toLowerCase();
        return EXT_TO_LANGUAGE.get(ext);
    }

    private static long[] parseNumstat(String line) {
        int firstTab = line.indexOf('\t');
        if (firstTab < 0) {
            return null;
        }
        int secondTab = line.indexOf('\t', firstTab + 1);
        if (secondTab < 0) {
            return null;
        }
        long added = parseLong(line.substring(0, firstTab));
        long removed = parseLong(line.substring(firstTab + 1, secondTab));
        if (added < 0 || removed < 0) {
            return null;
        }
        return new long[]{added, removed};
    }

    private static LocalDate parseDate(String raw) {
        try {
            return LocalDate.parse(raw);
        } catch (DateTimeParseException malformed) {
            log.debug("Skipping unparseable commit date '{}'", raw);
            return null;
        }
    }

    private static int parseInt(String raw) {
        try {
            return Integer.parseInt(raw);
        } catch (NumberFormatException malformed) {
            return -1;
        }
    }

    private static long parseLong(String raw) {
        try {
            return Long.parseLong(raw);
        } catch (NumberFormatException malformed) {
            return -1;
        }
    }
}
