package com.dualfroz.ghstats.common.model;

// Earliest calendar year a language appears in his commit history (by author date),
// derived from git numstat file paths. Feeds "experience since <year>" and the
// per-language expertise score, so nothing here is hand-curated.
public record LanguageFirstYear(
        String name,
        int year
) {
}
