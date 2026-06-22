package com.dualfroz.ghstats.collector;

import java.util.List;
import org.springframework.boot.context.properties.ConfigurationProperties;

@ConfigurationProperties(prefix = "ghstats.collector")
public class CollectorProperties {

    private List<String> owners = List.of("dualfroz", "VulCodeCom", "Sinlege");
    private List<String> authorEmails = List.of(
            "me@dualfroz.com",
            "me@dualfroz.com",
            "284081405+dualfroz@users.noreply.github.com"
    );
    private List<String> fullCountRepos = List.of();
    private int baselineCommits = 0;
    private String reposRoot = "/srv/ghstats/repos";
    private String snapshotPath = "/srv/ghstats/data/snapshot.json";
    private String ghBin = "gh";
    private String gitBin = "git";
    private String tokeiBin = "tokei";
    private int cloneTimeoutSeconds = 600;
    private int gitTimeoutSeconds = 120;
    private int tokeiTimeoutSeconds = 120;
    private int enumerationTimeoutSeconds = 120;
    private int calendarDays = 371;
    private int topReposCount = 12;
    private int repoListLimit = 1000;
    private String identityName = "DualFroz";
    private String identityLogin = "dualfroz";

    public List<String> getOwners() {
        return owners;
    }

    public void setOwners(List<String> owners) {
        this.owners = owners;
    }

    public List<String> getAuthorEmails() {
        return authorEmails;
    }

    public void setAuthorEmails(List<String> authorEmails) {
        this.authorEmails = authorEmails;
    }

    public List<String> getFullCountRepos() {
        return fullCountRepos;
    }

    public void setFullCountRepos(List<String> fullCountRepos) {
        this.fullCountRepos = fullCountRepos;
    }

    public int getBaselineCommits() {
        return baselineCommits;
    }

    public void setBaselineCommits(int baselineCommits) {
        this.baselineCommits = baselineCommits;
    }

    public String getReposRoot() {
        return reposRoot;
    }

    public void setReposRoot(String reposRoot) {
        this.reposRoot = reposRoot;
    }

    public String getSnapshotPath() {
        return snapshotPath;
    }

    public void setSnapshotPath(String snapshotPath) {
        this.snapshotPath = snapshotPath;
    }

    public String getGhBin() {
        return ghBin;
    }

    public void setGhBin(String ghBin) {
        this.ghBin = ghBin;
    }

    public String getGitBin() {
        return gitBin;
    }

    public void setGitBin(String gitBin) {
        this.gitBin = gitBin;
    }

    public String getTokeiBin() {
        return tokeiBin;
    }

    public void setTokeiBin(String tokeiBin) {
        this.tokeiBin = tokeiBin;
    }

    public int getCloneTimeoutSeconds() {
        return cloneTimeoutSeconds;
    }

    public void setCloneTimeoutSeconds(int cloneTimeoutSeconds) {
        this.cloneTimeoutSeconds = cloneTimeoutSeconds;
    }

    public int getGitTimeoutSeconds() {
        return gitTimeoutSeconds;
    }

    public void setGitTimeoutSeconds(int gitTimeoutSeconds) {
        this.gitTimeoutSeconds = gitTimeoutSeconds;
    }

    public int getTokeiTimeoutSeconds() {
        return tokeiTimeoutSeconds;
    }

    public void setTokeiTimeoutSeconds(int tokeiTimeoutSeconds) {
        this.tokeiTimeoutSeconds = tokeiTimeoutSeconds;
    }

    public int getEnumerationTimeoutSeconds() {
        return enumerationTimeoutSeconds;
    }

    public void setEnumerationTimeoutSeconds(int enumerationTimeoutSeconds) {
        this.enumerationTimeoutSeconds = enumerationTimeoutSeconds;
    }

    public int getCalendarDays() {
        return calendarDays;
    }

    public void setCalendarDays(int calendarDays) {
        this.calendarDays = calendarDays;
    }

    public int getTopReposCount() {
        return topReposCount;
    }

    public void setTopReposCount(int topReposCount) {
        this.topReposCount = topReposCount;
    }

    public int getRepoListLimit() {
        return repoListLimit;
    }

    public void setRepoListLimit(int repoListLimit) {
        this.repoListLimit = repoListLimit;
    }

    public String getIdentityName() {
        return identityName;
    }

    public void setIdentityName(String identityName) {
        this.identityName = identityName;
    }

    public String getIdentityLogin() {
        return identityLogin;
    }

    public void setIdentityLogin(String identityLogin) {
        this.identityLogin = identityLogin;
    }
}
