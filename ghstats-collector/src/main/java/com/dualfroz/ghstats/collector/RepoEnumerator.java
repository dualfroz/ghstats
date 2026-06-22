package com.dualfroz.ghstats.collector;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import java.time.Duration;
import java.util.ArrayList;
import java.util.List;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

@Component
public class RepoEnumerator {

    private static final Logger log = LoggerFactory.getLogger(RepoEnumerator.class);
    private static final String JSON_FIELDS =
            "nameWithOwner,isPrivate,isFork,stargazerCount,primaryLanguage,pushedAt";

    private final ProcessRunner processRunner;
    private final CollectorProperties properties;
    private final ObjectMapper json = new ObjectMapper();

    public RepoEnumerator(ProcessRunner processRunner, CollectorProperties properties) {
        this.processRunner = processRunner;
        this.properties = properties;
    }

    public List<RemoteRepo> enumerate(String owner) {
        List<String> command = List.of(
                properties.getGhBin(), "repo", "list", owner,
                "--limit", String.valueOf(properties.getRepoListLimit()),
                "--json", JSON_FIELDS
        );
        ProcessRunner.ProcessResult result = processRunner.run(
                command, null, Duration.ofSeconds(properties.getEnumerationTimeoutSeconds()));

        if (!result.ok()) {
            log.warn("Failed to enumerate owner {}: exit={} stderr={}",
                    owner, result.exitCode(), result.stderr().trim());
            return List.of();
        }

        return parse(owner, result.stdout());
    }

    private List<RemoteRepo> parse(String owner, String payload) {
        List<RemoteRepo> repos = new ArrayList<>();
        try {
            JsonNode root = json.readTree(payload);
            for (JsonNode node : root) {
                JsonNode language = node.get("primaryLanguage");
                String primaryLanguage = language != null && language.hasNonNull("name")
                        ? language.get("name").asText()
                        : null;
                repos.add(new RemoteRepo(
                        node.get("nameWithOwner").asText(),
                        node.path("isFork").asBoolean(false),
                        node.path("isPrivate").asBoolean(false),
                        node.path("stargazerCount").asInt(0),
                        primaryLanguage,
                        node.path("pushedAt").asText(null)
                ));
            }
        } catch (Exception parseFailure) {
            log.warn("Failed to parse repo list for owner {}: {}", owner, parseFailure.getMessage());
        }
        return repos;
    }
}
