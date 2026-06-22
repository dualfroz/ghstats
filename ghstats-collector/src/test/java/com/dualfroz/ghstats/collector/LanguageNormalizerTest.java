package com.dualfroz.ghstats.collector;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;

import java.util.List;
import java.util.Map;
import org.junit.jupiter.api.Test;

class LanguageNormalizerTest {

    private static final List<String> DENYLIST = List.of("JSON", "YAML", "Markdown");
    private static final Map<String, String> ALIASES = Map.of("Tsx", "TypeScript", "Jsx", "JavaScript");

    @Test
    void mergesTsxIntoTypeScriptAndJsxIntoJavaScript() {
        Map<String, Long> raw = Map.of(
                "TypeScript", 100L,
                "Tsx", 50L,
                "JavaScript", 20L,
                "Jsx", 30L,
                "Rust", 80L);

        Map<String, Long> kept = LanguageNormalizer.normalize(raw, DENYLIST, ALIASES);

        assertEquals(150L, kept.get("TypeScript"));
        assertEquals(50L, kept.get("JavaScript"));
        assertEquals(80L, kept.get("Rust"));
        assertFalse(kept.containsKey("Tsx"));
        assertFalse(kept.containsKey("Jsx"));
    }

    @Test
    void dropsDenylistedLanguages() {
        Map<String, Long> raw = Map.of(
                "TypeScript", 100L,
                "JSON", 1000L,
                "YAML", 200L,
                "Markdown", 300L,
                "HTML", 40L);

        Map<String, Long> kept = LanguageNormalizer.normalize(raw, DENYLIST, ALIASES);

        assertFalse(kept.containsKey("JSON"));
        assertFalse(kept.containsKey("YAML"));
        assertFalse(kept.containsKey("Markdown"));
        assertEquals(100L, kept.get("TypeScript"));
        assertEquals(40L, kept.get("HTML"));
        assertEquals(2, kept.size());
    }
}
