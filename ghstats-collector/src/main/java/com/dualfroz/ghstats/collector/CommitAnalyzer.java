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
            long linesRemoved) {
    }

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
        int[][] punch = new int[7][24];
        if (!result.ok()) {
            return new CommitScan(0, dayCounts, punch, 0, 0);
        }

        int ownCommits = 0;
        long linesAdded = 0;
        long linesRemoved = 0;

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
                for (String statLine : record.substring(newline + 1).split("\n")) {
                    long[] delta = parseNumstat(statLine);
                    if (delta != null) {
                        linesAdded += delta[0];
                        linesRemoved += delta[1];
                    }
                }
            }
        }
        return new CommitScan(ownCommits, dayCounts, punch, linesAdded, linesRemoved);
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
