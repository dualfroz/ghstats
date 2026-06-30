package com.dualfroz.ghstats.common;

import java.util.Map;

public final class LanguageColors {

    public static final String UNKNOWN = "#8b8b8b";

    private static final Map<String, String> COLORS = Map.ofEntries(
            Map.entry("TypeScript", "#3178c6"),
            Map.entry("JavaScript", "#f1e05a"),
            Map.entry("Rust", "#dea584"),
            Map.entry("Go", "#00ADD8"),
            Map.entry("Python", "#3572A5"),
            Map.entry("PHP", "#4F5D95"),
            Map.entry("Elixir", "#6e4a7e"),
            Map.entry("Java", "#b07219"),
            Map.entry("Kotlin", "#A97BFF"),
            Map.entry("C#", "#178600"),
            Map.entry("C", "#555555"),
            Map.entry("C++", "#f34b7d"),
            Map.entry("HTML", "#e34c26"),
            Map.entry("CSS", "#563d7c"),
            Map.entry("SCSS", "#c6538c"),
            Map.entry("Vue", "#41b883"),
            Map.entry("Svelte", "#ff3e00"),
            Map.entry("Shell", "#89e051"),
            Map.entry("Bash", "#89e051"),
            Map.entry("Lua", "#000080"),
            Map.entry("Ruby", "#701516"),
            Map.entry("Dart", "#00B4AB"),
            Map.entry("Swift", "#F05138"),
            Map.entry("Dockerfile", "#384d54"),
            Map.entry("Makefile", "#427819"),
            Map.entry("SQL", "#e38c00"),
            Map.entry("PLpgSQL", "#336790"),
            Map.entry("YAML", "#cb171e"),
            Map.entry("JSON", "#292929"),
            Map.entry("TOML", "#9c4221"),
            Map.entry("Markdown", "#083fa1"),
            Map.entry("Zig", "#ec915c"),
            Map.entry("Nix", "#7e7eff"),
            Map.entry("Haskell", "#5e5086"),
            Map.entry("Scala", "#c22d40"),
            Map.entry("F#", "#b845fc"),
            Map.entry("Solidity", "#aa6746"),
            Map.entry("Assembly", "#6e4c13"),
            Map.entry("Erlang", "#B83998"),
            Map.entry("Clojure", "#db5855"),
            Map.entry("PowerShell", "#012456"),
            Map.entry("Objective-C", "#438eff"),
            Map.entry("Perl", "#0298c3"),
            Map.entry("R", "#198CE7"),
            Map.entry("GraphQL", "#e10098"),
            Map.entry("Handlebars", "#f7931e"),
            Map.entry("Twig", "#c1d026"),
            Map.entry("Blade", "#f7523f")
    );

    private LanguageColors() {
    }

    public static String colorFor(String language) {
        return COLORS.getOrDefault(language, UNKNOWN);
    }
}
