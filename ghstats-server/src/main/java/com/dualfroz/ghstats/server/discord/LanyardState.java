package com.dualfroz.ghstats.server.discord;

import java.time.Instant;

public record LanyardState(
        boolean present,
        boolean stale,
        String status,
        String displayName,
        String username,
        String clanTag,
        String clanBadgeDataUri,
        String avatarDataUri,
        String bannerDataUri,
        Spotify spotify,
        Playing playing,
        String customStatus,
        Instant fetchedAt
) {
    public record Spotify(String song, String artist, String album, String albumArtDataUri,
                          long startMs, long endMs) {
    }

    public record Playing(String name, String details, String state, String imageDataUri) {
    }

    public static LanyardState absent() {
        return new LanyardState(false, false, "offline", "DualFroz", "dualfroz",
                null, null, null, null, null, null, null, null);
    }

    public String token() {
        StringBuilder token = new StringBuilder();
        token.append(present).append('|').append(stale).append('|').append(status)
                .append('|').append(displayName).append('|').append(username)
                .append('|').append(clanTag == null ? "" : clanTag)
                .append('|').append(avatarDataUri != null)
                .append('|').append(clanBadgeDataUri != null)
                .append('|').append(customStatus == null ? "" : customStatus);
        if (spotify != null) {
            token.append("|s:").append(spotify.song()).append(spotify.album());
        }
        if (playing != null) {
            token.append("|p:").append(playing.name()).append(playing.details()).append(playing.state());
        }
        return token.toString();
    }
}
