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
import java.util.Base64;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

@Component
public class LanyardClient {

    private static final Logger log = LoggerFactory.getLogger(LanyardClient.class);
    private static final String LANYARD = "https://api.lanyard.rest/v1/users/";

    private final ServerProperties properties;
    private final HttpClient http = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(5))
            .followRedirects(HttpClient.Redirect.NORMAL)
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

    @Scheduled(fixedDelayString = "${ghstats.server.lanyardPollSeconds:30}000")
    public void poll() {
        try {
            state = fetch();
        } catch (Exception failure) {
            LanyardState previous = state;
            log.warn("Lanyard fetch failed ({}), keeping last known state", failure.getMessage());
            if (previous.present()) {
                state = markStale(previous);
            }
        }
    }

    public LanyardState state() {
        return state;
    }

    private LanyardState markStale(LanyardState previous) {
        return new LanyardState(true, true, previous.status(), previous.displayName(),
                previous.username(), previous.clanTag(), previous.clanBadgeDataUri(),
                previous.avatarDataUri(), previous.bannerDataUri(), previous.spotify(),
                previous.playing(), previous.customStatus(), previous.fetchedAt());
    }

    private LanyardState fetch() throws Exception {
        JsonNode root = json.readTree(getString(LANYARD + properties.getLanyardUserId()));
        if (!root.path("success").asBoolean(false)) {
            throw new IllegalStateException("success=false");
        }
        JsonNode data = root.path("data");
        JsonNode user = data.path("discord_user");

        String userId = user.path("id").asText(properties.getLanyardUserId());
        String username = user.path("username").asText("dualfroz");
        String displayName = user.hasNonNull("global_name")
                ? user.get("global_name").asText() : username;
        String status = data.path("discord_status").asText("offline");

        String avatarHash = user.path("avatar").asText(null);
        String avatarDataUri = avatarHash == null ? null
                : fetchImage("https://cdn.discordapp.com/avatars/" + userId + "/" + avatarHash + ".png?size=128");

        JsonNode guild = user.path("primary_guild");
        String clanTag = guild.hasNonNull("tag") ? guild.get("tag").asText() : null;
        String clanBadgeDataUri = null;
        if (clanTag != null && guild.hasNonNull("badge") && guild.hasNonNull("identity_guild_id")) {
            clanBadgeDataUri = fetchImage("https://cdn.discordapp.com/guild-tag-badges/"
                    + guild.get("identity_guild_id").asText() + "/" + guild.get("badge").asText()
                    + ".png?size=32");
        }

        String bannerDataUri = properties.getDiscordBanner().startsWith("http")
                ? fetchImage(properties.getDiscordBanner()) : null;

        LanyardState.Spotify spotify = extractSpotify(data);
        LanyardState.Playing playing = spotify == null ? extractPlaying(data) : null;
        String customStatus = extractCustomStatus(data);

        return new LanyardState(true, false, status, displayName, username, clanTag, clanBadgeDataUri,
                avatarDataUri, bannerDataUri, spotify, playing, customStatus, Instant.now());
    }

    private LanyardState.Spotify extractSpotify(JsonNode data) {
        if (!data.path("listening_to_spotify").asBoolean(false) || !data.has("spotify")) {
            return null;
        }
        JsonNode spotify = data.get("spotify");
        JsonNode timestamps = spotify.path("timestamps");
        return new LanyardState.Spotify(
                spotify.path("song").asText(""),
                spotify.path("artist").asText(""),
                spotify.path("album").asText(""),
                fetchImage(spotify.path("album_art_url").asText(null)),
                timestamps.path("start").asLong(0),
                timestamps.path("end").asLong(0));
    }

    private LanyardState.Playing extractPlaying(JsonNode data) {
        JsonNode activities = data.path("activities");
        if (!activities.isArray()) {
            return null;
        }
        for (JsonNode activity : activities) {
            int type = activity.path("type").asInt(0);
            if (type == 2 || type == 4) {
                continue;
            }
            String name = activity.path("name").asText("");
            if (name.isBlank()) {
                continue;
            }
            return new LanyardState.Playing(
                    name,
                    activity.path("details").asText(null),
                    activity.path("state").asText(null),
                    null);
        }
        return null;
    }

    private String extractCustomStatus(JsonNode data) {
        JsonNode activities = data.path("activities");
        if (activities.isArray()) {
            for (JsonNode activity : activities) {
                if (activity.path("type").asInt(0) == 4) {
                    String state = activity.path("state").asText("");
                    return state.isBlank() ? null : state;
                }
            }
        }
        return null;
    }

    private String getString(String url) throws Exception {
        HttpResponse<String> response = http.send(request(url), HttpResponse.BodyHandlers.ofString());
        if (response.statusCode() != 200) {
            throw new IllegalStateException("HTTP " + response.statusCode() + " for " + url);
        }
        return response.body();
    }

    private String fetchImage(String url) {
        if (url == null || url.isBlank()) {
            return null;
        }
        try {
            HttpResponse<byte[]> response = http.send(request(url), HttpResponse.BodyHandlers.ofByteArray());
            if (response.statusCode() != 200) {
                return null;
            }
            String contentType = response.headers().firstValue("content-type").orElse("image/png");
            if (!contentType.startsWith("image/")) {
                contentType = "image/png";
            }
            return "data:" + contentType + ";base64," + Base64.getEncoder().encodeToString(response.body());
        } catch (Exception imageFailure) {
            log.debug("Image fetch failed for {}: {}", url, imageFailure.getMessage());
            return null;
        }
    }

    private HttpRequest request(String url) {
        return HttpRequest.newBuilder()
                .uri(URI.create(url))
                .timeout(Duration.ofSeconds(6))
                .header("User-Agent", "ghstats-discord-card")
                .GET()
                .build();
    }
}
