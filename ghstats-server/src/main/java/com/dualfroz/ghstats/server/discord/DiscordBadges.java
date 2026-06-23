package com.dualfroz.ghstats.server.discord;

import java.io.InputStream;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.Base64;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

@Component
public class DiscordBadges {

    private static final Logger log = LoggerFactory.getLogger(DiscordBadges.class);

    private static final Map<String, String> FILES = new LinkedHashMap<>();

    static {
        FILES.put("nitro", "nitro.svg");
        FILES.put("boost", "boost.svg");
        FILES.put("quest", "quest.png");
        FILES.put("orb", "orb.svg");
        FILES.put("legacy", "legacy.png");
    }

    private final Map<String, String> dataUris = new LinkedHashMap<>();

    public DiscordBadges() {
        FILES.forEach((key, file) -> {
            String dataUri = load(file);
            if (dataUri != null) {
                dataUris.put(key, dataUri);
            } else {
                log.warn("Discord badge asset not found on classpath: badges/{}", file);
            }
        });
    }

    public boolean known(String key) {
        return key != null && dataUris.containsKey(key.toLowerCase());
    }

    public String glyph(String key, int x, int y, int size) {
        String dataUri = key == null ? null : dataUris.get(key.toLowerCase());
        if (dataUri == null) {
            return "";
        }
        return "<image href=\"" + dataUri + "\" x=\"" + x + "\" y=\"" + y + "\" width=\"" + size
                + "\" height=\"" + size + "\" preserveAspectRatio=\"xMidYMid meet\"/>";
    }

    private String load(String file) {
        try (InputStream stream = getClass().getResourceAsStream("/badges/" + file)) {
            if (stream == null) {
                return null;
            }
            byte[] bytes = stream.readAllBytes();
            String mime = file.endsWith(".svg") ? "image/svg+xml" : "image/png";
            return "data:" + mime + ";base64," + Base64.getEncoder().encodeToString(bytes);
        } catch (Exception failure) {
            log.warn("Failed to load badge asset badges/{}: {}", file, failure.getMessage());
            return null;
        }
    }
}
