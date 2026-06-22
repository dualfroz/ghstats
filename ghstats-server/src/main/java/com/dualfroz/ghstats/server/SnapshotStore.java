package com.dualfroz.ghstats.server;

import com.dualfroz.ghstats.common.SnapshotMapper;
import com.dualfroz.ghstats.common.model.Snapshot;
import com.dualfroz.ghstats.server.render.CardRenderer;
import com.dualfroz.ghstats.server.render.NoDataCard;
import com.dualfroz.ghstats.server.render.RenderOptions;
import com.dualfroz.ghstats.server.theme.Theme;
import com.dualfroz.ghstats.server.theme.Themes;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.github.benmanes.caffeine.cache.Cache;
import com.github.benmanes.caffeine.cache.Caffeine;
import jakarta.annotation.PostConstruct;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.time.Instant;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

@Component
public class SnapshotStore {

    private static final Logger log = LoggerFactory.getLogger(SnapshotStore.class);

    private final ServerProperties properties;
    private final CardCatalog catalog;
    private final ObjectMapper mapper = SnapshotMapper.create();
    private final Cache<String, String> svgCache = Caffeine.newBuilder().maximumSize(256).build();

    private volatile SnapshotState state = SnapshotState.absent();
    private volatile long lastModifiedMillis = -1;

    public SnapshotStore(ServerProperties properties, CardCatalog catalog) {
        this.properties = properties;
        this.catalog = catalog;
    }

    @PostConstruct
    public void initialLoad() {
        reloadIfChanged();
    }

    @Scheduled(fixedDelayString = "${ghstats.server.pollIntervalSeconds:30}000")
    public void poll() {
        reloadIfChanged();
    }

    public SnapshotState state() {
        return state;
    }

    public String renderCard(String cardKey, RenderOptions options) {
        String cacheKey = cardKey + '|' + options.theme().name() + '|' + options.hideBorder()
                + '|' + (options.range() == null ? "" : options.range().toLowerCase());
        return svgCache.get(cacheKey, ignored -> renderNow(cardKey, options));
    }

    private String renderNow(String cardKey, RenderOptions options) {
        SnapshotState current = state;
        if (!current.hasData()) {
            return NoDataCard.render(cardKey, options.theme(), options.hideBorder());
        }
        CardRenderer renderer = catalog.get(cardKey);
        return renderer.render(current.snapshot(), options);
    }

    private synchronized void reloadIfChanged() {
        Path path = Path.of(properties.getSnapshotPath());
        if (!Files.isReadable(path)) {
            if (state.present()) {
                log.warn("Snapshot file no longer readable at {}", path);
            }
            return;
        }
        long modified;
        try {
            modified = Files.getLastModifiedTime(path).toMillis();
        } catch (IOException statFailure) {
            log.warn("Cannot stat snapshot {}: {}", path, statFailure.getMessage());
            return;
        }
        if (modified == lastModifiedMillis) {
            return;
        }
        try {
            Snapshot snapshot = mapper.readValue(Files.readAllBytes(path), Snapshot.class);
            Instant generatedAt = snapshot.generatedAt() != null ? snapshot.generatedAt() : Instant.now();
            String etag = "W/\"ghstats-" + generatedAt.toEpochMilli() + '"';
            state = new SnapshotState(snapshot, true, generatedAt, etag);
            lastModifiedMillis = modified;
            svgCache.invalidateAll();
            warmDefaults();
            log.info("Loaded snapshot generated at {} ({} calendar days, {} languages)",
                    generatedAt, sizeOf(snapshot), languageCount(snapshot));
        } catch (IOException parseFailure) {
            log.error("Failed to read snapshot {}: {}", path, parseFailure.getMessage());
        }
    }

    private void warmDefaults() {
        Theme theme = Themes.resolve(properties.getDefaultTheme());
        for (String key : catalog.keys()) {
            renderCard(key, new RenderOptions(theme, false, RenderOptions.RANGE_YEAR));
            renderCard(key, new RenderOptions(theme, true, RenderOptions.RANGE_YEAR));
        }
    }

    private int sizeOf(Snapshot snapshot) {
        return snapshot.calendar() == null ? 0 : snapshot.calendar().size();
    }

    private int languageCount(Snapshot snapshot) {
        return snapshot.languagesByLines() == null ? 0 : snapshot.languagesByLines().size();
    }
}
