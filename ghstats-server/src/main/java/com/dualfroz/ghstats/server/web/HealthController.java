package com.dualfroz.ghstats.server.web;

import com.dualfroz.ghstats.server.SnapshotState;
import com.dualfroz.ghstats.server.SnapshotStore;
import java.util.LinkedHashMap;
import java.util.Map;
import org.springframework.http.MediaType;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class HealthController {

    private final SnapshotStore store;

    public HealthController(SnapshotStore store) {
        this.store = store;
    }

    @GetMapping(value = "/healthz", produces = MediaType.APPLICATION_JSON_VALUE)
    public Map<String, Object> health() {
        SnapshotState state = store.state();
        Map<String, Object> payload = new LinkedHashMap<>();
        payload.put("status", statusOf(state));
        payload.put("generated_at", state.generatedAt() != null ? state.generatedAt().toString() : null);
        payload.put("age_seconds", state.ageSeconds());
        return payload;
    }

    private String statusOf(SnapshotState state) {
        if (!state.present()) {
            return "no_data";
        }
        if (!state.hasData()) {
            return "empty";
        }
        return "ok";
    }
}
