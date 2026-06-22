package com.dualfroz.ghstats.collector;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
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

    public boolean tokeiAvailable() {
        ProcessRunner.ProcessResult result = processRunner.run(
                List.of(properties.getTokeiBin(), "--version"), null, Duration.ofSeconds(15));
        return result.ok();
    }

    public Map<String, Long> countByLanguage(Path repoPath) {
        ProcessRunner.ProcessResult result = processRunner.run(
                List.of(properties.getTokeiBin(), repoPath.toString(), "--output", "json"),
                null, Duration.ofSeconds(properties.getTokeiTimeoutSeconds()));

        Map<String, Long> byLanguage = new HashMap<>();
        if (!result.ok()) {
            log.warn("tokei failed for {}: {}", repoPath, result.stderr().trim());
            return byLanguage;
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
            }
        } catch (Exception parseFailure) {
            log.warn("Failed to parse tokei output for {}: {}", repoPath, parseFailure.getMessage());
        }
        return byLanguage;
    }
}
