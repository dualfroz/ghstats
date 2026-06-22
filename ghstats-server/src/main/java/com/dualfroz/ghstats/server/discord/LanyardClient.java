package com.dualfroz.ghstats.server.discord;

import com.dualfroz.ghstats.server.ServerProperties;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import jakarta.annotation.PostConstruct;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;
import java.time.Instant;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

@Component
public class LanyardClient {

    private static final Logger log = LoggerFactory.getLogger(LanyardClient.class);
    private static final String BASE = "https://api.lanyard.rest/v1/users/";

    private final ServerProperties properties;
    private final HttpClient http = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(5))
            .build();
    private final ObjectMapper json = new ObjectMapper();

    private volatile LanyardState state = LanyardState.absent();

    public LanyardClient(ServerProperties properties) {
        this.properties = properties;
    }

    @PostConstruct
    public void warmup() {
        poll();
    }

    @Scheduled(fixedDelayString = "${ghstats.server.lanyardPollSeconds:60}000")
    public void poll() {
        try {
            state = fetch();
        } catch (Exception failure) {
            LanyardState previous = state;
            log.warn("Lanyard fetch failed ({}), keeping last known state", failure.getMessage());
            if (previous.present()) {
                state = new LanyardState(true, true, previous.status(), previous.displayName(),
                        previous.activity(), previous.fetchedAt());
            }
        }
    }

    public LanyardState state() {
        return state;
    }

    private LanyardState fetch() throws Exception {
        HttpRequest request = HttpRequest.newBuilder()
                .uri(URI.create(BASE + properties.getLanyardUserId()))
                .timeout(Duration.ofSeconds(6))
                .header("Accept", "application/json")
                .GET()
                .build();
        HttpResponse<String> response = http.send(request, HttpResponse.BodyHandlers.ofString());
        if (response.statusCode() != 200) {
            throw new IllegalStateException("HTTP " + response.statusCode());
        }
        JsonNode root = json.readTree(response.body());
        if (!root.path("success").asBoolean(false)) {
            throw new IllegalStateException("success=false");
        }
        JsonNode data = root.path("data");
        JsonNode user = data.path("discord_user");
        String displayName = user.hasNonNull("global_name")
                ? user.get("global_name").asText()
                : user.path("username").asText("DualFroz");
        String status = data.path("discord_status").asText("offline");
        String activity = extractActivity(data);
        return new LanyardState(true, false, status, displayName, activity, Instant.now());
    }

    private String extractActivity(JsonNode data) {
        if (data.path("listening_to_spotify").asBoolean(false) && data.has("spotify")) {
            JsonNode spotify = data.get("spotify");
            String song = spotify.path("song").asText("");
            return "Listening to Spotify: " + song;
        }
        JsonNode activities = data.path("activities");
        if (activities.isArray()) {
            for (JsonNode activity : activities) {
                int type = activity.path("type").asInt(0);
                if (type == 4) {
                    continue;
                }
                String name = activity.path("name").asText("");
                if (!name.isBlank()) {
                    return verb(type) + " " + name;
                }
            }
        }
        return null;
    }

    private String verb(int type) {
        return switch (type) {
            case 1 -> "Streaming";
            case 2 -> "Listening to";
            case 3 -> "Watching";
            default -> "Playing";
        };
    }
}
