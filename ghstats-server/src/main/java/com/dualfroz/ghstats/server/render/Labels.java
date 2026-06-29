package com.dualfroz.ghstats.server.render;

import java.util.Map;

public record Labels(Map<String, String> values) {

    private static final Map<String, String> EN = Map.ofEntries(
            Map.entry("stats.title", "%s's GitHub Stats"),
            Map.entry("stats.commits", "Own commits (all-time)"),
            Map.entry("stats.repos", "Repositories"),
            Map.entry("stats.stars", "Total stars earned"),
            Map.entry("stats.languages", "Languages used"),
            Map.entry("stats.activeDays", "Active days"),
            Map.entry("stats.linesWritten", "Lines written"),
            Map.entry("stats.linesAddedRemoved", "Lines added / removed"),
            Map.entry("langs.title", "Most Used Languages"),
            Map.entry("langs.other", "Other"),
            Map.entry("langs.alt", "Most used languages pie chart"),
            Map.entry("langs.empty", "No language data available."),
            Map.entry("langs.emptyTitle", "Most used languages - no data"),
            Map.entry("loc.title", "Lines Written"),
            Map.entry("loc.caption", "net lines written, all-time"),
            Map.entry("loc.alt", "Lines written net with added and removed"),
            Map.entry("loc.empty", "No line data available."),
            Map.entry("year.title", "Commits per Year"),
            Map.entry("year.alt", "Commits per year bar chart"),
            Map.entry("year.empty", "No yearly data available."),
            Map.entry("year.emptyTitle", "Commits per year - no data")
    );

    private static final Map<String, String> PL = Map.ofEntries(
            Map.entry("stats.title", "%s w liczbach"),
            Map.entry("stats.commits", "Commity (od zawsze)"),
            Map.entry("stats.repos", "Repozytoria"),
            Map.entry("stats.stars", "Zebrane gwiazdki"),
            Map.entry("stats.languages", "Używane języki"),
            Map.entry("stats.activeDays", "Dni z pracą"),
            Map.entry("stats.linesWritten", "Napisane linie"),
            Map.entry("stats.linesAddedRemoved", "Linie dodane / usunięte"),
            Map.entry("langs.title", "Czym piszemy"),
            Map.entry("langs.other", "Pozostałe"),
            Map.entry("langs.alt", "Wykres używanych języków"),
            Map.entry("langs.empty", "Brak danych o językach."),
            Map.entry("langs.emptyTitle", "Używane języki - brak danych"),
            Map.entry("loc.title", "Napisane linie"),
            Map.entry("loc.caption", "linii kodu netto, od zawsze"),
            Map.entry("loc.alt", "Napisane linie netto z dodanymi i usuniętymi"),
            Map.entry("loc.empty", "Brak danych o liniach."),
            Map.entry("year.title", "Commity rocznie"),
            Map.entry("year.alt", "Wykres commitów w kolejnych latach"),
            Map.entry("year.empty", "Brak danych rocznych."),
            Map.entry("year.emptyTitle", "Commity rocznie - brak danych")
    );

    public static Labels of(String lang) {
        return new Labels("pl".equalsIgnoreCase(lang) ? PL : EN);
    }

    public String get(String key) {
        return values.getOrDefault(key, key);
    }

    public String get(String key, Object... args) {
        return String.format(values.getOrDefault(key, key), args);
    }
}
