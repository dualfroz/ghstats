package com.dualfroz.ghstats.collector;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.nio.file.Files;
import java.nio.file.Path;
import java.time.Duration;
import java.util.List;
import java.util.Map;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;

class CommitAnalyzerTest {

    private static final String AUTHOR_EMAIL = "me@dualfroz.com";

    private final ProcessRunner processRunner = new ProcessRunner();
    private final CollectorProperties properties = new CollectorProperties();
    private final CommitAnalyzer analyzer = new CommitAnalyzer(processRunner, properties);

    @TempDir
    Path repo;

    @BeforeEach
    void initRepo() {
        git("init", "--quiet");
        git("config", "user.email", AUTHOR_EMAIL);
        git("config", "user.name", "DualFroz");
        git("config", "commit.gpgsign", "false");
    }

    @Test
    void attributesGrossAddedLinesPerLanguageByExtension() {
        writeAndCommit("app.ts", lines(5), "add ts");
        writeAndCommit("lib.rs", lines(3), "add rust");
        writeAndCommit("util.py", lines(4), "add python");
        // Data file with a denylisted extension must not be attributed to any language.
        writeAndCommit("config.json", lines(9), "add json");

        // Editing app.ts adds 2 more TypeScript lines: gross added TS becomes 5 + 2 = 7.
        writeAndCommit("app.ts", lines(7), "grow ts");

        CommitAnalyzer.CommitScan scan = analyzer.analyze(repo, false);
        Map<String, Long> byLanguage = scan.addedByLanguage();

        assertEquals(7L, byLanguage.get("TypeScript"));
        assertEquals(3L, byLanguage.get("Rust"));
        assertEquals(4L, byLanguage.get("Python"));
        assertFalse(byLanguage.containsKey("JSON"),
                "denylisted/unmapped extensions must not be attributed to a language");
        // linesAdded is the all-files total (code + data), so it exceeds the code-only sum.
        assertEquals(7L + 3L + 4L + 9L, scan.linesAdded());
    }

    @Test
    void countsGrossAddedNotNetChurn() {
        writeAndCommit("app.ts", "alpha\nbeta\ngamma\ndelta\nepsilon\nzeta\n", "add ts");
        // Replace with fully distinct content: git sees 6 removed and 4 added. Gross-added
        // attribution must credit 6 + 4 = 10 (not the net 4): "co napisalem" never subtracts
        // removed lines.
        writeAndCommit("app.ts", "one\ntwo\nthree\nfour\n", "rewrite ts");

        CommitAnalyzer.CommitScan scan = analyzer.analyze(repo, false);

        assertEquals(10L, scan.addedByLanguage().get("TypeScript"));
        assertEquals(4L, scan.linesAdded() - scan.linesRemoved(),
                "net churn is 4; the language attribution must be larger, proving it is gross-added");
        assertTrue(scan.linesRemoved() >= 6L, "removed churn is tracked separately, not netted out");
    }

    private static String lines(int count) {
        StringBuilder sb = new StringBuilder();
        for (int i = 1; i <= count; i++) {
            sb.append("line ").append(i).append('\n');
        }
        return sb.toString();
    }

    private void writeAndCommit(String relativePath, String content, String message) {
        try {
            Path file = repo.resolve(relativePath);
            Files.createDirectories(file.getParent() == null ? repo : file.getParent());
            Files.writeString(file, content);
        } catch (Exception failure) {
            throw new RuntimeException(failure);
        }
        git("add", "--all");
        git("commit", "--quiet", "-m", message);
    }

    private void git(String... args) {
        java.util.List<String> command = new java.util.ArrayList<>();
        command.add("git");
        command.add("-C");
        command.add(repo.toString());
        command.addAll(List.of(args));
        ProcessRunner.ProcessResult result = processRunner.run(command, null, Duration.ofSeconds(30));
        if (!result.ok()) {
            throw new IllegalStateException("git " + String.join(" ", args) + " failed: " + result.stderr());
        }
    }
}
