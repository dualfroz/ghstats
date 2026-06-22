package com.dualfroz.ghstats.server.web;

import com.dualfroz.ghstats.server.ServerProperties;
import com.dualfroz.ghstats.server.SnapshotState;
import com.dualfroz.ghstats.server.SnapshotStore;
import com.dualfroz.ghstats.server.render.RenderOptions;
import com.dualfroz.ghstats.server.theme.Theme;
import com.dualfroz.ghstats.server.theme.Themes;
import org.springframework.http.CacheControl;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestHeader;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import java.time.Duration;

@RestController
public class CardController {

    private static final MediaType SVG = MediaType.valueOf("image/svg+xml;charset=utf-8");

    private final SnapshotStore store;
    private final ServerProperties properties;

    public CardController(SnapshotStore store, ServerProperties properties) {
        this.store = store;
        this.properties = properties;
    }

    @GetMapping("/stats")
    public ResponseEntity<String> stats(@RequestParam(required = false) String theme,
                                        @RequestParam(name = "hide_border", required = false) String hideBorder,
                                        @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        return respond("stats", theme, hideBorder, RenderOptions.RANGE_YEAR, ifNoneMatch);
    }

    @GetMapping("/top-langs")
    public ResponseEntity<String> topLangs(@RequestParam(required = false) String theme,
                                           @RequestParam(name = "hide_border", required = false) String hideBorder,
                                           @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        return respond("top-langs", theme, hideBorder, RenderOptions.RANGE_YEAR, ifNoneMatch);
    }

    @GetMapping("/streak")
    public ResponseEntity<String> streak(@RequestParam(required = false) String theme,
                                         @RequestParam(name = "hide_border", required = false) String hideBorder,
                                         @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        return respond("streak", theme, hideBorder, RenderOptions.RANGE_YEAR, ifNoneMatch);
    }

    @GetMapping("/activity")
    public ResponseEntity<String> activity(@RequestParam(required = false) String theme,
                                           @RequestParam(name = "hide_border", required = false) String hideBorder,
                                           @RequestParam(required = false) String range,
                                           @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        String resolvedRange = RenderOptions.RANGE_ALL.equalsIgnoreCase(range)
                ? RenderOptions.RANGE_ALL : RenderOptions.RANGE_YEAR;
        return respond("activity", theme, hideBorder, resolvedRange, ifNoneMatch);
    }

    @GetMapping("/heatmap")
    public ResponseEntity<String> heatmap(@RequestParam(required = false) String theme,
                                          @RequestParam(name = "hide_border", required = false) String hideBorder,
                                          @RequestParam(required = false) String range,
                                          @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        String resolvedRange = RenderOptions.RANGE_ALL.equalsIgnoreCase(range)
                ? RenderOptions.RANGE_ALL : RenderOptions.RANGE_YEAR;
        return respond("heatmap", theme, hideBorder, resolvedRange, ifNoneMatch);
    }

    @GetMapping("/loc")
    public ResponseEntity<String> loc(@RequestParam(required = false) String theme,
                                      @RequestParam(name = "hide_border", required = false) String hideBorder,
                                      @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        return respond("loc", theme, hideBorder, RenderOptions.RANGE_YEAR, ifNoneMatch);
    }

    @GetMapping("/by-year")
    public ResponseEntity<String> byYear(@RequestParam(required = false) String theme,
                                         @RequestParam(name = "hide_border", required = false) String hideBorder,
                                         @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        return respond("by-year", theme, hideBorder, RenderOptions.RANGE_YEAR, ifNoneMatch);
    }

    @GetMapping("/top-repos")
    public ResponseEntity<String> topRepos(@RequestParam(required = false) String theme,
                                           @RequestParam(name = "hide_border", required = false) String hideBorder,
                                           @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        return respond("top-repos", theme, hideBorder, RenderOptions.RANGE_YEAR, ifNoneMatch);
    }

    @GetMapping("/punchcard")
    public ResponseEntity<String> punchcard(@RequestParam(required = false) String theme,
                                            @RequestParam(name = "hide_border", required = false) String hideBorder,
                                            @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        return respond("punchcard", theme, hideBorder, RenderOptions.RANGE_YEAR, ifNoneMatch);
    }

    @GetMapping("/achievements")
    public ResponseEntity<String> achievements(@RequestParam(required = false) String theme,
                                                @RequestParam(name = "hide_border", required = false) String hideBorder,
                                                @RequestHeader(name = "If-None-Match", required = false) String ifNoneMatch) {
        return respond("achievements", theme, hideBorder, RenderOptions.RANGE_YEAR, ifNoneMatch);
    }

    private ResponseEntity<String> respond(String cardKey, String themeParam, String hideBorderParam,
                                           String range, String ifNoneMatch) {
        SnapshotState snapshotState = store.state();
        Theme theme = resolveTheme(themeParam);
        boolean hideBorder = isTrue(hideBorderParam);
        String etag = snapshotState.etag().substring(0, snapshotState.etag().length() - 1)
                + "-" + cardKey + "-" + theme.name() + "-" + hideBorder + "-" + range + "\"";

        CacheControl cacheControl = CacheControl
                .maxAge(Duration.ofSeconds(properties.getCacheMaxAgeSeconds()))
                .cachePublic();

        if (etag.equals(ifNoneMatch)) {
            return ResponseEntity.status(HttpStatus.NOT_MODIFIED)
                    .eTag(etag)
                    .cacheControl(cacheControl)
                    .build();
        }

        RenderOptions options = new RenderOptions(theme, hideBorder, range);
        String svg = store.renderCard(cardKey, options);

        return ResponseEntity.ok()
                .contentType(SVG)
                .eTag(etag)
                .cacheControl(cacheControl)
                .header(HttpHeaders.VARY, "Accept-Encoding")
                .body(svg);
    }

    private Theme resolveTheme(String themeParam) {
        return Themes.resolve(themeParam != null ? themeParam : properties.getDefaultTheme());
    }

    private boolean isTrue(String value) {
        if (value == null) {
            return false;
        }
        return value.isEmpty() || "true".equalsIgnoreCase(value) || "1".equals(value);
    }
}
