package com.dualfroz.ghstats.collector;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import java.time.Duration;
import java.util.List;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

@Component
public class MetaFetcher {

    private static final Logger log = LoggerFactory.getLogger(MetaFetcher.class);

    public record Meta(int followers, int publicRepos) {
    }

    private final ProcessRunner processRunner;
    private final CollectorProperties properties;
    private final ObjectMapper json = new ObjectMapper();

    public MetaFetcher(ProcessRunner processRunner, CollectorProperties properties) {
        this.processRunner = processRunner;
        this.properties = properties;
    }

    public Meta fetch() {
        ProcessRunner.ProcessResult result = processRunner.run(
                List.of(properties.getGhBin(), "api", "user"),
                null, Duration.ofSeconds(properties.getEnumerationTimeoutSeconds()));
        if (!result.ok()) {
            log.warn("Failed to fetch user meta: {}", result.stderr().trim());
            return new Meta(0, 0);
        }
        try {
            JsonNode root = json.readTree(result.stdout());
            return new Meta(root.path("followers").asInt(0), root.path("public_repos").asInt(0));
        } catch (Exception parseFailure) {
            log.warn("Failed to parse user meta: {}", parseFailure.getMessage());
            return new Meta(0, 0);
        }
    }
}
