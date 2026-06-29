package com.dualfroz.ghstats.common.model;

/**
 * One language in the breakdown. {@code lines} is authored churn - gross lines added
 * across the whole history, attributed by file extension - so it only ever grows.
 * {@code codeLines} is what tokei finds on disk today, so deleted and rewritten code
 * is gone from it. The two answer different questions and are kept side by side on
 * purpose. {@code projects} is how many repositories contain the language at all.
 * Snapshots written before these two fields existed deserialize them as 0.
 */
public record LanguageByLines(
        String name,
        long lines,
        double share,
        String color,
        long codeLines,
        int projects
) {
}
