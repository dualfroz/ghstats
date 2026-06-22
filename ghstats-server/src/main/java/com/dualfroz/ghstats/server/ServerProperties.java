package com.dualfroz.ghstats.server;

import org.springframework.boot.context.properties.ConfigurationProperties;

@ConfigurationProperties(prefix = "ghstats.server")
public class ServerProperties {

    private String snapshotPath = "/srv/ghstats/data/snapshot.json";
    private int pollIntervalSeconds = 30;
    private int rateLimitPerMinute = 60;
    private int cacheMaxAgeSeconds = 1800;
    private String defaultTheme = "tokyonight";
    private String lanyardUserId = "000000000000000000";
    private int lanyardPollSeconds = 60;
    private int discordCacheMaxAgeSeconds = 60;

    public String getSnapshotPath() {
        return snapshotPath;
    }

    public void setSnapshotPath(String snapshotPath) {
        this.snapshotPath = snapshotPath;
    }

    public int getPollIntervalSeconds() {
        return pollIntervalSeconds;
    }

    public void setPollIntervalSeconds(int pollIntervalSeconds) {
        this.pollIntervalSeconds = pollIntervalSeconds;
    }

    public int getRateLimitPerMinute() {
        return rateLimitPerMinute;
    }

    public void setRateLimitPerMinute(int rateLimitPerMinute) {
        this.rateLimitPerMinute = rateLimitPerMinute;
    }

    public int getCacheMaxAgeSeconds() {
        return cacheMaxAgeSeconds;
    }

    public void setCacheMaxAgeSeconds(int cacheMaxAgeSeconds) {
        this.cacheMaxAgeSeconds = cacheMaxAgeSeconds;
    }

    public String getDefaultTheme() {
        return defaultTheme;
    }

    public void setDefaultTheme(String defaultTheme) {
        this.defaultTheme = defaultTheme;
    }

    public String getLanyardUserId() {
        return lanyardUserId;
    }

    public void setLanyardUserId(String lanyardUserId) {
        this.lanyardUserId = lanyardUserId;
    }

    public int getLanyardPollSeconds() {
        return lanyardPollSeconds;
    }

    public void setLanyardPollSeconds(int lanyardPollSeconds) {
        this.lanyardPollSeconds = lanyardPollSeconds;
    }

    public int getDiscordCacheMaxAgeSeconds() {
        return discordCacheMaxAgeSeconds;
    }

    public void setDiscordCacheMaxAgeSeconds(int discordCacheMaxAgeSeconds) {
        this.discordCacheMaxAgeSeconds = discordCacheMaxAgeSeconds;
    }
}
