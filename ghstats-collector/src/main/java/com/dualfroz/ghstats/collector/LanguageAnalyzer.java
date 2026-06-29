package com.dualfroz.ghstats.collector;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import java.nio.file.Files;
import java.nio.file.Path;
import java.time.Duration;
import java.util.HashMap;
import java.util.Iterator;
import java.util.List;
import java.util.Map;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

@Component
public class LanguageAnalyzer {

    private static final Logger log = LoggerFactory.getLogger(LanguageAnalyzer.class);

    private final ProcessRunner processRunner;
    private final CollectorProperties properties;
    private final ObjectMapper json = new ObjectMapper();

    public LanguageAnalyzer(ProcessRunner processRunner, CollectorProperties properties) {
        this.processRunner = processRunner;
        this.properties = properties;
    }

    /**
     * Per-repo language scan: lines of code per language (from tokei) and the raw
     * character count (file bytes) per language, summed from tokei's per-file reports.
     * Both maps are keyed by the raw tokei language name (before alias/denylist).
     */
    public record LangScan(Map<String, Long> code, Map<String, Long> bytes) {
    }

    public boolean tokeiAvailable() {
        ProcessRunner.ProcessResult result = processRunner.run(
                List.of(properties.getTokeiBin(), "--version"), null, Duration.ofSeconds(15));
        return result.ok();
    }

    public LangScan countByLanguage(Path repoPath) {
        ProcessRunner.ProcessResult result = processRunner.run(
                List.of(properties.getTokeiBin(), repoPath.toString(), "--output", "json", "--files"),
                null, Duration.ofSeconds(properties.getTokeiTimeoutSeconds()));

        Map<String, Long> byLanguage = new HashMap<>();
        Map<String, Long> bytesByLanguage = new HashMap<>();
        if (!result.ok()) {
            log.warn("tokei failed for {}: {}", repoPath, result.stderr().trim());
            return new LangScan(byLanguage, bytesByLanguage);
        }

        try {
            JsonNode root = json.readTree(result.stdout());
            Iterator<Map.Entry<String, JsonNode>> fields = root.fields();
            while (fields.hasNext()) {
                Map.Entry<String, JsonNode> entry = fields.next();
                String language = entry.getKey();
                if ("Total".equals(language)) {
                    continue;
                }
                JsonNode code = entry.getValue().get("code");
                if (code != null && code.isNumber()) {
                    byLanguage.merge(language, code.asLong(), Long::sum);
                }
                long bytes = sumReportBytes(entry.getValue());
                if (bytes > 0) {
                    bytesByLanguage.merge(language, bytes, Long::sum);
                }
            }
        } catch (Exception parseFailure) {
            log.warn("Failed to parse tokei output for {}: {}", repoPath, parseFailure.getMessage());
        }
        return new LangScan(byLanguage, bytesByLanguage);
    }

    // tokei json puts per-file entries under "reports" (with --files) and embedded
    // languages under "children" (a map language -> reports). Sum the on-disk size of
    // every reported file so charsOfCode reflects real bytes, not an estimate.
    private long sumReportBytes(JsonNode languageNode) {
        long total = 0;
        total += bytesFromReports(languageNode.get("reports"));
        JsonNode children = languageNode.get("children");
        if (children != null && children.isObject()) {
            for (JsonNode child : children) {
                total += bytesFromReports(child);
            }
        }
        return total;
    }

    private long bytesFromReports(JsonNode reports) {
        if (reports == null || !reports.isArray()) {
            return 0;
        }
        long total = 0;
        for (JsonNode report : reports) {
            JsonNode name = report.get("name");
            if (name != null && name.isTextual()) {
                try {
                    total += Files.size(Path.of(name.asText()));
                } catch (Exception unreadable) {
                    log.debug("Could not size reported file {}: {}", name.asText(), unreadable.getMessage());
                }
            }
        }
        return total;
    }
}
