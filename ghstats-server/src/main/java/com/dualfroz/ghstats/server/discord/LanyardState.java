package com.dualfroz.ghstats.server.discord;

import java.time.Instant;

public record LanyardState(
        boolean present,
        boolean stale,
        String status,
        String displayName,
        String activity,
        Instant fetchedAt
) {
    public static LanyardState absent() {
        return new LanyardState(false, false, "offline", null, null, null);
    }

    public String token() {
        return present + "|" + stale + "|" + status + "|" + displayName + "|" + (activity == null ? "" : activity);
    }
}
