package com.dualfroz.ghstats.common.model;

public record Totals(
        int ownCommits,
        int reposOwn,
        int reposScanned,
        int stars,
        int languages,
        long linesOfCode,
        int activeDays,
        int followers,
        int publicRepos,
        long linesAdded,
        long linesRemoved,
        long longestCommitChurn,
        long charsOfCode,
        int projects
) {
}
