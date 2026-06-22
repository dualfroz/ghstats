package com.dualfroz.ghstats.server;

import com.dualfroz.ghstats.common.model.Snapshot;
import java.time.Instant;

public record SnapshotState(
        Snapshot snapshot,
        boolean present,
        Instant generatedAt,
        String etag
) {
    public static SnapshotState absent() {
        return new SnapshotState(null, false, null, "W/\"ghstats-empty\"");
    }

    public boolean hasData() {
        return present && snapshot != null && !snapshot.isEmpty();
    }

    public long ageSeconds() {
        if (generatedAt == null) {
            return -1;
        }
        return Instant.now().getEpochSecond() - generatedAt.getEpochSecond();
    }
}
