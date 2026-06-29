package com.dualfroz.ghstats.collector;

import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.stream.Collectors;

public final class LanguageNormalizer {

    private LanguageNormalizer() {
    }

    public static Map<String, Long> normalize(Map<String, Long> raw, List<String> denylist,
                                              Map<String, String> aliases) {
        Set<String> denied = denylist.stream()
                .map(name -> name.toLowerCase())
                .collect(Collectors.toSet());
        Map<String, String> aliasByLower = new HashMap<>();
        aliases.forEach((from, to) -> aliasByLower.put(from.toLowerCase(), to));

        Map<String, Long> kept = new HashMap<>();
        raw.forEach((language, lines) -> {
            String canonical = aliasByLower.getOrDefault(language.toLowerCase(), language);
            if (denied.contains(canonical.toLowerCase())) {
                return;
            }
            kept.merge(canonical, lines, Long::sum);
        });
        return kept;
    }

    /**
     * Canonical name for a single language, or null when the denylist drops it. Same
     * rules as {@link #normalize}, for callers that aggregate something other than a
     * line count - counting repositories per language, for instance, where summing
     * would double-count a repository whose two aliases collapse into one name.
     */
    public static String canonicalOrNull(String language, List<String> denylist,
                                         Map<String, String> aliases) {
        String canonical = language;
        for (Map.Entry<String, String> alias : aliases.entrySet()) {
            if (alias.getKey().equalsIgnoreCase(language)) {
                canonical = alias.getValue();
                break;
            }
        }
        for (String denied : denylist) {
            if (denied.equalsIgnoreCase(canonical)) {
                return null;
            }
        }
        return canonical;
    }
}
