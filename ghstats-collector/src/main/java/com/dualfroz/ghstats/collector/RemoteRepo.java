package com.dualfroz.ghstats.collector;

public record RemoteRepo(
        String nameWithOwner,
        boolean fork,
        boolean isPrivate,
        int stargazerCount,
        String primaryLanguage,
        String pushedAt
) {
    public String owner() {
        return nameWithOwner.substring(0, nameWithOwner.indexOf('/'));
    }

    public String repo() {
        return nameWithOwner.substring(nameWithOwner.indexOf('/') + 1);
    }
}
