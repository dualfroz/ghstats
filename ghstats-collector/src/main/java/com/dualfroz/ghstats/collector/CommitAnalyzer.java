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

    public record CommitScan(int ownCommits, Map<LocalDate, Integer> dayCounts) {
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
                        "log", "--all", "--no-merges", "--pretty=format:%ae|%ad", "--date=short"),
                null, Duration.ofSeconds(properties.getGitTimeoutSeconds()));

        Map<LocalDate, Integer> dayCounts = new HashMap<>();
        if (!result.ok()) {
            return new CommitScan(0, dayCounts);
        }

        int ownCommits = 0;
        for (String line : result.stdout().split("\n")) {
            int separator = line.indexOf('|');
            if (separator < 0) {
                continue;
            }
            String email = line.substring(0, separator).toLowerCase();
            if (!countAll && !authors.contains(email)) {
                continue;
            }
            LocalDate date = parseDate(line.substring(separator + 1).trim());
            if (date == null) {
                continue;
            }
            ownCommits++;
            dayCounts.merge(date, 1, Integer::sum);
        }
        return new CommitScan(ownCommits, dayCounts);
    }

    private static LocalDate parseDate(String raw) {
        try {
            return LocalDate.parse(raw);
        } catch (DateTimeParseException malformed) {
            log.debug("Skipping unparseable commit date '{}'", raw);
            return null;
        }
    }
}
